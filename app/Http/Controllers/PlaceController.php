<?php

namespace App\Http\Controllers;

use App\Services\GooglePlacesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceController extends Controller
{
    public function autocomplete(Request $request, GooglePlacesService $places): JsonResponse
    {
        $validated = $request->validate([
            'input' => ['required', 'string', 'min:2', 'max:120'],
            'session_token' => ['nullable', 'uuid'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'scope' => ['nullable', 'in:places,regions'],
        ]);

        $origin = isset($validated['latitude'], $validated['longitude'])
            ? [(float) $validated['latitude'], (float) $validated['longitude']]
            : null;

        return response()->json([
            'suggestions' => $places->autocomplete(
                $validated['input'],
                $validated['session_token'] ?? null,
                $origin,
            ),
        ]);
    }

    public function show(Request $request, string $place, GooglePlacesService $places): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => ['nullable', 'uuid'],
        ]);

        $details = $places->details($place, $validated['session_token'] ?? null);

        abort_if($details === null, 404);

        return response()->json($details);
    }
}
