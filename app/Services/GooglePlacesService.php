<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class GooglePlacesService
{
    private const AUTOCOMPLETE_URL = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';

    private const DETAILS_URL = 'https://maps.googleapis.com/maps/api/place/details/json';

    private const GEOCODE_URL = 'https://maps.googleapis.com/maps/api/geocode/json';

    private const NEARBY_URL = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json';

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

    public function reverseGeocode(float $latitude, float $longitude): ?string
    {
        $key = $this->apiKey();

        if ($key === null) {
            return null;
        }

        return $this->reverseGeocodeFromGeocoding($latitude, $longitude)
            ?? $this->reverseGeocodeFromNearby($latitude, $longitude);
    }

    private function reverseGeocodeFromGeocoding(float $latitude, float $longitude): ?string
    {
        try {
            $response = Http::timeout(6)
                ->connectTimeout(4)
                ->get(self::GEOCODE_URL, [
                    'latlng' => $latitude.','.$longitude,
                    'key' => $this->apiKey(),
                    'language' => 'en',
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || $response->json('status') !== 'OK') {
            return null;
        }

        $results = $response->json('results', []);

        if (! is_array($results)) {
            return null;
        }

        $preferred = [];
        $fallback = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $label = $this->labelFromGeocodeResult($result);

            if ($label === null) {
                continue;
            }

            $types = is_array($result['types'] ?? null) ? $result['types'] : [];
            $isPlaceName = count(array_intersect($types, [
                'locality',
                'neighborhood',
                'sublocality',
                'sublocality_level_1',
                'administrative_area_level_1',
                'administrative_area_level_2',
                'political',
            ])) > 0;

            if ($isPlaceName) {
                $preferred[] = $label;
            } else {
                $fallback[] = $label;
            }
        }

        return $preferred[0] ?? $fallback[0] ?? null;
    }

    private function reverseGeocodeFromNearby(float $latitude, float $longitude): ?string
    {
        try {
            $response = Http::timeout(6)
                ->connectTimeout(4)
                ->get(self::NEARBY_URL, [
                    'location' => $latitude.','.$longitude,
                    'rankby' => 'distance',
                    'key' => $this->apiKey(),
                    'language' => 'en',
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful() || $response->json('status') !== 'OK') {
            return null;
        }

        $results = $response->json('results', []);

        if (! is_array($results)) {
            return null;
        }

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $vicinity = $result['vicinity'] ?? null;

            if (is_string($vicinity) && trim($vicinity) !== '') {
                return mb_substr(trim($vicinity), 0, 255);
            }

            $name = $result['name'] ?? null;

            if (is_string($name) && trim($name) !== '' && ! preg_match('/^-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?$/', trim($name))) {
                return mb_substr(trim($name), 0, 255);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function labelFromGeocodeResult(array $result): ?string
    {
        $fromComponents = $this->labelFromAddressComponents($result);

        if ($fromComponents !== null) {
            return $fromComponents;
        }

        $formatted = $result['formatted_address'] ?? null;

        if (! is_string($formatted)) {
            return null;
        }

        $formatted = trim($formatted);

        if ($formatted === '' || preg_match('/^-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?$/', $formatted)) {
            return null;
        }

        if (preg_match('/^[0-9A-Z]{2,}\+[0-9A-Z]+\s+(.+)$/i', $formatted, $matches)) {
            $formatted = trim($matches[1]);
        }

        return $formatted !== '' ? mb_substr($formatted, 0, 255) : null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function labelFromAddressComponents(array $result): ?string
    {
        $components = $result['address_components'] ?? [];

        if (! is_array($components) || $components === []) {
            return null;
        }

        $find = function (array $types) use ($components): ?array {
            foreach ($components as $component) {
                if (! is_array($component) || ! is_array($component['types'] ?? null)) {
                    continue;
                }

                if (count(array_intersect($types, $component['types'])) > 0) {
                    return $component;
                }
            }

            return null;
        };

        $neighborhood = $find(['neighborhood', 'sublocality', 'sublocality_level_1']);
        $locality = $find(['locality', 'postal_town']);
        $area = $find(['administrative_area_level_2']);
        $region = $find(['administrative_area_level_1']);
        $country = $find(['country']);

        $parts = [];
        $isUsableName = function (?string $name): bool {
            $name = trim((string) $name);

            return $name !== ''
                && ! preg_match('/^-?\d+(?:\.\d+)?$/', $name)
                && preg_match('/\p{L}/u', $name);
        };

        $localityName = is_array($locality) ? trim((string) ($locality['long_name'] ?? '')) : '';
        $neighborhoodName = is_array($neighborhood) ? trim((string) ($neighborhood['long_name'] ?? '')) : '';
        $areaName = is_array($area) ? trim((string) ($area['long_name'] ?? '')) : '';

        if ($isUsableName($localityName)) {
            $parts[] = $localityName;
        } elseif ($isUsableName($neighborhoodName)) {
            $parts[] = $neighborhoodName;
        } elseif ($isUsableName($areaName)) {
            $parts[] = $areaName;
        }

        if (is_array($region)) {
            $regionName = trim((string) ($region['short_name'] ?? $region['long_name'] ?? ''));

            if ($regionName !== '' && $isUsableName($regionName) && ! in_array($regionName, $parts, true)) {
                $parts[] = $regionName;
            }
        }

        if ($parts === [] && is_array($country)) {
            $countryName = trim((string) ($country['long_name'] ?? $country['short_name'] ?? ''));

            if ($countryName !== '') {
                $parts[] = $countryName;
            }
        }

        if ($parts === []) {
            return null;
        }

        return mb_substr(implode(', ', $parts), 0, 255);
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
