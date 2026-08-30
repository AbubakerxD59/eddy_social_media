<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserMute;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UserMuteController extends Controller
{
    public function store(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 403);

        $mute = UserMute::query()
            ->where('user_id', auth()->id())
            ->where('muted_user_id', $user->id)
            ->first();

        if ($mute) {
            $mute->delete();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Unmuted :name.', ['name' => $user->displayName()]),
            ]);

            return back();
        }

        UserMute::query()->create([
            'user_id' => auth()->id(),
            'muted_user_id' => $user->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Muted :name. Their signals will be hidden.', ['name' => $user->displayName()]),
        ]);

        return back();
    }
}
