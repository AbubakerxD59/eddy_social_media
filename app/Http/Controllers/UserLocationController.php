<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserLocationRequest;
use Illuminate\Http\JsonResponse;

class UserLocationController extends Controller
{
    public function store(StoreUserLocationRequest $request): JsonResponse
    {
        $label = $request->validated('label');
        $origin = FeedController::persistViewerOrigin(
            $request,
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
            is_string($label) ? $label : null,
            $request->exists('manual') ? $request->boolean('manual') : null,
        );

        return response()->json($origin);
    }
}
