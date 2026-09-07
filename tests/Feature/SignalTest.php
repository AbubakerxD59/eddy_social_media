<?php

use App\Enums\SignalType;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
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
            ->missing('signals')
            ->missing('stories')
            ->missing('rail')
            ->where('highlight', null)
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 1)
                ->where('signals.data.0.body', 'Shipping in public.')
                ->where('signals.data.0.id', $signal->public_id)
                ->where('signals.data.0.type', 'drop')
                ->where('signals.data.0.author.username', $user->username)
                ->where('signals.per_page', 20))
            ->loadDeferredProps('stories', fn ($page) => $page->has('stories'))
            ->loadDeferredProps('rail', fn ($page) => $page
                ->has('rail.level')
                ->has('rail.matches')
                ->has('rail.needs')));

    expect($signal->public_id)->not->toBe((string) $signal->getKey());
});

test('the feed can be filtered by opportunities and needs', function () {
    $user = User::factory()->create();
    Signal::factory()->for($user)->drop()->create();
    $need = Signal::factory()->for($user)->need()->create();
    $opportunity = Signal::factory()->for($user)->opportunity()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['filter' => 'opportunity']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeFilter', 'opportunity')
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 1)
                ->where('signals.data.0.id', $opportunity->public_id)
                ->where('signals.data.0.type', 'opportunity')));

    $this->actingAs($user)
        ->get(route('dashboard', ['filter' => 'need']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeFilter', 'need')
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 1)
                ->where('signals.data.0.id', $need->public_id)
                ->where('signals.data.0.type', 'need')));
});

test('the connections feed is empty until it is implemented', function () {
    $user = User::factory()->create();
    Signal::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['filter' => 'connections']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('activeFilter', 'connections')
            ->loadDeferredProps('feed', fn ($page) => $page->has('signals.data', 0)));
});

test('the feed loads twenty signals at a time', function () {
    $user = User::factory()->create();
    Signal::factory()->for($user)->count(21)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('signals')
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 20)
                ->where('signals.per_page', 20)
                ->where('signals.last_page', 2)));
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

test('users can publish a drop', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Drop->value,
            'body' => 'Hire slowly. Fire slowly too.',
        ])
        ->assertRedirect(route('dashboard', ['highlight' => Signal::query()->first()->public_id]));

    $signal = Signal::query()->first();

    expect($signal?->public_id)->toMatch('/^[A-Za-z0-9]{12}$/');

    $this->assertDatabaseHas('signals', [
        'user_id' => $user->id,
        'type' => SignalType::Drop->value,
        'body' => 'Hire slowly. Fire slowly too.',
        'public_id' => $signal?->public_id,
    ]);
});

test('drop signals require a body, media, or a link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('signals.store'), [
            'type' => SignalType::Drop->value,
            'body' => '',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('body');
});

test('empty html is treated as a missing drop body', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('signals.store'), [
            'type' => SignalType::Drop->value,
            'body' => '<p></p>',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('body');
});

test('drop bodies keep basic formatting and drop scripts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Drop->value,
            'body' => '<p>Hello <strong>world</strong><script>alert(1)</script> <a href="javascript:alert(1)">bad</a> <a href="https://eddy.test">good</a></p>',
        ])
        ->assertRedirect();

    $signal = Signal::query()->first();

    expect($signal?->body)
        ->toContain('<strong>world</strong>')
        ->and($signal?->body)->not->toContain('script')
        ->and($signal?->body)->not->toContain('javascript:')
        ->and($signal?->body)->toContain('https://eddy.test')
        ->and($signal?->body)->toContain('good');
});

test('users can publish an image carousel', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Drop->value,
            'body' => 'Launch week.',
            'media' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ])
        ->assertRedirect();

    $signal = Signal::query()->first();

    expect($signal->type)->toBe(SignalType::Drop)
        ->and($signal->media)->toHaveCount(2);
});

test('users can publish a video', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Drop->value,
            'media' => [
                UploadedFile::fake()->create('pitch.mp4', 200, 'video/mp4'),
            ],
        ])
        ->assertRedirect();

    expect(Signal::query()->first()->type)->toBe(SignalType::Drop)
        ->and(Signal::query()->first()->media)->toHaveCount(1);
});

test('users can publish mixed images and videos', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Drop->value,
            'body' => 'Site walkthrough.',
            'media' => [
                UploadedFile::fake()->image('still.jpg'),
                UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'),
                UploadedFile::fake()->image('detail.png'),
            ],
        ])
        ->assertRedirect();

    $signal = Signal::query()->first();

    expect($signal?->media)->toHaveCount(3)
        ->and($signal?->media->pluck('kind')->map->value->all())->toBe(['image', 'video', 'image']);
});

