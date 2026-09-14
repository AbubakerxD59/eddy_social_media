<?php

use App\Enums\ConnectionStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Models\Connection;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('users cannot message themselves', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('messages.with', $user))
        ->assertForbidden();
});

test('connected users can message each other directly', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => ConnectionStatus::Accepted,
    ]);

    $this->actingAs($alice)
        ->get(route('messages.with', $bob))
        ->assertRedirect();

    $conversation = Conversation::between($alice->id, $bob->id);

    expect($conversation?->status)->toBe(ConversationStatus::Accepted);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Hello Bob'])
        ->assertOk()
        ->assertJsonPath('message.kind', 'text')
        ->assertJsonPath('message.body', 'Hello Bob');

    $this->actingAs($bob)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Hi Alice'])
        ->assertOk();

    expect(Message::query()->count())->toBe(2)
        ->and(UserNotification::query()->where('type', 'message_request')->count())->toBe(0);
});

test('unconnected users send a message request the other person can accept', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)->get(route('messages.with', $bob))->assertRedirect();

    $conversation = Conversation::between($alice->id, $bob->id);

    expect($conversation?->status)->toBe(ConversationStatus::Pending)
        ->and($conversation?->initiator_id)->toBe($alice->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Can we talk?'])
        ->assertOk();

    expect(UserNotification::query()->where('user_id', $bob->id)->where('type', 'message_request')->count())->toBe(1);

    $this->actingAs($bob)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Not yet'])
        ->assertForbidden();

    $notification = UserNotification::query()->where('user_id', $bob->id)->first();

    $this->actingAs($bob)
        ->getJson(route('notifications.index'))
        ->assertOk()
        ->assertJsonPath('notifications.0.can_accept', true);

    $this->actingAs($bob)
        ->postJson(route('notifications.accept', $notification))
        ->assertOk()
        ->assertJsonPath('message', 'Message request accepted.');

    expect($conversation?->fresh()?->status)->toBe(ConversationStatus::Accepted)
        ->and(UserNotification::query()->where('user_id', $alice->id)->where('type', 'message_accepted')->count())->toBe(1);

    $this->actingAs($bob)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Sure'])
        ->assertOk();
});

test('declining a message request closes the chat', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice)->get(route('messages.with', $bob));
    $conversation = Conversation::between($alice->id, $bob->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Hello']);

    $this->actingAs($bob)
        ->postJson(route('conversations.reject', $conversation))
        ->assertOk();

    expect($conversation?->fresh()?->status)->toBe(ConversationStatus::Rejected);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Again'])
        ->assertForbidden();

    $this->actingAs($bob)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'No'])
        ->assertForbidden();
});

test('users can send image video and file attachments', function () {
    Storage::fake('public');

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => ConnectionStatus::Accepted,
    ]);

    $this->actingAs($alice)->get(route('messages.with', $bob));
    $conversation = Conversation::between($alice->id, $bob->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->image('photo.jpg'),
        ])
        ->assertOk()
        ->assertJsonPath('message.kind', MessageKind::Image->value);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'),
        ])
        ->assertOk()
        ->assertJsonPath('message.kind', MessageKind::Video->value);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('message.kind', MessageKind::File->value)
        ->assertJsonPath('message.original_name', 'brief.pdf');

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->create(
                'notes.docx',
                80,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ),
        ])
        ->assertOk()
        ->assertJsonPath('message.kind', MessageKind::File->value);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), [
            'attachment' => UploadedFile::fake()->create('archive.zip', 40, 'application/zip'),
        ])
        ->assertInvalid(['attachments.0']);
});

