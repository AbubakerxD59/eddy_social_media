<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class PublicProfileController extends Controller
{
    public function __invoke(User $user): Response
    {
        $user->load('mentorProfile');

        return Inertia::render('Profile/Show', [
            'profile' => [
                ...$user->toPublicArray(),
                'is_mentor' => $user->mentorProfile !== null,
                'is_own' => auth()->id() === $user->id,
            ],
            'signals' => Inertia::scroll(
                fn () => FeedController::paginatedSignals(request(), null, $user->id),
            )->defer('feed'),
        ]);
    }
}
