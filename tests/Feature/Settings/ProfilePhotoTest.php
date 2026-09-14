<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('users can upload a profile picture and cover photo', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('profile.photo'), [
            'kind' => 'avatar',
            'photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertOk()
        ->assertJsonPath('kind', 'avatar')
        ->assertJsonPath('message', 'Profile picture updated.');

    $this->actingAs($user)
        ->postJson(route('profile.photo'), [
            'kind' => 'cover',
            'photo' => UploadedFile::fake()->image('cover.jpg', 1200, 400),
        ])
        ->assertOk()
        ->assertJsonPath('kind', 'cover');

    $user->refresh();

    expect($user->avatar_path)->not->toBeNull()
        ->and($user->cover_path)->not->toBeNull()
        ->and($user->cover_url)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('profiles.show', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('profile.cover', $user->cover_url)
            ->where('profile.avatar', $user->avatar_url));
});
