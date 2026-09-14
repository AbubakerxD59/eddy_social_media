<?php

use App\Enums\ConnectionStatus;
use App\Models\Connection;
use App\Models\User;
use App\Models\UserNotification;

test('users can send a connection request and notify the other person', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->postJson(route('users.connect', $target))
        ->assertOk()
        ->assertJsonPath('status', 'pending_outgoing')
        ->assertJsonPath('message', 'Connection request sent.');

    $this->assertDatabaseHas('connections', [
        'requester_id' => $actor->id,
        'addressee_id' => $target->id,
        'status' => ConnectionStatus::Pending->value,
    ]);

    expect(UserNotification::query()->where('user_id', $target->id)->where('type', 'connection_request')->count())->toBe(1)
        ->and(UserNotification::query()->where('user_id', $actor->id)->count())->toBe(0);

    $this->actingAs($target)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('notifications.0.can_accept', true)
        ->assertJsonPath('notifications.0.can_reject', true);
});

test('users cannot connect with themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('users.connect', $user))
        ->assertForbidden();
});

test('accepting a connection request notifies the requester', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)->postJson(route('users.connect', $target))->assertOk();

    $notification = UserNotification::query()->where('user_id', $target->id)->first();

    $this->actingAs($target)
        ->postJson(route('notifications.accept', $notification))
        ->assertOk()
        ->assertJsonPath('message', 'Connection request accepted.');

    expect(Connection::query()->first()?->status)->toBe(ConnectionStatus::Accepted)
        ->and(UserNotification::query()->where('user_id', $actor->id)->where('type', 'connection_accepted')->count())->toBe(1)
        ->and($notification?->fresh()?->read_at)->not->toBeNull()
        ->and($notification?->fresh()?->toFeedArray()['can_accept'])->toBeFalse();
});

test('rejecting a connection request does not notify the requester', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)->postJson(route('users.connect', $target))->assertOk();

    $notification = UserNotification::query()->where('user_id', $target->id)->first();

    $this->actingAs($target)
        ->postJson(route('notifications.reject', $notification))
        ->assertOk();

    expect(Connection::query()->first()?->status)->toBe(ConnectionStatus::Rejected)
        ->and(UserNotification::query()->where('user_id', $actor->id)->count())->toBe(0)
        ->and($notification?->fresh()?->read_at)->not->toBeNull();
});

test('the profile card reports connection status', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create(['headline' => 'Builder']);

    $this->actingAs($actor)
        ->getJson(route('users.card', $target))
        ->assertOk()
        ->assertJsonPath('connection', 'none')
        ->assertJsonPath('is_own', false)
        ->assertJsonPath('user.cover', $target->cover_url);

    $this->actingAs($actor)->postJson(route('users.connect', $target))->assertOk();

    $this->actingAs($actor)
        ->getJson(route('users.card', $target))
        ->assertJsonPath('connection', 'pending_outgoing');

    $this->actingAs($target)
        ->getJson(route('users.card', $actor))
        ->assertJsonPath('connection', 'pending_incoming');
});

test('users cannot accept a request that is not for them', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($actor)->postJson(route('users.connect', $target))->assertOk();

    $notification = UserNotification::query()->where('user_id', $target->id)->first();

    $this->actingAs($stranger)
        ->postJson(route('notifications.accept', $notification))
        ->assertForbidden();
});
