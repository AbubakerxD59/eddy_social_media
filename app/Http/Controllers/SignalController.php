<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Enums\SignalType;
use App\Http\Requests\StoreSignalRequest;
use App\Models\Signal;
use App\Models\SignalMedia;
use App\Services\LinkPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SignalController extends Controller
{
    public function show(Signal $signal): Response
    {
        $signal = FeedController::feedQuery()
            ->whereKey($signal->getKey())
            ->firstOrFail();

        $replies = FeedController::excludeMutedAuthors(FeedController::feedQuery())
            ->where('parent_id', $signal->getKey())
            ->latest()
            ->get()
            ->map(fn (Signal $reply) => FeedController::present($reply));

        return Inertia::render('Signals/Show', [
            'signal' => FeedController::present($signal),
            'replies' => $replies,
        ]);
    }

    public function store(StoreSignalRequest $request, LinkPreviewService $previews): RedirectResponse
    {
        $type = SignalType::from($request->validated('type'));
        $parent = $this->parentFromRequest($request);

        $signal = DB::transaction(function () use ($request, $type, $previews, $parent) {
            $link = $this->linkPayload($request, $type, $previews);

            $signal = Signal::query()->create([
                'user_id' => $request->user()->id,
                'parent_id' => $parent?->getKey(),
                'type' => $type,
                'body' => $request->validated('body'),
                ...$link,
            ]);

            $this->storeMedia($signal, $type, $request->file('media', []));

            return $signal;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $parent ? __('Reply is live.') : __('Your signal is live.'),
        ]);

        if ($parent) {
            return to_route('signals.show', $parent);
        }

        return to_route('dashboard', ['highlight' => $signal->public_id]);
    }

    public function like(Signal $signal): JsonResponse
    {
        $like = $signal->likes()->where('user_id', auth()->id())->first();

        if ($like) {
            $like->delete();
            $liked = false;
        } else {
            $signal->likes()->create([
                'user_id' => auth()->id(),
            ]);
            $liked = true;
        }

        return response()->json([
            'id' => $signal->public_id,
            'liked' => $liked,
            'likes_count' => $signal->likes()->count(),
        ]);
    }

    public function save(Signal $signal): JsonResponse
    {
        $save = $signal->saves()->where('user_id', auth()->id())->first();

        if ($save) {
            $save->delete();
            $saved = false;
        } else {
            $signal->saves()->create([
                'user_id' => auth()->id(),
            ]);
            $saved = true;
        }

        return response()->json([
            'id' => $signal->public_id,
            'saved' => $saved,
        ]);
    }

    public function report(Signal $signal): JsonResponse
    {
        abort_if($signal->user_id === auth()->id(), 403);

        $signal->reports()->firstOrCreate([
            'user_id' => auth()->id(),
        ]);

        return response()->json([
            'id' => $signal->public_id,
            'reported' => true,
        ]);
    }

    public function destroy(Signal $signal): RedirectResponse
    {
        abort_unless($signal->user_id === auth()->id(), 403);

        $signal->load('media');

        foreach ($signal->media as $media) {
            Storage::disk('public')->delete($media->path);
        }

        $signal->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Signal removed.')]);

        return back();
    }

    private function parentFromRequest(StoreSignalRequest $request): ?Signal
    {
        $parentId = $request->validated('parent_id');

        if (! is_string($parentId) || $parentId === '') {
            return null;
        }

        return Signal::query()->where('public_id', $parentId)->first();
    }

    /**
     * @return array{link_url: string|null, link_title: string|null, link_description: string|null, link_image: string|null}
     */
    private function linkPayload(StoreSignalRequest $request, SignalType $type, LinkPreviewService $previews): array
    {
        if ($type !== SignalType::Link) {
            return [
                'link_url' => null,
                'link_title' => null,
                'link_description' => null,
                'link_image' => null,
            ];
        }

        $url = $previews->normalizeUrl((string) $request->validated('link_url'));
        $preview = $previews->fetch($url);

        return [
            'link_url' => $url,
            'link_title' => $request->validated('link_title') ?: $preview['title'],
            'link_description' => $request->validated('link_description') ?: $preview['description'],
            'link_image' => $request->validated('link_image') ?: $preview['image'],
        ];
    }

    /**
     * @param  array<int, UploadedFile>|UploadedFile|null  $files
     */
    private function storeMedia(Signal $signal, SignalType $type, array|UploadedFile|null $files): void
    {
        if (! in_array($type, [SignalType::Images, SignalType::Video], true)) {
            return;
        }

        $files = is_array($files) ? $files : ($files ? [$files] : []);

        foreach (array_values($files) as $index => $file) {
            $path = $file->store('signals/'.$signal->public_id, 'public');

            SignalMedia::query()->create([
                'signal_id' => $signal->getKey(),
                'kind' => $type === SignalType::Video ? MediaType::Video : MediaType::Image,
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'position' => $index,
            ]);
        }
    }
}
