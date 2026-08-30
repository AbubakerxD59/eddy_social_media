<?php

namespace App\Http\Controllers;

use App\Models\MentorProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MentorController extends Controller
{
    public function index(): Response
    {
        $mentors = MentorProfile::query()
            ->where('is_accepting_bookings', true)
            ->with('user')
            ->latest()
            ->paginate(12)
            ->through(fn (MentorProfile $mentor) => [
                'id' => $mentor->id,
                'headline' => $mentor->headline ?: $mentor->user->headline,
                'bio' => $mentor->bio ?: $mentor->user->bio,
                'hourly_rate_cents' => $mentor->hourly_rate_cents,
                'google_connected' => $mentor->google_connected_at !== null,
                'user' => $mentor->user->toPublicArray(),
            ]);

        return Inertia::render('Mentors/Index', [
            'mentors' => $mentors,
            'isMentor' => auth()->user()?->mentorProfile !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'headline' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:500'],
            'hourly_rate_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        MentorProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                ...$validated,
                'is_accepting_bookings' => true,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('You are listed as a mentor.')]);

        return to_route('mentors.index');
    }
}
