<?php

use App\Models\User;
use App\Services\LinkPreviewService;

test('guests cannot request a link preview', function () {
    $this->postJson(route('link-preview.store'), [
        'url' => 'https://laravel.com',
    ])->assertUnauthorized();
});

test('local addresses are not fetched', function () {
    $preview = app(LinkPreviewService::class)->fetch('http://127.0.0.1');

    expect($preview['title'])->toBeNull()
        ->and($preview['image'])->toBeNull();
});

test('authenticated users can request a preview payload', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('link-preview.store'), [
            'url' => 'http://localhost',
        ])
        ->assertOk()
        ->assertJsonPath('title', null);
});
