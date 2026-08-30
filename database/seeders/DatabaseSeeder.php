<?php

namespace Database\Seeders;

use App\Enums\SignalType;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $ada = User::query()->updateOrCreate(
            ['email' => 'ada@eddy.test'],
            [
                'name' => 'Eddy Labs',
                'full_name' => 'Ada',
                'last_name' => 'Founder',
                'business_name' => 'Eddy Labs',
                'username' => 'ada',
                'headline' => 'Building Eddy for operators',
                'bio' => 'A quieter social network for founders.',
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test Co',
                'full_name' => 'Test',
                'last_name' => 'User',
                'business_name' => 'Test Co',
                'username' => 'testuser',
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        Signal::query()->firstOrCreate(
            [
                'user_id' => $ada->id,
                'body' => 'Eddy is live for quotes, photos, video, and link signals.',
            ],
            [
                'public_id' => Signal::generatePublicId(),
                'type' => SignalType::Quote,
            ],
        );

        Signal::query()->firstOrCreate(
            [
                'user_id' => $ada->id,
                'body' => 'Start here if you are new to Laravel.',
            ],
            [
                'public_id' => Signal::generatePublicId(),
                'type' => SignalType::Link,
                'link_url' => 'https://laravel.com',
                'link_title' => 'Laravel',
                'link_description' => 'The PHP framework for web artisans.',
            ],
        );
    }
}
