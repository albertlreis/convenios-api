<?php

namespace App\Support;

use Illuminate\Support\Str;

class TextNormalizer
{
    public static function normalizeForMatch(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of((string) $value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->toString();

        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return $normalized !== '' ? $normalized : null;
    }
}