test('users can stage media and publish with upload ids', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $upload = $this->actingAs($user)
        ->postJson(route('signals.uploads.store'), [
            'media' => UploadedFile::fake()->image('site.jpg'),
        ])
        ->assertOk()
        ->json();

    expect($upload['id'])->toBeString()
        ->and($upload['kind'])->toBe('image');

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Drop->value,
            'body' => 'From the site.',
            'media_ids' => [$upload['id']],
        ])
        ->assertRedirect();

    $signal = Signal::query()->first();

    expect($signal?->media)->toHaveCount(1)
        ->and($signal?->media->first()?->kind->value)->toBe('image');

    $this->assertDatabaseCount('signal_uploads', 0);
});

test('guests cannot stage media uploads', function () {
    $this->postJson(route('signals.uploads.store'), [
        'media' => UploadedFile::fake()->image('site.jpg'),
    ])->assertUnauthorized();
});

test('users can delete a staged upload', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $upload = $this->actingAs($user)
        ->postJson(route('signals.uploads.store'), [
            'media' => UploadedFile::fake()->image('site.jpg'),
        ])
        ->json();

    $this->actingAs($user)
        ->deleteJson(route('signals.uploads.destroy', $upload['id']))
        ->assertOk();

    $this->assertDatabaseCount('signal_uploads', 0);
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
            ->loadDeferredProps('feed', fn ($page) => $page
                ->where('signals.data.0.id', $signal->public_id)
                ->whereNot('signals.data.0.id', $signal->getKey())));
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
            'type' => SignalType::Drop->value,
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
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('feed', fn ($page) => $page->has('signals.data', 1)));

    $this->actingAs($user)
        ->get(route('signals.show', $signal))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Signals/Show')
            ->where('signal.id', $signal->public_id)
            ->loadDeferredProps('replies', fn ($page) => $page
                ->where('replies.0.id', $reply?->public_id)
                ->where('replies.0.body', 'A reply that uses the unique id.')));
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
            ->loadDeferredProps('feed', fn ($page) => $page->has('signals.data', 0)));

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

test('users can publish a need', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Need->value,
            'title' => 'Need a video editor for ongoing projects',
            'body' => 'Weekly product clips, 30-60 seconds.',
            'budget' => '$500 - $1,000 / project',
            'timeline' => '2 weeks',
            'location' => 'Remote',
            'skills' => 'Premiere Pro, After Effects',
        ])
        ->assertRedirect();

    $signal = Signal::query()->first();

    expect($signal?->type)->toBe(SignalType::Need)
        ->and($signal?->title)->toBe('Need a video editor for ongoing projects')
        ->and($signal?->latitude)->toBeNull()
        ->and($signal?->longitude)->toBeNull()
        ->and($signal?->payload)->toMatchArray([
            'budget' => '$500 - $1,000 / project',
            'timeline' => '2 weeks',
            'location' => 'Remote',
            'skills' => ['Premiere Pro', 'After Effects'],
        ]);
});

test('need signals require a title', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('signals.store'), [
            'type' => SignalType::Need->value,
            'body' => 'Help wanted.',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors('title');
});

test('users can publish an opportunity', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Opportunity->value,
            'title' => 'Commercial construction project',
            'body' => 'Seeking trade partners.',
            'project_value' => '$250K - $500K',
            'timeline' => '3 months',
            'location' => 'Orlando, FL',
            'trades' => 'Electrical, HVAC, Plumbing',
        ])
        ->assertRedirect();

    $signal = Signal::query()->first();

    expect($signal?->type)->toBe(SignalType::Opportunity)
        ->and($signal?->title)->toBe('Commercial construction project')
        ->and($signal?->payload)->toMatchArray([
            'project_value' => '$250K - $500K',
            'timeline' => '3 months',
            'location' => 'Orlando, FL',
            'trades' => ['Electrical', 'HVAC', 'Plumbing'],
        ]);
});

