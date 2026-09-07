<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class GooglePlacesService
{
    private const AUTOCOMPLETE_URL = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';

    private const DETAILS_URL = 'https://maps.googleapis.com/maps/api/place/details/json';

    /**
     * Same mix as the working Places Autocomplete integration: businesses and addresses.
     */
    public const SEARCH_TYPES = 'establishment|geocode';

    /**
     * Nearby bias in meters. Legacy Autocomplete treats this as a bias, not a hard fence.
     */
    private const LOCATION_BIAS_RADIUS_METERS = 100_000;

    /**
     * @param  array{0: float, 1: float}|null  $origin
     * @param  list<string>|string|null  $includedPrimaryTypes
     * @return list<array{place_id: string, label: string, description: string|null}>
     */
    public function autocomplete(string $input, ?string $sessionToken = null, ?array $origin = null, array|string|null $includedPrimaryTypes = null): array
    {
        $key = $this->apiKey();
        $input = trim($input);

        if ($key === null || mb_strlen($input) < 2) {
            return [];
        }

        $query = array_filter([
            'input' => $input,
            'key' => $key,
            'language' => 'en',
            'types' => $this->autocompleteTypes($includedPrimaryTypes),
            'sessiontoken' => $sessionToken,
        ], fn ($value) => $value !== null && $value !== '');

        if ($origin !== null) {
            $query['location'] = $origin[0].','.$origin[1];
            $query['radius'] = self::LOCATION_BIAS_RADIUS_METERS;
        }

        try {
            $response = Http::timeout(6)
                ->connectTimeout(4)
                ->get(self::AUTOCOMPLETE_URL, $query);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful() || $response->json('status') !== 'OK') {
            return [];
        }

        $suggestions = [];

        foreach ($response->json('predictions', []) as $prediction) {
            if (! is_array($prediction)) {
                continue;
            }

            $placeId = $this->normalizePlaceId((string) ($prediction['place_id'] ?? ''));

            if ($placeId === '') {
                continue;
            }

            $structured = is_array($prediction['structured_formatting'] ?? null)
                ? $prediction['structured_formatting']
                : [];
            $main = $structured['main_text'] ?? null;
            $secondary = $structured['secondary_text'] ?? null;
            $description = $prediction['description'] ?? null;

            $label = is_string($main) && $main !== ''
                ? $main
                : (is_string($description) && $description !== '' ? $description : $placeId);

            $suggestions[] = [
                'place_id' => $placeId,
                'label' => $label,
                'description' => is_string($secondary) && $secondary !== '' ? $secondary : null,
            ];
        }

        return $suggestions;
    }

    /**
     * @return array{place_id: string, label: string, latitude: float, longitude: float}|null
     */
    public function details(string $placeId, ?string $sessionToken = null): ?array
    {
        $key = $this->apiKey();
        $placeId = $this->normalizePlaceId($placeId);

        if ($key === null || $placeId === '') {
            return null;
        }

        try {
            $response = Http::timeout(6)
                ->connectTimeout(4)
                ->get(self::DETAILS_URL, array_filter([
                    'place_id' => $placeId,
                    'key' => $key,
                    'language' => 'en',
                    'fields' => 'place_id,name,formatted_address,geometry',
                    'sessiontoken' => $sessionToken,
                ], fn ($value) => $value !== null && $value !== ''));
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || $response->json('status') !== 'OK') {
            return null;
        }

        $result = $response->json('result');

        if (! is_array($result)) {
            return null;
        }

        $location = is_array($result['geometry'] ?? null)
            ? ($result['geometry']['location'] ?? null)
            : null;
        $latitude = is_array($location) ? ($location['lat'] ?? null) : null;
        $longitude = is_array($location) ? ($location['lng'] ?? null) : null;

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $formatted = $result['formatted_address'] ?? null;
        $name = $result['name'] ?? null;
        $label = is_string($formatted) && $formatted !== ''
            ? $formatted
            : (is_string($name) && $name !== '' ? $name : $placeId);

        return [
            'place_id' => (string) ($result['place_id'] ?? $placeId),
            'label' => $label,
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];
    }

    public function normalizePlaceId(string $placeId): string
    {
        $placeId = trim($placeId);

        if (str_starts_with($placeId, 'places/')) {
            $placeId = substr($placeId, 7);
        }

        return $placeId;
    }

    /**
     * @param  list<string>|string|null  $types
     */
    private function autocompleteTypes(array|string|null $types): string
    {
        if (is_string($types) && $types !== '') {
            return $types;
        }

        if (is_array($types) && $types !== []) {
            return implode('|', $types);
        }

        return self::SEARCH_TYPES;
    }

    private function apiKey(): ?string
    {
        $key = config('services.google.places_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
