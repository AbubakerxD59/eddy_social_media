<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\LinkPreviewController;
use App\Http\Controllers\MentorController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\SignalController;
use App\Http\Controllers\UserMuteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/storage/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('storage.show');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::get('/@{user:username}', PublicProfileController::class)->name('profiles.show');
Route::get('/s/{signal}', [SignalController::class, 'show'])->name('signals.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', FeedController::class)->name('dashboard');
    Route::post('signals', [SignalController::class, 'store'])->name('signals.store');
    Route::post('signals/{signal}/like', [SignalController::class, 'like'])->name('signals.like');
    Route::post('signals/{signal}/save', [SignalController::class, 'save'])->name('signals.save');
    Route::post('signals/{signal}/report', [SignalController::class, 'report'])->name('signals.report');
    Route::post('users/{user}/mute', [UserMuteController::class, 'store'])->name('users.mute');
    Route::delete('signals/{signal}', [SignalController::class, 'destroy'])->name('signals.destroy');
    Route::post('link-preview', LinkPreviewController::class)->name('link-preview.store');

    Route::get('mentors', [MentorController::class, 'index'])->name('mentors.index');
    Route::post('mentors', [MentorController::class, 'store'])->name('mentors.store');

    Route::inertia('messages', 'ComingSoon', [
        'title' => 'Messages',
        'description' => 'Live chat will use Laravel Echo. On Hostinger shared hosting that will go through Pusher or Ably, then Laravel Reverb after a VPS move.',
    ])->name('messages.index');

    Route::inertia('notifications', 'ComingSoon', [
        'title' => 'Notifications',
        'description' => 'Realtime notifications will share the same Echo channel layer as chat. The Vue UI can be wired without changing this stack.',
    ])->name('notifications.index');
});

require __DIR__.'/settings.php';
