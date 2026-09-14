<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTalentProfileRequest;
use App\Support\TalentSkills;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TalentController extends Controller
{
    public function store(StoreTalentProfileRequest $request): RedirectResponse
    {
        $request->user()->becomeTalent([
            'headline' => $request->validated('headline'),
            'bio' => $request->validated('bio'),
            'skills' => TalentSkills::fromInput($request->validated('skills')),
            'hourly_rate_cents' => TalentSkills::centsFromRate($request->validated('hourly_rate')),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('You are now listed as talent.'),
        ]);

        return to_route('mentors.index');
    }
}
