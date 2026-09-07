<?php

namespace App\Http\Controllers;

use App\Enums\MediaType;
use App\Enums\SignalType;
use App\Http\Requests\StoreSignalRequest;
use App\Http\Requests\UpdateSignalRequest;
use App\Http\Requests\VotePollRequest;
use App\Models\Signal;
use App\Models\SignalMedia;
use App\Models\SignalUpload;
use App\Services\GooglePlacesService;
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

        return Inertia::render('Signals/Show', [
            'signal' => FeedController::present($signal),
            'replies' => Inertia::defer(function () use ($signal) {
                return FeedController::excludeMutedAuthors(FeedController::feedQuery())
                    ->where('parent_id', $signal->getKey())
                    ->latest()
                    ->get()
                    ->map(fn (Signal $reply) => FeedController::present($reply))
                    ->values()
                    ->all();
            }, 'replies'),
        ]);
    }

    public function store(StoreSignalRequest $request, LinkPreviewService $previews, GooglePlacesService $places): RedirectResponse
    {
        $type = SignalType::from($request->validated('type'));
        $parent = $this->parentFromRequest($request);

        $signal = DB::transaction(function () use ($request, $type, $previews, $places, $parent) {
            $link = $this->linkPayload($request, $type, $previews);
            $place = $this->placePayload($request, $type, $places);

            $signal = Signal::query()->create([
                'user_id' => $request->user()->id,
                'parent_id' => $parent?->getKey(),
                'type' => $type,
                'title' => $this->titleFor($request, $type),
                'body' => $request->validated('body'),
                'payload' => $this->payloadFor($request, $type, $place['location']),
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
                'place_id' => $place['place_id'],
                ...$link,
            ]);

            $this->storeMedia($signal, $type, $request->file('media', []));
            $this->attachUploads($signal, $type, $request->validated('media_ids', []));

            return $signal;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $parent ? __('Reply is live.') : __($type->liveMessage()),
        ]);

        if ($parent) {
            return to_route('signals.show', $parent);
        }

        return to_route('dashboard', ['highlight' => $signal->public_id]);
    }

    public function update(UpdateSignalRequest $request, Signal $signal, LinkPreviewService $previews, GooglePlacesService $places): RedirectResponse
    {
        abort_unless($signal->user_id === auth()->id(), 403);

        $type = $signal->type;

        DB::transaction(function () use ($request, $signal, $type, $previews, $places): void {
            $link = $this->linkPayload($request, $type, $previews);
            $place = $this->placePayload($request, $type, $places);

            $signal->update([
                'title' => $this->titleFor($request, $type),
                'body' => $request->validated('body'),
                'payload' => $this->payloadFor($request, $type, $place['location'], $signal),
                'latitude' => $place['latitude'],
                'longitude' => $place['longitude'],
                'place_id' => $place['place_id'],
                ...$link,
            ]);

            $this->storeMedia($signal, $type, $request->file('media', []));
            $this->attachUploads($signal, $type, $request->validated('media_ids', []));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __($type->updatedMessage()),
        ]);

        return back();
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

    public function vote(VotePollRequest $request, Signal $signal): JsonResponse
    {
        abort_unless($signal->type === SignalType::Poll, 404);

        $optionId = $request->validated('option_id');
        $options = collect($signal->payload['options'] ?? []);

        abort_unless($options->contains(fn (mixed $option): bool => is_array($option) && ($option['id'] ?? null) === $optionId), 422);

        $signal->pollVotes()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['option_id' => $optionId],
        );

        $signal->load('pollVotes');

        return response()->json([
            'id' => $signal->public_id,
            'poll' => FeedController::presentPoll($signal),
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

    private function titleFor(StoreSignalRequest $request, SignalType $type): ?string
    {
        if (! in_array($type, [SignalType::Need, SignalType::Opportunity], true)) {
            return null;
        }

        $title = $request->validated('title');

        return is_string($title) && $title !== '' ? $title : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payloadFor(StoreSignalRequest $request, SignalType $type, ?string $location, ?Signal $existing = null): ?array
    {
        $payload = match ($type) {
            SignalType::Need => array_filter([
                'budget' => $request->validated('budget'),
                'timeline' => $request->validated('timeline'),
                'location' => $location,
                'skills' => $request->csvList('skills'),
            ]),
            SignalType::Opportunity => array_filter([
                'project_value' => $request->validated('project_value'),
                'timeline' => $request->validated('timeline'),
                'location' => $location,
                'trades' => $request->csvList('trades'),
            ]),
            SignalType::Poll => [
                'options' => $this->pollOptionsPayload($request, $existing),
            ],
            SignalType::Drop => [],
        };

        return $payload === [] ? null : $payload;
    }

    /**
     * @return list<array{id: string, text: string}>
     */
    private function pollOptionsPayload(StoreSignalRequest $request, ?Signal $existing): array
    {
        $existingOptions = is_array($existing?->payload['options'] ?? null)
            ? array_values($existing->payload['options'])
            : [];

        return array_map(
            function (string $text, int $index) use ($existingOptions): array {
                $previous = $existingOptions[$index] ?? null;
                $id = is_array($previous) && isset($previous['id'])
                    ? (string) $previous['id']
                    : (string) ($index + 1);

                return [
                    'id' => $id,
                    'text' => $text,
                ];
            },
            $request->pollOptions(),
            array_keys($request->pollOptions()),
        );
    }

    /**
     * @return array{location: string|null, latitude: float|null, longitude: float|null, place_id: string|null}
     */
    private function placePayload(StoreSignalRequest $request, SignalType $type, GooglePlacesService $places): array
    {
        $empty = [
            'location' => null,
            'latitude' => null,
            'longitude' => null,
            'place_id' => null,
        ];

        if (! in_array($type, [SignalType::Need, SignalType::Opportunity], true)) {
            return $empty;
        }

        $placeId = $request->validated('place_id');

        if (is_string($placeId) && $placeId !== '') {
            $details = $places->details($placeId);

            if ($details !== null) {
                return [
                    'location' => $details['label'],
                    'latitude' => $details['latitude'],
                    'longitude' => $details['longitude'],
                    'place_id' => $details['place_id'],
                ];
            }
        }

        $location = $request->validated('location');
        $latitude = $request->validated('latitude');
        $longitude = $request->validated('longitude');

        return [
            'location' => is_string($location) && $location !== '' ? $location : null,
            'latitude' => is_numeric($latitude) ? (float) $latitude : null,
            'longitude' => is_numeric($longitude) ? (float) $longitude : null,
            'place_id' => is_string($placeId) && $placeId !== '' ? $places->normalizePlaceId($placeId) : null,
        ];
    }

    /**
     * @return array{link_url: string|null, link_title: string|null, link_description: string|null, link_image: string|null}
     */
    private function linkPayload(StoreSignalRequest $request, SignalType $type, LinkPreviewService $previews): array
    {
        if (! $type->allowsLink() || blank($request->validated('link_url'))) {
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
        if (! $type->allowsMedia()) {
            return;
        }

        $files = is_array($files) ? $files : ($files ? [$files] : []);

        foreach (array_values($files) as $index => $file) {
            $path = $file->store('signals/'.$signal->public_id, 'public');
            $mime = (string) $file->getMimeType();

            SignalMedia::query()->create([
                'signal_id' => $signal->getKey(),
                'kind' => str_starts_with($mime, 'video/') ? MediaType::Video : MediaType::Image,
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'position' => $index,
            ]);
        }
    }

    /**
     * @param  array<int, mixed>|null  $ids
     */
    private function attachUploads(Signal $signal, SignalType $type, mixed $ids): void
    {
        if (! $type->allowsMedia() || ! is_array($ids) || $ids === []) {
            return;
        }

        $ids = array_values(array_filter(
            array_map(fn (mixed $id): string => is_string($id) ? $id : '', $ids),
        ));

        $uploads = SignalUpload::query()
            ->where('user_id', $signal->user_id)
            ->whereIn('public_id', $ids)
            ->get()
            ->sortBy(fn (SignalUpload $upload): int => (int) array_search($upload->public_id, $ids, true))
            ->values();

        $position = $signal->media()->count();

        foreach ($uploads as $upload) {
            $filename = basename($upload->path);
            $destination = 'signals/'.$signal->public_id.'/'.$filename;

            Storage::disk('public')->move($upload->path, $destination);

            SignalMedia::query()->create([
                'signal_id' => $signal->getKey(),
                'kind' => $upload->kind,
                'path' => $destination,
                'mime_type' => $upload->mime_type,
                'position' => $position,
            ]);

            $position++;
            $upload->delete();
        }
    }
}
