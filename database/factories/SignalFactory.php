<?php

namespace Database\Factories;

use App\Enums\SignalType;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signal>
 */
class SignalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => SignalType::Quote,
            'body' => fake()->paragraph(),
            'link_url' => null,
            'link_title' => null,
            'link_description' => null,
            'link_image' => null,
        ];
    }

    public function link(string $url = 'https://laravel.com'): static
    {
        return $this->state(fn (): array => [
            'type' => SignalType::Link,
            'body' => fake()->optional()->sentence(),
            'link_url' => $url,
            'link_title' => 'Laravel',
            'link_description' => 'The PHP framework for web artisans.',
            'link_image' => null,
        ]);
    }

    public function images(): static
    {
        return $this->state(fn (): array => [
            'type' => SignalType::Images,
            'body' => fake()->optional()->sentence(),
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'type' => SignalType::Video,
            'body' => fake()->optional()->sentence(),
        ]);
    }
}
