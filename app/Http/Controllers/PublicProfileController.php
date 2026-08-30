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

        $signals = FeedController::feedQuery()
            ->where('user_id', $user->id)
            ->roots()
            ->latest()
            ->paginate(15)
            ->through(fn ($signal) => FeedController::present($signal));

        return Inertia::render('Profile/Show', [
            'profile' => [
                ...$user->toPublicArray(),
                'is_mentor' => $user->mentorProfile !== null,
                'is_own' => auth()->id() === $user->id,
            ],
            'signals' => $signals,
        ]);
    }
}
