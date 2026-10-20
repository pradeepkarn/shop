<?php

namespace App\Services;

use Illuminate\Support\Str;

class VariantKeyNormalizer
{
    /**
     * Normalize an associative or list array of variant attributes into a canonical key.
     * e.g. ['Size' => 'XL', 'Color' => 'Navy Blue'] -> "color:navy-blue|size:xl"
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function normalize(array $attributes): string
    {
        $normalized = [];

        foreach ($attributes as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $cleanKey = Str::slug((string) $key);
            $cleanValue = Str::slug((string) $value);

            if ($cleanKey !== '' && $cleanValue !== '') {
                $normalized[$cleanKey] = $cleanValue;
            }
        }

        ksort($normalized);

        $pairs = [];
        foreach ($normalized as $k => $v) {
            $pairs[] = "{$k}:{$v}";
        }

        return implode('|', $pairs);
    }
}
