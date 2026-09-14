<?php

namespace App\Http\Controllers;

use App\Models\MentorProfile;
use App\Models\TalentProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MentorController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Mentors/Index', [
            'mentors' => Inertia::scroll(
                fn () => TalentProfile::query()
                    ->where('is_available', true)
                    ->with('user')
                    ->latest()
                    ->paginate(FeedController::PAGE_SIZE)
                    ->through(fn (TalentProfile $talent) => $talent->toPublicArray()),
            )->defer('mentors'),
            'isMentor' => $user?->mentorProfile !== null,
            'isTalent' => $user?->isTalent() ?? false,
            'canBecomeTalent' => $user?->isExplorer() ?? false,
            'talentProfile' => $user?->talentProfile?->only(['headline', 'bio', 'skills', 'hourly_rate_cents']),
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
