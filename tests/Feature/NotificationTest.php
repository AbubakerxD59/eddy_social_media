<?php

use App\Enums\SignalType;
use App\Models\Signal;
use App\Models\User;
use App\Models\UserMute;
use App\Models\UserNotification;
use App\Services\NearbySignalNotifier;
use Illuminate\Support\Facades\Http;

test('nearby users are notified when a need is posted', function () {
    $author = User::factory()->create();
    $nearby = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
    ]);
    $far = User::factory()->create([
        'latitude' => 40.7128,
        'longitude' => -74.006,
    ]);

    $signal = Signal::factory()->for($author)->need()->at(28.5383, -81.3792, 'Orlando, FL')->create();

    app(NearbySignalNotifier::class)->notify($signal);

    expect(UserNotification::query()->where('user_id', $nearby->id)->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $author->id)->count())->toBe(0)
        ->and(UserNotification::query()->where('user_id', $far->id)->count())->toBe(0);

    $notification = UserNotification::query()->where('user_id', $nearby->id)->first();

    expect($notification?->type)->toBe('nearby_need')
        ->and($notification?->url)->toBe('/s/'.$signal->public_id)
        ->and($notification?->title)->toContain('posted a need nearby');
});

test('nearby users are notified when an opportunity is posted', function () {
    $author = User::factory()->talent()->create();
    $nearby = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
    ]);

    $signal = Signal::factory()->for($author)->opportunity()->create();

    app(NearbySignalNotifier::class)->notify($signal);

    expect(UserNotification::query()->where('user_id', $nearby->id)->value('type'))->toBe('nearby_opportunity');
});

test('users who muted the author are not notified', function () {
    $author = User::factory()->create();
    $nearby = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
    ]);

    UserMute::query()->create([
        'user_id' => $nearby->id,
        'muted_user_id' => $author->id,
    ]);

    $signal = Signal::factory()->for($author)->need()->at(28.5383, -81.3792, 'Orlando, FL')->create();

    app(NearbySignalNotifier::class)->notify($signal);

    expect(UserNotification::query()->count())->toBe(0);
});

test('drops and unlocated needs do not notify nearby users', function () {
    $author = User::factory()->create();
    User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
    ]);

    app(NearbySignalNotifier::class)->notify(
        Signal::factory()->for($author)->drop()->create(),
    );
    app(NearbySignalNotifier::class)->notify(
        Signal::factory()->for($author)->need()->create(),
    );

    expect(UserNotification::query()->count())->toBe(0);
});

test('publishing a located need creates notifications for nearby users', function () {
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

    $author = User::factory()->create();
    $nearby = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
    ]);

    $this->actingAs($author)
        ->post(route('signals.store'), [
            'type' => SignalType::Need->value,
            'title' => 'Need a plumber this week',
            'location' => 'Orlando',
            'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
            'latitude' => 1,
            'longitude' => 2,
        ])
        ->assertRedirect();

    expect(UserNotification::query()->where('user_id', $nearby->id)->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $author->id)->count())->toBe(0);
});

test('users can list poll and mark notifications as read', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create();
    $older = UserNotification::query()->create([
        'user_id' => $user->id,
        'actor_id' => $actor->id,
        'type' => 'nearby_need',
        'title' => 'Older need nearby',
        'body' => 'First',
        'url' => '/s/abc',
        'read_at' => null,
    ]);
    $newer = UserNotification::query()->create([
        'user_id' => $user->id,
        'actor_id' => $actor->id,
        'type' => 'nearby_opportunity',
        'title' => 'Newer opportunity nearby',
        'body' => 'Second',
        'url' => '/s/def',
        'read_at' => null,
    ]);

    $this->actingAs($user)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 2)
        ->assertJsonCount(2, 'notifications');

    $this->actingAs($user)
        ->getJson(route('notifications.index', ['after_id' => $older->id]))
        ->assertOk()
        ->assertJsonCount(1, 'notifications')
        ->assertJsonPath('notifications.0.id', $newer->id);

    $this->actingAs($user)
        ->postJson(route('notifications.read', $older))
        ->assertOk()
        ->assertJsonPath('unread_count', 1);

    expect($older->fresh()?->read_at)->not->toBeNull();

    $this->actingAs($user)
        ->postJson(route('notifications.read-all'))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);
});

test('users cannot read someone elses notification', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $notification = UserNotification::query()->create([
        'user_id' => $other->id,
        'type' => 'nearby_need',
        'title' => 'Need nearby',
        'url' => '/s/abc',
    ]);

    $this->actingAs($user)
        ->postJson(route('notifications.read', $notification))
        ->assertForbidden();
});
