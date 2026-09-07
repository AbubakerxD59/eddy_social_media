<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Story>
 */
class StoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => MediaType::Image,
            'path' => 'stories/example.jpg',
            'mime_type' => 'image/jpeg',
            'caption' => fake()->optional()->sentence(),
            'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'created_at' => now()->subHours(Story::LIFETIME_HOURS + 1),
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'kind' => MediaType::Video,
            'path' => 'stories/example.mp4',
            'mime_type' => 'video/mp4',
        ]);
    }
}
