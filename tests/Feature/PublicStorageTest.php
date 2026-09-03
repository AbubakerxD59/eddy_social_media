<?php

use Illuminate\Support\Facades\Storage;

test('public disk files are served at the storage url', function () {
    Storage::fake('public');
    Storage::disk('public')->put('signals/abc/photo.jpg', 'fake-image');

    $response = $this->get('/storage/signals/abc/photo.jpg');

    $response->assertOk();
    expect($response->streamedContent())->toBe('fake-image');
});

test('missing public disk files return 404', function () {
    Storage::fake('public');

    $this->get('/storage/signals/missing.jpg')->assertNotFound();
});

test('public storage rejects path traversal', function () {
    Storage::fake('public');

    $this->get('/storage/../.env')->assertNotFound();
    $this->get('/storage/signals/../../.env')->assertNotFound();
});
