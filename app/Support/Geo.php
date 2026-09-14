<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class Geo
{
    public const KM_PER_DEGREE = 111.045;

    public const NEARBY_NOTIFICATION_KM = 50;

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function withinRadiusKm(Builder $query, float $latitude, float $longitude, int $radiusKm): Builder
    {
        [$sql, $bindings] = self::distanceKmSquaredExpression($latitude, $longitude);

        $cosLat = cos(deg2rad($latitude));
        $latDelta = $radiusKm / self::KM_PER_DEGREE;
        $lngDelta = abs($cosLat) > 0.000001
            ? $radiusKm / (self::KM_PER_DEGREE * abs($cosLat))
            : 180.0;

        return $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [
                max(-90.0, $latitude - $latDelta),
                min(90.0, $latitude + $latDelta),
            ])
            ->whereBetween('longitude', [
                max(-180.0, $longitude - $lngDelta),
                min(180.0, $longitude + $lngDelta),
            ])
            ->whereRaw($sql.' <= ?', [...$bindings, $radiusKm * $radiusKm]);
    }

    /**
     * @return array{0: string, 1: list<float>}
     */
    public static function distanceKmSquaredExpression(float $latitude, float $longitude): array
    {
        $cosLat = cos(deg2rad($latitude));

        return [
            '? * ((((longitude - ?) * ?) * ((longitude - ?) * ?)) + ((latitude - ?) * (latitude - ?)))',
            [
                self::KM_PER_DEGREE * self::KM_PER_DEGREE,
                $longitude,
                $cosLat,
                $longitude,
                $cosLat,
                $latitude,
                $latitude,
            ],
        ];
    }
}
