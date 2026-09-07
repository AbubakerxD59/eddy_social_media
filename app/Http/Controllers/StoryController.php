<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Http\Requests\StoreStoryRequest;
use App\Models\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

class StoryController extends Controller
{
    public function store(StoreStoryRequest $request): RedirectResponse
    {
        /** @var UploadedFile $file */
        $file = $request->file('media');
        $mime = (string) $file->getMimeType();
        $kind = str_starts_with($mime, 'video/') ? MediaType::Video : MediaType::Image;

        Story::query()->create([
            'user_id' => $request->user()->id,
            'kind' => $kind,
            'path' => $file->store('stories/'.$request->user()->id, 'public'),
            'mime_type' => $file->getMimeType(),
            'caption' => $request->validated('caption'),
            'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Your story is live for 24 hours.'),
        ]);

        return back();
    }

    public function destroy(Story $story): RedirectResponse
    {
        abort_unless($story->user_id === auth()->id(), 403);

        $story->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Story removed.'),
        ]);

        return back();
    }
}
