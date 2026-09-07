<?php

namespace App\Http\Middleware;

use App\Http\Controllers\FeedController;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $origin = FeedController::rememberViewerOrigin($request);
        $state = FeedController::viewerLocationState($request);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user?->toInertia(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'viewerLatitude' => $origin !== null ? $origin['latitude'] : null,
            'viewerLongitude' => $origin !== null ? $origin['longitude'] : null,
            'viewerLocation' => $state['label'] ?? null,
            'viewerLocationManual' => (bool) ($state['manual'] ?? false),
            'stories' => $user instanceof User
                ? Inertia::defer(fn () => Story::groupedForFeed($user, FeedController::mutedAuthorIds()), 'stories')
                : [],
            'rail' => $user instanceof User
                ? Inertia::defer(fn () => FeedController::rail($user), 'rail')
                : null,
        ];
    }
}