test('needs store coordinates from a selected google place', function () {
    config(['services.google.places_key' => 'test-key']);

    Http::fake(fn () => Http::response([
        'status' => 'OK',
        'result' => [
            'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
            'name' => 'Orlando',
            'formatted_address' => 'Orlando, FL, USA',
            'geometry' => [
                'location' => [
                    'lat' => 28.5383355,
                    'lng' => -81.3792365,
                ],
            ],
        ],
    ]));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Need->value,
            'title' => 'Need a plumber this week',
            'location' => 'Orlando',
            'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
            'latitude' => 1,
            'longitude' => 2,
        ])
        ->assertRedirect();

    $signal = Signal::query()->first();

    expect($signal?->place_id)->toBe('ChIJN1t_tDeuEmsRUsoyG83frY4')
        ->and($signal?->latitude)->toEqual(28.5383355)
        ->and($signal?->longitude)->toEqual(-81.3792365)
        ->and($signal?->payload['location'])->toBe('Orlando, FL, USA');
});

test('the feed ranks nearby signals first when a location is provided', function () {
    $user = User::factory()->create();

    Signal::factory()->for($user)->need()->at(40.7128, -74.006, 'New York, NY')->create([
        'created_at' => now(),
    ]);
    $near = Signal::factory()->for($user)->need()->at(28.5383, -81.3792, 'Orlando, FL')->create([
        'created_at' => now()->subHour(),
    ]);
    $withinRadius = Signal::factory()->for($user)->need()->at(27.9506, -82.4572, 'Tampa, FL')->create([
        'created_at' => now()->subMinutes(30),
    ]);
    $unlocated = Signal::factory()->for($user)->drop()->create([
        'created_at' => now()->addMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard', ['lat' => 28.54, 'lng' => -81.38]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('viewerLatitude', 28.54)
            ->where('viewerLongitude', -81.38)
            ->loadDeferredProps('feed', fn ($page) => $page
                ->has('signals.data', 3)
                ->where('signals.data.0.id', $near->public_id)
                ->where('signals.data.1.id', $withinRadius->public_id)
                ->where('signals.data.2.id', $unlocated->public_id)));
});

test('users can publish a poll', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Poll->value,
            'body' => 'Which channel should we double down on?',
            'poll_options' => ['LinkedIn', 'Short-form video', 'Email'],
        ])
        ->assertRedirect();

    $signal = Signal::query()->first();

    expect($signal?->type)->toBe(SignalType::Poll)
        ->and($signal?->payload)->toMatchArray([
            'options' => [
                ['id' => '1', 'text' => 'LinkedIn'],
                ['id' => '2', 'text' => 'Short-form video'],
                ['id' => '3', 'text' => 'Email'],
            ],
        ]);
});

test('polls require a question and two options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('signals.store'), [
            'type' => SignalType::Poll->value,
            'body' => '',
            'poll_options' => ['Only one'],
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasErrors(['body', 'poll_options']);
});

test('users can vote on a poll', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->poll()->create();

    $this->actingAs($user)
        ->postJson(route('signals.vote', $signal), ['option_id' => '2'])
        ->assertOk()
        ->assertJsonPath('id', $signal->public_id)
        ->assertJsonPath('poll.voted_option_id', '2')
        ->assertJsonPath('poll.total_votes', 1)
        ->assertJsonPath('poll.options.1.votes_count', 1);

    $this->assertDatabaseHas('poll_votes', [
        'signal_id' => $signal->getKey(),
        'user_id' => $user->id,
        'option_id' => '2',
    ]);
});

test('users can change their poll vote', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->poll()->create();

    $this->actingAs($user)
        ->postJson(route('signals.vote', $signal), ['option_id' => '1'])
        ->assertOk();

    $this->actingAs($user)
        ->postJson(route('signals.vote', $signal), ['option_id' => '3'])
        ->assertOk()
        ->assertJsonPath('poll.voted_option_id', '3')
        ->assertJsonPath('poll.total_votes', 1);

    $this->assertDatabaseHas('poll_votes', [
        'signal_id' => $signal->getKey(),
        'user_id' => $user->id,
        'option_id' => '3',
    ]);
});

test('users cannot vote on a non-poll signal', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('signals.vote', $signal), ['option_id' => '1'])
        ->assertNotFound();
});

test('replies are always drops', function () {
    $user = User::factory()->create();
    $signal = Signal::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('signals.store'), [
            'type' => SignalType::Need->value,
            'parent_id' => $signal->public_id,
            'title' => 'This should be ignored',
            'body' => 'A reply that stays a drop.',
        ])
        ->assertRedirect(route('signals.show', $signal));

    $reply = Signal::query()->where('parent_id', $signal->getKey())->first();

    expect($reply?->type)->toBe(SignalType::Drop)
        ->and($reply?->title)->toBeNull();
});
