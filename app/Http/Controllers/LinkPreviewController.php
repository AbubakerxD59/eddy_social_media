<?php

namespace App\Http\Controllers;

use App\Services\LinkPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkPreviewController extends Controller
{
    public function __invoke(Request $request, LinkPreviewService $previews): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        return response()->json($previews->fetch($validated['url']));
    }
}
