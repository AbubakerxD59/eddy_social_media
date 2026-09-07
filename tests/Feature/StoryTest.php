<?php

use App\Models\Story;
use App\Models\User;
use App\Models\UserMute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests cannot add a story', function () {
    $this->post(route('stories.store'), [
        'media' => UploadedFile::fake()->image('story.jpg'),
    ])->assertRedirect(route('login'));
});

test('users can add a photo story that expires in 24 hours', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('stories.store'), [
            'media' => UploadedFile::fake()->image('story.jpg'),
            'caption' => 'On site today.',
        ])
        ->assertRedirect(route('dashboard'));

    $story = Story::query()->first();

    expect($story)->not->toBeNull()
        ->and($story?->user_id)->toBe($user->id)
        ->and($story?->caption)->toBe('On site today.')
        ->and($story?->kind->value)->toBe('image')
        ->and($story?->public_id)->toMatch('/^[A-Za-z0-9]{12}$/')
        ->and($story?->expires_at?->greaterThanOrEqualTo(now()->addHours(23)->addMinutes(50)))->toBeTrue()
        ->and($story?->expires_at?->lessThanOrEqualTo(now()->addHours(24)->addMinute()))->toBeTrue();

    Storage::disk('public')->assertExists($story?->path);
});

test('users can add a video story', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('stories.store'), [
            'media' => UploadedFile::fake()->create('story.mp4', 200, 'video/mp4'),
        ])
        ->assertRedirect();

    expect(Story::query()->first()?->kind->value)->toBe('video');
});

test('a story requires a photo or video', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('stories.store'), [
            'caption' => 'No media.',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('media');
});

test('active stories appear in the feed rail with the author avatar', function () {
    Storage::fake('public');

    $viewer = User::factory()->create();
    $author = User::factory()->create([
        'name' => 'Maya Build',
        'avatar_path' => 'avatars/maya.jpg',
    ]);
    $story = Story::factory()->for($author)->create([
        'caption' => 'Crew lunch.',
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Feed')
            ->missing('stories')
            ->loadDeferredProps('stories', fn ($page) => $page
                ->has('stories', 1)
                ->where('stories.0.user.id', $author->id)
                ->where('stories.0.user.name', 'Maya Build')
                ->where('stories.0.user.avatar', $author->avatar_url)
                ->where('stories.0.items.0.id', $story->public_id)
                ->where('stories.0.items.0.caption', 'Crew lunch.')));
});

test('the current users story is listed first', function () {
    $viewer = User::factory()->create();
    $other = User::factory()->create();

    Story::factory()->for($other)->create(['created_at' => now()->subHour()]);
    Story::factory()->for($viewer)->create(['created_at' => now()->subMinutes(10)]);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('stories', fn ($page) => $page
                ->where('stories.0.user.id', $viewer->id)
                ->where('stories.1.user.id', $other->id)));
});

test('expired stories are hidden from the rail', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();

    Story::factory()->for($author)->expired()->create();

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('stories', fn ($page) => $page->has('stories', 0)));
});

test('muted authors do not appear in stories', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();

    Story::factory()->for($author)->create();

    UserMute::query()->create([
        'user_id' => $viewer->id,
        'muted_user_id' => $author->id,
    ]);

    $this->actingAs($viewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('stories', fn ($page) => $page->has('stories', 0)));
});

test('users can delete their own story', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $path = UploadedFile::fake()->image('story.jpg')->store('stories/'.$user->id, 'public');
    $story = Story::factory()->for($user)->create(['path' => $path]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->delete(route('stories.destroy', $story))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseMissing('stories', ['id' => $story->id]);
    Storage::disk('public')->assertMissing($path);
});

test('users cannot delete someone elses story', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $story = Story::factory()->for($owner)->create();

    $this->actingAs($other)
        ->delete(route('stories.destroy', $story))
        ->assertForbidden();

    $this->assertDatabaseHas('stories', ['id' => $story->id]);
});

test('users cannot keep more than ten live stories', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    Story::factory()->for($user)->count(Story::MAX_ACTIVE_PER_USER)->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('stories.store'), [
            'media' => UploadedFile::fake()->image('story.jpg'),
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('media');
});
