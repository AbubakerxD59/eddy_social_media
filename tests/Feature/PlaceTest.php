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
            'suggestions' => [
                [
                    'placePrediction' => [
                        'placeId' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
                        'text' => ['text' => 'Orlando, FL, USA'],
                        'structuredFormat' => [
                            'mainText' => ['text' => 'Orlando'],
                            'secondaryText' => ['text' => 'FL, USA'],
                        ],
                    ],
                ],
            ],
        ]);
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('places.autocomplete'), [
            'input' => 'Orlando',
        ])
        ->assertOk()
        ->assertJsonPath('suggestions.0.place_id', 'ChIJN1t_tDeuEmsRUsoyG83frY4')
        ->assertJsonPath('suggestions.0.label', 'Orlando')
        ->assertJsonPath('suggestions.0.description', 'FL, USA');
});

test('region autocomplete searches google without a local bias', function () {
    config(['services.google.places_key' => 'test-key']);

    Http::fake(fn () => Http::response(['suggestions' => []]));

    $user = User::factory()->create([
        'latitude' => 28.54,
        'longitude' => -81.38,
    ]);

    $this->actingAs($user)
        ->postJson(route('places.autocomplete'), [
            'input' => 'London',
            'scope' => 'regions',
        ])
        ->assertOk();

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), 'places:autocomplete')) {
            return false;
        }

        $body = $request->data();

        return ($body['input'] ?? null) === 'London'
            && ($body['includedPrimaryTypes'] ?? null) === [
                'locality',
                'administrative_area_level_1',
                'administrative_area_level_2',
                'country',
                'neighborhood',
            ]
            && ! isset($body['locationBias']);
    });
});

test('authenticated users can resolve a google place', function () {
    config(['services.google.places_key' => 'test-key']);

    Http::fake(function () {
        return Http::response([
            'id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4',
            'formattedAddress' => 'Orlando, FL, USA',
            'location' => [
                'latitude' => 28.5383355,
                'longitude' => -81.3792365,
            ],
        ]);
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('places.show', 'ChIJN1t_tDeuEmsRUsoyG83frY4'))
        ->assertOk()
        ->assertJsonPath('place_id', 'ChIJN1t_tDeuEmsRUsoyG83frY4')
        ->assertJsonPath('label', 'Orlando, FL, USA')
        ->assertJsonPath('latitude', 28.5383355)
        ->assertJsonPath('longitude', -81.3792365);
});

test('place details return not found when google has no result', function () {
    config(['services.google.places_key' => 'test-key']);

    Http::fake(fn () => Http::response(['error' => 'not found'], 404));

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('places.show', 'missing-place'))
        ->assertNotFound();
});

test('the places service strips the places prefix from ids', function () {
    expect(app(GooglePlacesService::class)->normalizePlaceId('places/ChIJ123'))->toBe('ChIJ123');
});
