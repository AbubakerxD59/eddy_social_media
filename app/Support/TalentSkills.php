<?php

namespace App\Support;

class TalentSkills
{
    /**
     * @return list<string>
     */
    public static function fromInput(mixed $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[,|\n]+/', (string) $value) ?: [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $skill) => trim((string) $skill),
            $parts,
        ))));
    }

    public static function centsFromRate(mixed $rate): ?int
    {
        if ($rate === null || $rate === '') {
            return null;
        }

        return (int) round(((float) $rate) * 100);
    }
}