test('users can send multiple attachments as a grouped album', function () {
    Storage::fake('public');

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => ConnectionStatus::Accepted,
    ]);

    $this->actingAs($alice)->get(route('messages.with', $bob));
    $conversation = Conversation::between($alice->id, $bob->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), [
            'body' => 'Look at these',
            'attachments' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'),
            ],
        ])
        ->assertOk()
        ->assertJsonCount(3, 'messages')
        ->assertJsonPath('messages.0.body', 'Look at these')
        ->assertJsonPath('messages.1.body', null)
        ->assertJsonPath('conversation.last_message_preview', '3 files');

    $groupId = Message::query()->value('group_id');

    expect($groupId)->not->toBeNull()
        ->and(Message::query()->where('group_id', $groupId)->count())->toBe(3);
});

test('users can reply to a message in the same chat', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => ConnectionStatus::Accepted,
    ]);

    $this->actingAs($alice)->get(route('messages.with', $bob));
    $conversation = Conversation::between($alice->id, $bob->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Are you free?'])
        ->assertOk();

    $original = Message::query()->first();
    $carol = User::factory()->create();
    [$low, $high] = Conversation::pairIds($alice->id, $carol->id);
    $other = Conversation::query()->create([
        'user_low_id' => $low,
        'user_high_id' => $high,
        'initiator_id' => $alice->id,
        'status' => ConversationStatus::Accepted,
    ]);

    $this->actingAs($bob)
        ->post(route('conversations.messages.store', $conversation), [
            'body' => 'Yes, after 5.',
            'reply_to_id' => $original?->public_id,
        ])
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'Yes, after 5.')
        ->assertJsonPath('messages.0.reply_to.id', $original?->public_id)
        ->assertJsonPath('messages.0.reply_to.body', 'Are you free?');

    $this->actingAs($bob)
        ->post(route('conversations.messages.store', $conversation), [
            'body' => 'Nope',
            'reply_to_id' => Message::query()->create([
                'conversation_id' => $other->id,
                'user_id' => $alice->id,
                'kind' => MessageKind::Text,
                'body' => 'Secret',
            ])->public_id,
        ])
        ->assertInvalid(['reply_to_id']);
});

test('owners can edit their own messages', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => ConnectionStatus::Accepted,
    ]);

    $this->actingAs($alice)->get(route('messages.with', $bob));
    $conversation = Conversation::between($alice->id, $bob->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Hello Bob'])
        ->assertOk();

    $message = Message::query()->first();

    $this->actingAs($alice)
        ->patch(route('conversations.messages.update', [$conversation, $message]), ['body' => 'Hello Robert'])
        ->assertOk()
        ->assertJsonPath('messages.0.body', 'Hello Robert')
        ->assertJsonPath('messages.0.can_edit', true);

    expect($message?->fresh()?->body)->toBe('Hello Robert')
        ->and($message?->fresh()?->edited_at)->not->toBeNull()
        ->and($conversation?->fresh()?->last_message_preview)->toBe('Hello Robert');

    $this->actingAs($bob)
        ->patch(route('conversations.messages.update', [$conversation, $message]), ['body' => 'Hijack'])
        ->assertForbidden();
});

test('owners can delete their own message within ten minutes', function () {
    Storage::fake('public');

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => ConnectionStatus::Accepted,
    ]);

    $this->actingAs($alice)->get(route('messages.with', $bob));
    $conversation = Conversation::between($alice->id, $bob->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), [
            'body' => 'Look at these',
            'attachments' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
            ],
        ])
        ->assertOk();

    $message = Message::query()->orderBy('id')->first();
    $groupId = $message?->group_id;
    $path = $message?->path;

    $response = $this->actingAs($alice)
        ->delete(route('conversations.messages.destroy', [$conversation, $message]))
        ->assertOk();

    expect($response->json('messages.0.body'))->toBeNull()
        ->and($response->json('messages.0.deleted_at'))->not->toBeNull();

    expect(Message::query()->count())->toBe(0)
        ->and(Message::withTrashed()->count())->toBe(2)
        ->and(Message::withTrashed()->where('group_id', $groupId)->count())->toBe(2)
        ->and($path)->not->toBeNull();

    Storage::disk('public')->assertMissing($path);

    $latest = Message::withTrashed()->orderByDesc('id')->first();
    $updates = $this->actingAs($bob)
        ->getJson(route('conversations.messages', [
            'conversation' => $conversation,
            'after' => $latest?->public_id,
            'synced_at' => now()->subMinute()->toIso8601String(),
        ]))
        ->assertOk()
        ->json('updates');

    expect($updates)->not->toBeEmpty()
        ->and($updates[0]['deleted_at'])->not->toBeNull();
});

