<?php

namespace App\Http\Controllers;

use App\Enums\SignalType;
use App\Models\Signal;
use App\Models\User;
use App\Models\UserMute;
use App\Services\GooglePlacesService;
use App\Support\HtmlBody;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public const PAGE_SIZE = 10;

    public const INITIAL_RADIUS_KM = 250;

    public const MAX_RADIUS_KM = 1000;

    /**
     * @var list<int>
     */
    public const FEED_RADII_KM = [250, 500, 1000];

    private const KM_PER_DEGREE = 111.045;

    public function __invoke(Request $request): Response
    {
        $filter = self::feedFilter($request);
        $type = self::typeForFilter($filter);

        return Inertia::render('Feed', [
            'highlight' => $request->string('highlight')->toString() ?: null,
            'compose' => self::composeType($request)?->value,
            'activeFilter' => $filter,
            'activeType' => $type?->value,
            'signals' => Inertia::scroll(
                fn () => $filter === 'connections'
                    ? self::emptyPage($request)
                    : self::paginatedSignals($request, $type),
            )->defer('feed'),
        ]);
    }

    /**
     * @return 'for-you'|'connections'|'opportunity'|'need'
     */
    public static function feedFilter(Request $request): string
    {
        $filter = (string) $request->query('filter');

        if (in_array($filter, ['for-you', 'connections', 'opportunity', 'need'], true)) {
            return $filter;
        }

        return match (SignalType::tryFrom((string) $request->query('type'))) {
            SignalType::Need => 'need',
            SignalType::Opportunity => 'opportunity',
            default => 'for-you',
        };
    }

    public static function typeForFilter(string $filter): ?SignalType
    {
        return match ($filter) {
            'need' => SignalType::Need,
            'opportunity' => SignalType::Opportunity,
            default => null,
        };
    }

    public static function composeType(Request $request): ?SignalType
    {
        $type = SignalType::tryFrom((string) $request->query('compose'));
        $user = $request->user();

        if ($type === null || ! $user instanceof User || ! $user->canCompose($type)) {
            return null;
        }

        return $type;
    }

    /**
     * @return Paginator<int, array<string, mixed>>
     */
    public static function emptyPage(Request $request): Paginator
    {
        return new Paginator([], 0, self::PAGE_SIZE, 1, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function rail(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $signalCount = $user->signals()->count();
        $xp = min(10000, 800 + ($signalCount * 850));
        $level = max(1, 1 + intdiv($xp, 1250));
        $title = match (true) {
            $level >= 8 => 'Visionary',
            $level >= 5 => 'Operator',
            $level >= 3 => 'Builder',
            default => 'Explorer',
        };

        $matches = User::query()
            ->whereKeyNot($user->getKey())
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (User $match): array => [
                ...$match->toPublicArray(),
                'match' => 71 + (($match->id * 17) % 28),
            ])
            ->values()
            ->all();

        $needsQuery = Signal::query()
            ->roots()
            ->where('type', SignalType::Need)
            ->with('user');

        $origin = self::viewerOrigin(request());

        if ($origin !== null) {
            self::applyDistanceOrder($needsQuery, $origin['latitude'], $origin['longitude']);
        } else {
            $needsQuery->latest();
        }

        $needs = $needsQuery
            ->limit(4)
            ->get()
            ->map(fn (Signal $signal): array => [
                'id' => $signal->public_id,
                'title' => $signal->title ?: Str::limit(HtmlBody::plainText($signal->body), 52),
                'budget' => is_array($signal->payload) ? ($signal->payload['budget'] ?? null) : null,
                'location' => is_array($signal->payload) ? ($signal->payload['location'] ?? null) : null,
            ])
            ->values()
            ->all();

        return [
            'level' => $level,
            'title' => $title,
            'xp' => $xp,
            'xp_max' => 10000,
            'matches' => $matches,
            'needs' => $needs,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public static function paginatedSignals(Request $request, ?SignalType $type = null, ?int $userId = null): mixed
    {
        $query = self::excludeMutedAuthors(self::feedQuery(eagerTypeRelations: false))
            ->roots();

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $origin = $userId === null ? self::viewerOrigin($request) : null;

        if ($origin === null) {
            $page = $query
                ->latest('created_at')
                ->latest('id')
                ->paginate(self::PAGE_SIZE)
                ->withQueryString();

            self::loadTypeRelations($page->getCollection());

            return $page->through(fn (Signal $signal) => self::present($signal));
        }

        return self::paginateSignalsByRadius(
            $request,
            $query,
            $origin['latitude'],
            $origin['longitude'],
        );
    }

    /**
     * @param  Builder<Signal>  $query
     * @return Paginator<int, array<string, mixed>>
     */
    private static function paginateSignalsByRadius(
        Request $request,
        Builder $query,
        float $latitude,
        float $longitude,
    ): Paginator {
        $page = max(1, (int) $request->integer('page', 1));
        $offset = ($page - 1) * self::PAGE_SIZE;
        $needed = self::PAGE_SIZE;
        $total = 0;
        $results = new EloquentCollection;

        $previousKm = 0;

        foreach (self::FEED_RADII_KM as $radiusKm) {
            $ringQuery = self::applyRingFilter(
                (clone $query)->reorder(),
                $latitude,
                $longitude,
                $previousKm,
                $radiusKm,
                includeUnlocated: $previousKm === 0,
            );

            $ringCount = (clone $ringQuery)->count();
            $total += $ringCount;

            if ($needed > 0) {
                if ($offset >= $ringCount) {
                    $offset -= $ringCount;
                } else {
                    $chunk = (clone $ringQuery)
                        ->latest('created_at')
                        ->latest('id')
                        ->offset($offset)
                        ->limit($needed)
                        ->get();

                    $results = $results->concat($chunk);
                    $needed -= $chunk->count();
                    $offset = 0;
                }
            }

            $previousKm = $radiusKm;
        }

        self::loadTypeRelations($results);

        return new Paginator(
            $results->map(fn (Signal $signal) => self::present($signal))->values()->all(),
            $total,
            self::PAGE_SIZE,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    /**
     * @param  Builder<Signal>  $query
     * @return Builder<Signal>
     */
    public static function applyRingFilter(
        Builder $query,
        float $latitude,
        float $longitude,
        int $minKm,
        int $maxKm,
        bool $includeUnlocated,
    ): Builder {
        return $query->where(function (Builder $feed) use ($latitude, $longitude, $minKm, $maxKm, $includeUnlocated): void {
            if ($includeUnlocated) {
                $feed->where(function (Builder $unlocated): void {
                    $unlocated->whereNull('latitude')->orWhereNull('longitude');
                })->orWhere(function (Builder $located) use ($latitude, $longitude, $minKm, $maxKm): void {
                    self::applyLocatedRing($located, $latitude, $longitude, $minKm, $maxKm);
                });

                return;
            }

            self::applyLocatedRing($feed, $latitude, $longitude, $minKm, $maxKm);
        });
    }

    /**
     * @param  Builder<Signal>  $query
     * @return Builder<Signal>
     */
    private static function applyLocatedRing(
        Builder $query,
        float $latitude,
        float $longitude,
        int $minKm,
        int $maxKm,
    ): Builder {
        self::applyRadiusFilter($query, $latitude, $longitude, $maxKm);

        if ($minKm > 0) {
            [$sql, $bindings] = self::distanceKmSquaredExpression($latitude, $longitude);
            $query->whereRaw($sql.' > ?', [...$bindings, $minKm * $minKm]);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Signal>  $signals
     */
    private static function loadTypeRelations(Collection $signals): void
    {
        $models = new EloquentCollection($signals->values()->all());

        $withMedia = $models->filter(fn (Signal $signal): bool => $signal->type !== SignalType::Poll);

        if ($withMedia->isNotEmpty()) {
            (new EloquentCollection($withMedia->all()))->load('media');
        }

        $polls = $models->filter(fn (Signal $signal): bool => $signal->type === SignalType::Poll);

        if ($polls->isNotEmpty()) {
            (new EloquentCollection($polls->all()))->load('pollVotes');
        }
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public static function rememberViewerOrigin(Request $request): ?array
    {
        $origin = self::originFromValues($request->query('lat'), $request->query('lng'));

        if ($origin !== null) {
            $request->session()->put('viewer_latitude', $origin['latitude']);
            $request->session()->put('viewer_longitude', $origin['longitude']);
            $request->session()->put('viewer_location_label', self::resolveLocationLabel(
                $origin['latitude'],
                $origin['longitude'],
            ) ?? '');
            $request->session()->put('viewer_location_manual', false);

            return $origin;
        }

        return self::viewerOrigin($request);
    }

    /**
     * @return array{latitude: float, longitude: float, label: string, manual: bool}
     */
    public static function persistViewerOrigin(
        Request $request,
        float $latitude,
        float $longitude,
        ?string $label = null,
        ?bool $manual = null,
    ): array {
        $user = $request->user();

        if ($manual === null && $user instanceof User && $user->location_manual && $user->latitude !== null && $user->longitude !== null) {
            return self::storeViewerOriginState(
                $request,
                (float) $user->latitude,
                (float) $user->longitude,
                filled($user->location_label) && ! self::isPlaceholderLocationLabel($user->location_label)
                    ? (string) $user->location_label
                    : (self::resolveLocationLabel((float) $user->latitude, (float) $user->longitude) ?? ''),
                true,
                saveUser: false,
            );
        }

        $resolvedManual = $manual ?? false;
        $resolvedLabel = self::isPlaceholderLocationLabel($label)
            ? ($resolvedManual && $user instanceof User && filled($user->location_label) && ! self::isPlaceholderLocationLabel($user->location_label)
                ? (string) $user->location_label
                : (self::resolveLocationLabel($latitude, $longitude) ?? ''))
            : trim((string) $label);

        return self::storeViewerOriginState(
            $request,
            $latitude,
            $longitude,
            $resolvedLabel,
            $resolvedManual,
        );
    }

    /**
     * @return array{latitude: float, longitude: float, label: string, manual: bool}|null
     */
    public static function viewerLocationState(?Request $request = null): ?array
    {
        $request ??= request();
        $origin = self::viewerOrigin($request);

        if ($origin === null) {
            return null;
        }

        $label = $request->session()->get('viewer_location_label');
        $user = $request->user();

        if (! is_string($label) || self::isPlaceholderLocationLabel($label)) {
            $label = $user instanceof User && filled($user->location_label) && ! self::isPlaceholderLocationLabel($user->location_label)
                ? (string) $user->location_label
                : (self::resolveLocationLabel($origin['latitude'], $origin['longitude']) ?? '');

            $request->session()->put('viewer_location_label', $label);

            if ($user instanceof User && self::isPlaceholderLocationLabel($user->location_label) && $label !== '') {
                $user->forceFill(['location_label' => $label])->save();
            }
        }

        $manual = $request->session()->exists('viewer_location_manual')
            ? (bool) $request->session()->get('viewer_location_manual')
            : (bool) ($user?->location_manual ?? false);

        return [
            'latitude' => $origin['latitude'],
            'longitude' => $origin['longitude'],
            'label' => $label,
            'manual' => $manual,
        ];
    }

    /**
     * @return array{latitude: float, longitude: float, label: string, manual: bool}
     */
    private static function storeViewerOriginState(
        Request $request,
        float $latitude,
        float $longitude,
        string $label,
        bool $manual,
        bool $saveUser = true,
    ): array {
        $request->session()->put('viewer_latitude', $latitude);
        $request->session()->put('viewer_longitude', $longitude);
        $request->session()->put('viewer_location_label', $label);
        $request->session()->put('viewer_location_manual', $manual);

        $user = $request->user();

        if ($saveUser && $user instanceof User) {
            $unchanged = $user->latitude !== null
                && $user->longitude !== null
                && abs($user->latitude - $latitude) < 0.0005
                && abs($user->longitude - $longitude) < 0.0005
                && $user->location_label === $label
                && (bool) $user->location_manual === $manual;

            if (! $unchanged) {
                $user->forceFill([
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location_label' => $label,
                    'location_manual' => $manual,
                    'location_updated_at' => now(),
                ])->save();
            }
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'label' => $label,
            'manual' => $manual,
        ];
    }

    public static function isPlaceholderLocationLabel(?string $label): bool
    {
        $label = is_string($label) ? trim($label) : '';

        if ($label === '' || strcasecmp($label, 'Current location') === 0) {
            return true;
        }

        return (bool) preg_match('/^-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?$/', $label);
    }

    public static function resolveLocationLabel(float $latitude, float $longitude): ?string
    {
        $resolved = app(GooglePlacesService::class)->reverseGeocode($latitude, $longitude);

        if (! is_string($resolved)) {
            return null;
        }

        $resolved = trim($resolved);

        if ($resolved === '' || self::isPlaceholderLocationLabel($resolved)) {
            return null;
        }

        return $resolved;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public static function viewerOrigin(?Request $request = null): ?array
    {
        $request ??= request();
        $fromSession = self::originFromValues(
            $request->session()->get('viewer_latitude'),
            $request->session()->get('viewer_longitude'),
        );

        if ($fromSession !== null) {
            return $fromSession;
        }

        $user = $request->user();

        if ($user instanceof User) {
            return self::originFromValues($user->latitude, $user->longitude);
        }

        return null;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public static function originFromValues(mixed $latitude, mixed $longitude): ?array
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * @param  Builder<Signal>  $query
     * @return Builder<Signal>
     */
    public static function applyRadiusFilter(Builder $query, float $latitude, float $longitude, int $radiusKm): Builder
    {
        [$sql, $bindings] = self::distanceKmSquaredExpression($latitude, $longitude);

        $cosLat = cos(deg2rad($latitude));
        $latDelta = $radiusKm / self::KM_PER_DEGREE;
        $lngDelta = abs($cosLat) > 0.000001
            ? $radiusKm / (self::KM_PER_DEGREE * abs($cosLat))
            : 180.0;

        return $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [
                max(-90.0, $latitude - $latDelta),
                min(90.0, $latitude + $latDelta),
            ])
            ->whereBetween('longitude', [
                max(-180.0, $longitude - $lngDelta),
                min(180.0, $longitude + $lngDelta),
            ])
            ->whereRaw($sql.' <= ?', [...$bindings, $radiusKm * $radiusKm]);
    }

    /**
     * @param  Builder<Signal>  $query
     * @return Builder<Signal>
     */
    public static function applyDistanceOrder(Builder $query, float $latitude, float $longitude): Builder
    {
        [$sql, $bindings] = self::distanceKmSquaredExpression($latitude, $longitude);

        return $query
            ->orderByRaw('CASE WHEN latitude IS NULL OR longitude IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw($sql, $bindings)
            ->orderByDesc('created_at');
    }

    /**
     * @return array{0: string, 1: list<float>}
     */
    private static function distanceKmSquaredExpression(float $latitude, float $longitude): array
    {
        $cosLat = cos(deg2rad($latitude));

        return [
            '? * ((((longitude - ?) * ?) * ((longitude - ?) * ?)) + ((latitude - ?) * (latitude - ?)))',
            [
                self::KM_PER_DEGREE * self::KM_PER_DEGREE,
                $longitude,
                $cosLat,
                $longitude,
                $cosLat,
                $latitude,
                $latitude,
            ],
        ];
    }

    /**
     * @return Builder<Signal>
     */
    public static function feedQuery(bool $eagerTypeRelations = true): Builder
    {
        $query = Signal::query()
            ->with(['user'])
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

        if ($eagerTypeRelations) {
            $query->with(['media', 'pollVotes']);
        }

        return $query;
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
        $signal->loadMissing('user');

        if ($signal->type === SignalType::Poll) {
            $signal->loadMissing('pollVotes');
        } else {
            $signal->loadMissing('media');
        }

        return [
            'id' => $signal->public_id,
            'type' => $signal->type->value,
            'is_reply' => $signal->parent_id !== null,
            'title' => $signal->title,
            'body' => HtmlBody::present($signal->body),
            'need' => self::presentNeed($signal),
            'opportunity' => self::presentOpportunity($signal),
            'poll' => $signal->type === SignalType::Poll ? self::presentPoll($signal) : null,
            'link' => $signal->link_url ? [
                'url' => $signal->link_url,
                'title' => $signal->link_title,
                'description' => $signal->link_description,
                'image' => $signal->link_image,
            ] : null,
            'media' => $signal->type === SignalType::Poll
                ? []
                : $signal->media->map(fn ($media) => [
                    'id' => $media->id,
                    'kind' => $media->kind->value,
                    'url' => $media->url,
                    'mime_type' => $media->mime_type,
                ])->values(),
            'author' => $signal->user->toPublicArray(),
            'created_at' => $signal->created_at?->toIso8601String(),
            'latitude' => $signal->latitude,
            'longitude' => $signal->longitude,
            'place_id' => $signal->place_id,
            'can_edit' => auth()->id() === $signal->user_id,
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

    /**
     * @return array{options: list<array{id: string, text: string, votes_count: int}>, total_votes: int, voted_option_id: string|null}
     */
    public static function presentPoll(Signal $signal): array
    {
        $signal->loadMissing('pollVotes');

        $votes = $signal->pollVotes;
        $counts = $votes->countBy('option_id');
        $voted = $votes->firstWhere('user_id', auth()->id());

        $options = [];

        foreach ($signal->payload['options'] ?? [] as $option) {
            if (! is_array($option) || ! isset($option['id'], $option['text'])) {
                continue;
            }

            $id = (string) $option['id'];

            $options[] = [
                'id' => $id,
                'text' => (string) $option['text'],
                'votes_count' => (int) ($counts[$id] ?? 0),
            ];
        }

        return [
            'options' => $options,
            'total_votes' => $votes->count(),
            'voted_option_id' => $voted?->option_id,
        ];
    }

    /**
     * @return array{budget: string|null, timeline: string|null, location: string|null, skills: list<string>}|null
     */
    private static function presentNeed(Signal $signal): ?array
    {
        if ($signal->type !== SignalType::Need) {
            return null;
        }

        $payload = $signal->payload ?? [];

        return [
            'budget' => isset($payload['budget']) ? (string) $payload['budget'] : null,
            'timeline' => isset($payload['timeline']) ? (string) $payload['timeline'] : null,
            'location' => isset($payload['location']) ? (string) $payload['location'] : null,
            'skills' => self::stringList($payload['skills'] ?? []),
        ];
    }

    /**
     * @return array{project_value: string|null, timeline: string|null, location: string|null, trades: list<string>}|null
     */
    private static function presentOpportunity(Signal $signal): ?array
    {
        if ($signal->type !== SignalType::Opportunity) {
            return null;
        }

        $payload = $signal->payload ?? [];

        return [
            'project_value' => isset($payload['project_value']) ? (string) $payload['project_value'] : null,
            'timeline' => isset($payload['timeline']) ? (string) $payload['timeline'] : null,
            'location' => isset($payload['location']) ? (string) $payload['location'] : null,
            'trades' => self::stringList($payload['trades'] ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): string => trim((string) $item), $value),
        ));
    }
}
