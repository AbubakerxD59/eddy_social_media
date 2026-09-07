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
            'type' => SignalType::Drop,
            'title' => null,
            'body' => fake()->paragraph(),
            'payload' => null,
            'latitude' => null,
            'longitude' => null,
            'place_id' => null,
            'link_url' => null,
            'link_title' => null,
            'link_description' => null,
            'link_image' => null,
        ];
    }

    public function drop(): static
    {
        return $this->state(fn (): array => [
            'type' => SignalType::Drop,
            'title' => null,
            'payload' => null,
        ]);
    }

    public function need(): static
    {
        return $this->state(fn (): array => [
            'type' => SignalType::Need,
            'title' => 'Need a video editor for ongoing projects',
            'body' => fake()->sentence(),
            'payload' => [
                'budget' => '$500 - $1,000 / project',
                'timeline' => '2 weeks',
                'location' => 'Remote',
                'skills' => ['Premiere Pro', 'After Effects'],
            ],
        ]);
    }

    public function opportunity(): static
    {
        return $this->state(fn (): array => [
            'type' => SignalType::Opportunity,
            'title' => 'Commercial construction project',
            'body' => fake()->sentence(),
            'payload' => [
                'project_value' => '$250K - $500K',
                'timeline' => '3 months',
                'location' => 'Orlando, FL',
                'trades' => ['Electrical', 'HVAC', 'Plumbing'],
            ],
            'latitude' => 28.5383355,
            'longitude' => -81.3792365,
        ]);
    }

    public function poll(): static
    {
        return $this->state(fn (): array => [
            'type' => SignalType::Poll,
            'title' => null,
            'body' => 'Which channel should we double down on next quarter?',
            'payload' => [
                'options' => [
                    ['id' => '1', 'text' => 'LinkedIn'],
                    ['id' => '2', 'text' => 'Short-form video'],
                    ['id' => '3', 'text' => 'Email'],
                ],
            ],
        ]);
    }

    public function at(float $latitude, float $longitude, string $location): static
    {
        return $this->state(fn (array $attributes): array => [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'payload' => [
                ...(is_array($attributes['payload'] ?? null) ? $attributes['payload'] : []),
                'location' => $location,
            ],
        ]);
    }

    public function link(string $url = 'https://laravel.com'): static
    {
        return $this->state(fn (): array => [
            'type' => SignalType::Drop,
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
            'type' => SignalType::Drop,
            'body' => fake()->optional()->sentence(),
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (): array => [
            'type' => SignalType::Drop,
            'body' => fake()->optional()->sentence(),
        ]);
    }
}
