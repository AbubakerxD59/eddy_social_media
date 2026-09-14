<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfilePhotoRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($user->isBusiness() && $user->isDirty('name')) {
            $user->business_name = $user->name;
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    public function photo(ProfilePhotoRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $kind = $request->string('kind')->toString();
        $column = $kind === 'cover' ? 'cover_path' : 'avatar_path';
        $directory = $kind === 'cover' ? 'covers' : 'avatars';

        if ($user->{$column}) {
            Storage::disk('public')->delete($user->{$column});
        }

        $path = $request->file('photo')->store($directory.'/'.$user->id, 'public');
        $user->forceFill([$column => $path])->save();

        $url = $kind === 'cover' ? $user->cover_url : $user->avatar_url;
        $message = $kind === 'cover' ? 'Cover photo updated.' : 'Profile picture updated.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'kind' => $kind,
                'url' => $url,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Your account has been deleted.')]);

        return redirect('/');
    }
}
