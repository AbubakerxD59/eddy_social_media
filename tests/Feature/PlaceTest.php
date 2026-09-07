<?php

use App\Models\User;
use App\Services\GooglePlacesService;
use Illuminate\Support\Facades\Http;

test('guests cannot autocomplete places', function () {
    $this->postJson(route('places.autocomplete'), [
        'input' => 'Orlando',
    ])->assertUnauthorized();
});

test('guests cannot resolve a place', function () {
    $this->getJson(route('places.show', 'ChIJ123'))->assertUnauthorized();
});

test('autocomplete is empty when google is not configured', function () {
    config(['services.google.places_key' => null]);
    Http::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('places.autocomplete'), [
            'input' => 'Orlando',
        ])
        ->assertOk()
        ->assertJson(['suggestions' => []]);

    Http::assertNothingSent();
});

test('authenticated users receive google place suggestions', function () {
    config(['services.google.places_key' => 'test-key']);

    Http::fake(function () {
        return Http::response([
            'status' => 'OK',
            'predictions' => [
                [
                    'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
                    'description' => 'Giga Mall, Islamabad, Pakistan',
                    'structured_formatting' => [
                        'main_text' => 'Giga Mall',
                        'secondary_text' => 'Islamabad, Pakistan',
                    ],
                ],
            ],
        ]);
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('places.autocomplete'), [
            'input' => 'Giga mall',
        ])
        ->assertOk()
        ->assertJsonPath('suggestions.0.place_id', 'ChIJN1t_tDeuEmsRUsoyG83frY4')
        ->assertJsonPath('suggestions.0.label', 'Giga Mall')
        ->assertJsonPath('suggestions.0.description', 'Islamabad, Pakistan');
});

test('autocomplete uses establishment and geocode types', function () {
    config(['services.google.places_key' => 'test-key']);

    Http::fake(fn () => Http::response(['status' => 'ZERO_RESULTS', 'predictions' => []]));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('places.autocomplete'), [
            'input' => 'Giga mall',
            'scope' => 'regions',
        ])
        ->assertOk();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'maps.googleapis.com/maps/api/place/autocomplete/json')) {
            return false;
        }

        $query = $request->data();

        return ($query['input'] ?? null) === 'Giga mall'
            && ($query['types'] ?? null) === 'establishment|geocode'
            && ($query['key'] ?? null) === 'test-key'
            && ! isset($query['location']);
    });
});

test('nearby autocomplete biases results within 100 km', function () {
    config(['services.google.places_key' => 'test-key']);

    Http::fake(fn () => Http::response(['status' => 'ZERO_RESULTS', 'predictions' => []]));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('places.autocomplete'), [
            'input' => 'Giga mall',
            'latitude' => 33.69,
            'longitude' => 73.02,
        ])
        ->assertOk();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'maps.googleapis.com/maps/api/place/autocomplete/json')) {
            return false;
        }

        $query = $request->data();

        return ($query['input'] ?? null) === 'Giga mall'
            && ($query['types'] ?? null) === 'establishment|geocode'
            && ($query['location'] ?? null) === '33.69,73.02'
            && (int) ($query['radius'] ?? 0) === 100_000;
    });
});

test('authenticated users can resolve a google place', function () {
    config(['services.google.places_key' => 'test-key']);

    Http::fake(function () {
        return Http::response([
            'status' => 'OK',
            'result' => [
                'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
                'name' => 'Giga Mall',
                'formatted_address' => 'Giga Mall, Islamabad, Pakistan',
                'geometry' => [
                    'location' => [
                        'lat' => 33.6928,
                        'lng' => 73.0213,
                    ],
                ],
            ],
        ]);
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('places.show', 'ChIJN1t_tDeuEmsRUsoyG83frY4'))
        ->assertOk()
        ->assertJsonPath('place_id', 'ChIJN1t_tDeuEmsRUsoyG83frY4')
        ->assertJsonPath('label', 'Giga Mall, Islamabad, Pakistan')
        ->assertJsonPath('latitude', 33.6928)
        ->assertJsonPath('longitude', 73.0213);
});

test('place details return not found when google has no result', function () {
    config(['services.google.places_key' => 'test-key']);

    Http::fake(fn () => Http::response(['status' => 'NOT_FOUND', 'error_message' => 'not found']));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('places.show', 'missing-place'))
        ->assertNotFound();
});

test('the places service strips the places prefix from ids', function () {
    expect(app(GooglePlacesService::class)->normalizePlaceId('places/ChIJ123'))->toBe('ChIJ123');
});
