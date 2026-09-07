<?php

use App\Http\Controllers\FeedController;
use App\Http\Controllers\LinkPreviewController;
use App\Http\Controllers\MentorController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\PublicStorageController;
use App\Http\Controllers\SignalController;
use App\Http\Controllers\SignalUploadController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\UserLocationController;
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
    Route::post('signals/uploads', [SignalUploadController::class, 'store'])->name('signals.uploads.store');
    Route::delete('signals/uploads/{upload}', [SignalUploadController::class, 'destroy'])->name('signals.uploads.destroy');
    Route::post('stories', [StoryController::class, 'store'])->name('stories.store');
    Route::delete('stories/{story}', [StoryController::class, 'destroy'])->name('stories.destroy');
    Route::post('signals/{signal}/like', [SignalController::class, 'like'])->name('signals.like');
    Route::post('signals/{signal}/save', [SignalController::class, 'save'])->name('signals.save');
    Route::post('signals/{signal}/report', [SignalController::class, 'report'])->name('signals.report');
    Route::post('signals/{signal}/vote', [SignalController::class, 'vote'])->name('signals.vote');
    Route::post('users/{user}/mute', [UserMuteController::class, 'store'])->name('users.mute');
    Route::delete('signals/{signal}', [SignalController::class, 'destroy'])->name('signals.destroy');
    Route::post('link-preview', LinkPreviewController::class)->name('link-preview.store');
    Route::post('location', [UserLocationController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('location.store');
    Route::post('places/autocomplete', [PlaceController::class, 'autocomplete'])
        ->middleware('throttle:30,1')
        ->name('places.autocomplete');
    Route::get('places/{place}', [PlaceController::class, 'show'])
        ->middleware('throttle:30,1')
        ->where('place', '[A-Za-z0-9_-]+')
        ->name('places.show');

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

    Route::inertia('connections', 'ComingSoon', [
        'title' => 'Connections',
        'description' => 'Your network of operators, trades, and partners will live here.',
    ])->name('connections.index');

    Route::inertia('businesses', 'ComingSoon', [
        'title' => 'Businesses',
        'description' => 'Discover businesses to partner with, buy, or work alongside.',
    ])->name('businesses.index');

    Route::inertia('opportunities', 'ComingSoon', [
        'title' => 'Opportunities',
        'description' => 'Browse open gigs, projects, and deals from the universe.',
    ])->name('opportunities.index');

    Route::inertia('wallet', 'ComingSoon', [
        'title' => 'My Wallet',
        'description' => 'Payouts and project payments will settle here.',
    ])->name('wallet.index');

    Route::inertia('projects', 'ComingSoon', [
        'title' => 'My Projects',
        'description' => 'Track the work you post and the work you take on.',
    ])->name('projects.index');

    Route::inertia('circles', 'ComingSoon', [
        'title' => 'Circles',
        'description' => 'Private groups for owners, investors, and operators.',
    ])->name('circles.index');

    Route::inertia('deals', 'ComingSoon', [
        'title' => 'Deals Marketplace',
        'description' => 'Businesses for sale and partnership deals will list here.',
    ])->name('deals.index');

    Route::inertia('events', 'ComingSoon', [
        'title' => 'Events',
        'description' => 'Mixers, summits, and local meetups will show up here.',
    ])->name('events.index');
});

require __DIR__.'/settings.php';
