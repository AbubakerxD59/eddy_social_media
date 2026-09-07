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
                'body' => 'Eddy is live for drops, needs, opportunities, and polls.',
            ],
            [
                'public_id' => Signal::generatePublicId(),
                'type' => SignalType::Drop,
            ],
        );

        Signal::query()->firstOrCreate(
            [
                'user_id' => $ada->id,
                'title' => 'Need a video editor for ongoing projects',
            ],
            [
                'public_id' => Signal::generatePublicId(),
                'type' => SignalType::Need,
                'body' => 'Looking for someone who can cut weekly product clips.',
                'payload' => [
                    'budget' => '$500 - $1,000 / project',
                    'timeline' => '2 weeks',
                    'location' => 'Remote',
                    'skills' => ['Premiere Pro', 'After Effects'],
                ],
            ],
        );

        Signal::query()->firstOrCreate(
            [
                'user_id' => $ada->id,
                'title' => 'Commercial construction project',
            ],
            [
                'public_id' => Signal::generatePublicId(),
                'type' => SignalType::Opportunity,
                'body' => 'Seeking trade partners for a mid-size commercial build.',
                'payload' => [
                    'project_value' => '$250K - $500K',
                    'timeline' => '3 months',
                    'location' => 'Orlando, FL',
                    'trades' => ['Electrical', 'HVAC', 'Plumbing'],
                ],
                'latitude' => 28.5383355,
                'longitude' => -81.3792365,
            ],
        );

        Signal::query()->firstOrCreate(
            [
                'user_id' => $ada->id,
                'body' => 'Which channel should we double down on next quarter?',
            ],
            [
                'public_id' => Signal::generatePublicId(),
                'type' => SignalType::Poll,
                'payload' => [
                    'options' => [
                        ['id' => '1', 'text' => 'LinkedIn'],
                        ['id' => '2', 'text' => 'Short-form video'],
                        ['id' => '3', 'text' => 'Email'],
                    ],
                ],
            ],
        );

        Signal::query()->firstOrCreate(
            [
                'user_id' => $ada->id,
                'body' => 'Start here if you are new to Laravel.',
            ],
            [
                'public_id' => Signal::generatePublicId(),
                'type' => SignalType::Drop,
                'link_url' => 'https://laravel.com',
                'link_title' => 'Laravel',
                'link_description' => 'The PHP framework for web artisans.',
            ],
        );
    }
}
