<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class GooglePlacesService
{
    private const AUTOCOMPLETE_URL = 'https://places.googleapis.com/v1/places:autocomplete';

    /**
     * Autocomplete (New) city collection. Table B types like `locality` return no predictions.
     *
     * @var list<string>
     */
    public const REGION_TYPES = [
        '(cities)',
    ];

    /**
     * Nearby ranking uses a viewport, not a circle: Autocomplete (New) circles max out at 50 km.
     */
    private const LOCATION_BIAS_RADIUS_KM = 1000.0;

    private const KM_PER_DEGREE_LATITUDE = 111.32;

    /**
     * @param  array{0: float, 1: float}|null  $origin
     * @param  list<string>|null  $includedPrimaryTypes
     * @return list<array{place_id: string, label: string, description: string|null}>
     */
    public function autocomplete(string $input, ?string $sessionToken = null, ?array $origin = null, ?array $includedPrimaryTypes = null): array
    {
        $key = $this->apiKey();
        $input = trim($input);

        if ($key === null || mb_strlen($input) < 2) {
            return [];
        }

        $body = array_filter([
            'input' => $input,
            'languageCode' => 'en',
            'sessionToken' => $sessionToken,
            'includedPrimaryTypes' => $includedPrimaryTypes ?: null,
        ]);

        if ($origin !== null) {
            $body['locationBias'] = $this->locationBias($origin);
        }

        try {
            $response = Http::timeout(6)
                ->connectTimeout(4)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Goog-Api-Key' => $key,
                    'X-Goog-FieldMask' => 'suggestions.placePrediction.placeId,suggestions.placePrediction.place,suggestions.placePrediction.text,suggestions.placePrediction.structuredFormat',
                ])
                ->post(self::AUTOCOMPLETE_URL, $body);
        } catch (Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $suggestions = [];

        foreach ($response->json('suggestions', []) as $suggestion) {
            if (! is_array($suggestion)) {
                continue;
            }

            $prediction = $suggestion['placePrediction'] ?? null;

            if (! is_array($prediction)) {
                continue;
            }

            $placeId = $this->normalizePlaceId((string) ($prediction['placeId'] ?? $prediction['place'] ?? ''));

            if ($placeId === '') {
                continue;
            }

            $structured = is_array($prediction['structuredFormat'] ?? null)
                ? $prediction['structuredFormat']
                : [];
            $main = is_array($structured['mainText'] ?? null)
                ? ($structured['mainText']['text'] ?? null)
                : null;
            $secondary = is_array($structured['secondaryText'] ?? null)
                ? ($structured['secondaryText']['text'] ?? null)
                : null;
            $text = is_array($prediction['text'] ?? null)
                ? ($prediction['text']['text'] ?? null)
                : null;

            $label = is_string($main) && $main !== ''
                ? $main
                : (is_string($text) && $text !== '' ? $text : $placeId);

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
                ->withHeaders([
                    'X-Goog-Api-Key' => $key,
                    'X-Goog-FieldMask' => 'id,formattedAddress,displayName,location',
                ])
                ->get('https://places.googleapis.com/v1/places/'.$placeId, array_filter([
                    'languageCode' => 'en',
                    'sessionToken' => $sessionToken,
                ]));
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $location = $response->json('location');
        $latitude = is_array($location) ? ($location['latitude'] ?? null) : null;
        $longitude = is_array($location) ? ($location['longitude'] ?? null) : null;

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $displayName = $response->json('displayName');
        $formatted = $response->json('formattedAddress');
        $fallback = is_array($displayName) ? ($displayName['text'] ?? null) : null;
        $label = is_string($formatted) && $formatted !== ''
            ? $formatted
            : (is_string($fallback) && $fallback !== '' ? $fallback : $placeId);

        return [
            'place_id' => (string) ($response->json('id') ?: $placeId),
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
     * @param  array{0: float, 1: float}  $origin
     * @return array{rectangle: array{low: array{latitude: float, longitude: float}, high: array{latitude: float, longitude: float}}}
     */
    private function locationBias(array $origin): array
    {
        [$latitude, $longitude] = $origin;
        $latDelta = self::LOCATION_BIAS_RADIUS_KM / self::KM_PER_DEGREE_LATITUDE;
        $cosLatitude = cos(deg2rad($latitude));
        $lngDelta = abs($cosLatitude) < 0.01
            ? 180.0
            : min(180.0, self::LOCATION_BIAS_RADIUS_KM / (self::KM_PER_DEGREE_LATITUDE * abs($cosLatitude)));

        return [
            'rectangle' => [
                'low' => [
                    'latitude' => max(-90.0, $latitude - $latDelta),
                    'longitude' => $this->normalizeLongitude($longitude - $lngDelta),
                ],
                'high' => [
                    'latitude' => min(90.0, $latitude + $latDelta),
                    'longitude' => $this->normalizeLongitude($longitude + $lngDelta),
                ],
            ],
        ];
    }

    private function normalizeLongitude(float $longitude): float
    {
        $longitude = fmod($longitude + 180.0, 360.0);

        if ($longitude < 0) {
            $longitude += 360.0;
        }

        return $longitude - 180.0;
    }

    private function apiKey(): ?string
    {
        $key = config('services.google.places_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
