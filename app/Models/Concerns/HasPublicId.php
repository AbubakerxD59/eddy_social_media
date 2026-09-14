<?php

namespace App\Models\Concerns;

trait HasPublicId
{
    public const PUBLIC_ID_LENGTH = 8;

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public static function generatePublicId(): string
    {
        $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        do {
            $id = '';

            for ($i = 0; $i < self::PUBLIC_ID_LENGTH; $i++) {
                $id .= $alphabet[random_int(0, 61)];
            }
        } while (static::query()->where('public_id', $id)->exists());

        return $id;
    }

    public static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (filled($model->public_id)) {
                return;
            }

            $model->public_id = static::generatePublicId();
        });
    }
}
