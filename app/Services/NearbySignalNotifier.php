<?php

namespace App\Services;

use App\Enums\SignalType;
use App\Models\Signal;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Geo;
use App\Support\HtmlBody;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class NearbySignalNotifier
{
    public function notify(Signal $signal): void
    {
        if (! in_array($signal->type, [SignalType::Need, SignalType::Opportunity], true)) {
            return;
        }

        if ($signal->latitude === null || $signal->longitude === null) {
            return;
        }

        $signal->loadMissing('user');

        $actor = $signal->user;

        if ($actor === null) {
            return;
        }

        $kind = $signal->type === SignalType::Need ? 'need' : 'opportunity';
        $title = $actor->displayName().' posted a '.$kind.' nearby';
        $body = $signal->title ?: Str::limit(HtmlBody::plainText($signal->body), 120) ?: null;
        $url = route('signals.show', $signal, absolute: false);
        $type = 'nearby_'.$kind;

        Geo::withinRadiusKm(User::query(), $signal->latitude, $signal->longitude, Geo::NEARBY_NOTIFICATION_KM)
            ->whereKeyNot($actor->id)
            ->whereDoesntHave('mutes', function (Builder $query) use ($actor): void {
                $query->where('muted_user_id', $actor->id);
            })
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($signal, $actor, $type, $title, $body, $url): void {
                $now = now();

                $rows = $users->map(fn (User $user): array => [
                    'user_id' => $user->id,
                    'actor_id' => $actor->id,
                    'signal_id' => $signal->id,
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'url' => $url,
                    'read_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows === []) {
                    return;
                }

                UserNotification::query()->insert($rows);
            }, 'id');
    }
}