test('owners cannot delete a message after ten minutes', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => ConnectionStatus::Accepted,
    ]);

    $this->actingAs($alice)->get(route('messages.with', $bob));
    $conversation = Conversation::between($alice->id, $bob->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Keep me'])
        ->assertOk();

    $message = Message::query()->first();

    $this->travel(11)->minutes();

    $this->actingAs($alice)
        ->getJson(route('conversations.messages', $conversation))
        ->assertOk()
        ->assertJsonPath('messages.0.can_delete', false)
        ->assertJsonPath('messages.0.can_edit', true);

    $this->actingAs($alice)
        ->delete(route('conversations.messages.destroy', [$conversation, $message]))
        ->assertForbidden();

    expect($message?->fresh())->not->toBeNull();
});

test('users cannot delete someone elses message', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => ConnectionStatus::Accepted,
    ]);

    $this->actingAs($alice)->get(route('messages.with', $bob));
    $conversation = Conversation::between($alice->id, $bob->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Secret'])
        ->assertOk();

    $message = Message::query()->first();

    $this->actingAs($bob)
        ->delete(route('conversations.messages.destroy', [$conversation, $message]))
        ->assertForbidden();
});

test('users can list recent chats for the navbar', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();

    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => ConnectionStatus::Accepted,
    ]);
    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $carol->id,
        'status' => ConnectionStatus::Accepted,
    ]);

    $this->actingAs($alice)->get(route('messages.with', $bob));
    $this->actingAs($alice)->get(route('messages.with', $carol));
    $bobChat = Conversation::between($alice->id, $bob->id);
    $carolChat = Conversation::between($alice->id, $carol->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $bobChat), ['body' => 'Hi Bob']);

    $this->travel(1)->minute();

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $carolChat), ['body' => 'Hi Carol']);

    $this->actingAs($alice)
        ->getJson(route('conversations.index'))
        ->assertOk()
        ->assertJsonCount(2, 'conversations')
        ->assertJsonPath('conversations.0.id', $carolChat?->public_id)
        ->assertJsonPath('conversations.0.last_message_preview', 'Hi Carol')
        ->assertJsonPath('conversations.1.id', $bobChat?->public_id);
});

test('chat and message public ids are used instead of numeric ids', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Connection::query()->create([
        'requester_id' => $alice->id,
        'addressee_id' => $bob->id,
        'status' => ConnectionStatus::Accepted,
    ]);

    $this->actingAs($alice)->get(route('messages.with', $bob))->assertRedirect();
    $conversation = Conversation::between($alice->id, $bob->id);

    $this->actingAs($alice)
        ->post(route('conversations.messages.store', $conversation), ['body' => 'Hello'])
        ->assertOk();

    $message = Message::query()->first();

    expect($conversation?->public_id)->toMatch('/^[A-Za-z0-9]{8}$/')
        ->and($conversation?->public_id)->not->toBe((string) $conversation?->getKey())
        ->and($message?->public_id)->toMatch('/^[A-Za-z0-9]{8}$/')
        ->and($message?->public_id)->not->toBe((string) $message?->getKey());

    $this->actingAs($alice)
        ->getJson(route('conversations.messages', $conversation))
        ->assertOk()
        ->assertJsonPath('messages.0.id', $message?->public_id)
        ->assertJsonPath('conversation.id', $conversation?->public_id);

    $this->actingAs($alice)
        ->get('/messages/'.$conversation?->getKey())
        ->assertNotFound();
});
