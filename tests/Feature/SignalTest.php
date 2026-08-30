<?php

use App\Enums\SignalType;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests are redirected from the feed', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('authenticated users can view the feed', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create(['body' => 'Shipping in public.']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Feed')
            ->has('signals.data', 1)
            ->where('highlight', null)
            ->where('signals.data.0.body', 'Shipping in public.')
            ->where('signals.data.0.id', $signal->public_id)
            ->where('signals.data.0.author.username', $user->username));

    expect($signal->public_id)->not->toBe((string) $signal->getKey());
});

test('the feed highlights a requested signal', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['highlight' => $signal->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Feed')
            ->where('highlight', $signal->public_id));
});

test('users can publish a quote', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Quote->value,
            'body' => 'Hire slowly. Fire slowly too.',
        ])
        ->assertRedirect(route('dashboard', ['highlight' => Signal::query()->first()->public_id]));

    $signal = Signal::query()->first();

    expect($signal?->public_id)->toMatch('/^[A-Za-z0-9]{12}$/');

    $this->assertDatabaseHas('signals', [
        'user_id' => $user->id,
        'type' => SignalType::Quote->value,
        'body' => 'Hire slowly. Fire slowly too.',
        'public_id' => $signal?->public_id,
    ]);
});

test('quote signals require a body', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('signals.store'), [
            'type' => SignalType::Quote->value,
            'body' => '',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('body');
});

test('users can publish an image carousel', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Images->value,
            'body' => 'Launch week.',
            'media' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ])
        ->assertRedirect();

    $signal = Signal::query()->first();

    expect($signal->type)->toBe(SignalType::Images)
        ->and($signal->media)->toHaveCount(2);
});

test('users can publish a video', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Video->value,
            'media' => [
                UploadedFile::fake()->create('pitch.mp4', 200, 'video/mp4'),
            ],
        ])
        ->assertRedirect();

    expect(Signal::query()->first()->type)->toBe(SignalType::Video)
        ->and(Signal::query()->first()->media)->toHaveCount(1);
});

test('users can delete their own signal', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('signals.destroy', $signal))
        ->assertRedirect();

    $this->assertDatabaseMissing('signals', ['id' => $signal->id]);
});

test('users cannot delete someone elses signal', function () {
    $author = User::factory()->create();
    $stranger = User::factory()->create();
    $signal = Signal::factory()->for($author)->create();

    $this->actingAs($stranger)
        ->delete(route('signals.destroy', $signal))
        ->assertForbidden();

    $this->assertDatabaseHas('signals', ['id' => $signal->id]);
});

test('the feed never exposes the numeric signal id', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('signals.data.0.id', $signal->public_id)
            ->whereNot('signals.data.0.id', $signal->getKey()));
});

test('users can heart and unheart a signal', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('signals.like', $signal))
        ->assertOk()
        ->assertJson([
            'id' => $signal->public_id,
            'liked' => true,
            'likes_count' => 1,
        ])
        ->assertJsonMissing(['id' => $signal->getKey()]);

    $this->assertDatabaseHas('signal_likes', [
        'signal_id' => $signal->getKey(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('signals.like', $signal))
        ->assertOk()
        ->assertJson([
            'id' => $signal->public_id,
            'liked' => false,
            'likes_count' => 0,
        ]);
});

test('users can reply to a signal and replies stay off the feed', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create(['body' => 'Root signal']);

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Quote->value,
            'parent_id' => $signal->public_id,
            'body' => 'A reply that uses the unique id.',
        ])
        ->assertRedirect(route('signals.show', $signal));

    $reply = Signal::query()->where('parent_id', $signal->getKey())->first();

    expect($reply?->public_id)->toMatch('/^[A-Za-z0-9]{12}$/')
        ->and($reply?->public_id)->not->toBe((string) $reply?->getKey());

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('signals.data', 1));

    $this->actingAs($user)
        ->get(route('signals.show', $signal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Signals/Show')
            ->where('signal.id', $signal->public_id)
            ->where('replies.0.id', $reply?->public_id)
            ->where('replies.0.body', 'A reply that uses the unique id.'));
});

test('users can save and unsave a signal', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('signals.save', $signal))
        ->assertOk()
        ->assertJson([
            'id' => $signal->public_id,
            'saved' => true,
        ]);

    $this->assertDatabaseHas('signal_saves', [
        'signal_id' => $signal->getKey(),
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('signals.save', $signal))
        ->assertOk()
        ->assertJson([
            'id' => $signal->public_id,
            'saved' => false,
        ]);

    $this->assertDatabaseMissing('signal_saves', [
        'signal_id' => $signal->getKey(),
        'user_id' => $user->id,
    ]);
});

test('users can report someone elses signal', function () {
    $author = User::factory()->create();
    $reporter = User::factory()->create();
    $signal = Signal::factory()->for($author)->create();

    $this->actingAs($reporter)
        ->post(route('signals.report', $signal))
        ->assertOk()
        ->assertJson([
            'id' => $signal->public_id,
            'reported' => true,
        ]);

    $this->assertDatabaseHas('signal_reports', [
        'signal_id' => $signal->getKey(),
        'user_id' => $reporter->id,
    ]);
});

test('users cannot report their own signal', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('signals.report', $signal))
        ->assertForbidden();
});

test('users can mute another user and hide their signals from the feed', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();
    $signal = Signal::factory()->for($author)->create(['body' => 'Should disappear.']);

    $this->actingAs($viewer)
        ->post(route('users.mute', $author))
        ->assertRedirect();

    $this->assertDatabaseHas('user_mutes', [
        'user_id' => $viewer->id,
        'muted_user_id' => $author->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Feed')
            ->has('signals.data', 0));

    $this->actingAs($viewer)
        ->get(route('signals.show', $signal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('signal.id', $signal->public_id)
            ->where('signal.author_muted', true));
});

test('users cannot mute themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('users.mute', $user))
        ->assertForbidden();

    $this->assertDatabaseMissing('user_mutes', [
        'user_id' => $user->id,
        'muted_user_id' => $user->id,
    ]);
});

test('a shared signal is available by unique id', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create(['body' => 'Share this.']);

    $this->actingAs($user)
        ->get(route('signals.show', $signal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Signals/Show')
            ->where('signal.id', $signal->public_id)
            ->whereNot('signal.id', $signal->getKey()));
});
