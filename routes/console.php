<?php

use App\Models\SignalUpload;
use App\Models\Story;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('stories:prune', function () {
    $deleted = 0;

    Story::query()->expired()->chunkById(100, function ($stories) use (&$deleted): void {
        foreach ($stories as $story) {
            $story->delete();
            $deleted++;
        }
    });

    $this->info("Pruned {$deleted} expired stories.");
})->purpose('Delete stories that have been live for more than 24 hours');

Artisan::command('uploads:prune', function () {
    $deleted = 0;

    SignalUpload::query()->stale()->chunkById(100, function ($uploads) use (&$deleted): void {
        foreach ($uploads as $upload) {
            $upload->delete();
            $deleted++;
        }
    });

    $this->info("Pruned {$deleted} unused uploads.");
})->purpose('Delete staged signal uploads older than 24 hours');

Schedule::command('stories:prune')->hourly();
Schedule::command('uploads:prune')->hourly();
