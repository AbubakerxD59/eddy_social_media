<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Http\Requests\StoreSignalUploadRequest;
use App\Models\SignalUpload;
use Illuminate\Http\JsonResponse;

class SignalUploadController extends Controller
{
    public function store(StoreSignalUploadRequest $request): JsonResponse
    {
        $file = $request->file('media');
        $mime = (string) $file->getMimeType();
        $path = $file->store('uploads/'.$request->user()->id, 'public');

        $upload = SignalUpload::query()->create([
            'user_id' => $request->user()->id,
            'kind' => str_starts_with($mime, 'video/') ? MediaType::Video : MediaType::Image,
            'path' => $path,
            'mime_type' => $file->getMimeType(),
        ]);

        return response()->json([
            'id' => $upload->public_id,
            'kind' => $upload->kind->value,
            'url' => $upload->url,
            'mime_type' => $upload->mime_type,
        ]);
    }

    public function destroy(SignalUpload $upload): JsonResponse
    {
        abort_unless($upload->user_id === auth()->id(), 403);

        $upload->delete();

        return response()->json(['ok' => true]);
    }
}
