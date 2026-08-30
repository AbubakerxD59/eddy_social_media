<?php

namespace App\Http\Controllers;

use App\Models\Signal;
use App\Models\UserMute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $signals = self::excludeMutedAuthors(self::feedQuery())
            ->roots()
            ->latest()
            ->paginate(15)
            ->through(fn (Signal $signal) => self::present($signal));

        return Inertia::render('Feed', [
            'signals' => $signals,
            'highlight' => $request->string('highlight')->toString() ?: null,
        ]);
    }

    /**
     * @return Builder<Signal>
     */
    public static function feedQuery(): Builder
    {
        return Signal::query()
            ->with(['user', 'media'])
            ->withCount(['likes', 'replies'])
            ->withExists(['likes as liked' => function (Builder $query): void {
                $query->where('user_id', auth()->id());
            }])
            ->withExists(['saves as saved' => function (Builder $query): void {
                $query->where('user_id', auth()->id());
            }])
            ->withExists(['reports as reported' => function (Builder $query): void {
                $query->where('user_id', auth()->id());
            }]);
    }

    /**
     * @param  Builder<Signal>  $query
     * @return Builder<Signal>
     */
    public static function excludeMutedAuthors(Builder $query): Builder
    {
        $mutedAuthorIds = self::mutedAuthorIds();

        if ($mutedAuthorIds === []) {
            return $query;
        }

        return $query->whereNotIn('user_id', $mutedAuthorIds);
    }

    /**
     * @return list<int>
     */
    public static function mutedAuthorIds(): array
    {
        if (! auth()->check()) {
            return [];
        }

        return once(fn (): array => UserMute::query()
            ->where('user_id', auth()->id())
            ->pluck('muted_user_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Signal $signal): array
    {
        $signal->loadMissing(['user', 'media']);

        return [
            'id' => $signal->public_id,
            'type' => $signal->type->value,
            'body' => $signal->body,
            'link' => $signal->type->value === 'link' ? [
                'url' => $signal->link_url,
                'title' => $signal->link_title,
                'description' => $signal->link_description,
                'image' => $signal->link_image,
            ] : null,
            'media' => $signal->media->map(fn ($media) => [
                'id' => $media->id,
                'kind' => $media->kind->value,
                'url' => $media->url,
                'mime_type' => $media->mime_type,
            ])->values(),
            'author' => $signal->user->toPublicArray(),
            'created_at' => $signal->created_at?->toIso8601String(),
            'can_delete' => auth()->id() === $signal->user_id,
            'can_mute' => auth()->check() && auth()->id() !== $signal->user_id,
            'can_report' => auth()->check() && auth()->id() !== $signal->user_id,
            'saved' => (bool) $signal->saved,
            'reported' => (bool) $signal->reported,
            'author_muted' => in_array($signal->user_id, self::mutedAuthorIds(), true),
            'liked' => (bool) $signal->liked,
            'likes_count' => (int) ($signal->likes_count ?? 0),
            'replies_count' => (int) ($signal->replies_count ?? 0),
        ];
    }
}
