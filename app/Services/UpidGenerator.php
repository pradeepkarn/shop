<?php

namespace App\Services;

use App\Models\ProductVariant;
use RuntimeException;

class UpidGenerator
{
    /**
     * Unambiguous uppercase alphanumeric charset (excludes 0, O, 1, I).
     */
    public const CHARSET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public const PREFIX = 'KHP';

    public const LENGTH = 12;

    /**
     * Generate a unique UPID (e.g. KHP + 12 chars).
     */
    public static function generate(int $maxAttempts = 10): string
    {
        $charsetLength = strlen(self::CHARSET);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $random = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $random .= self::CHARSET[random_int(0, $charsetLength - 1)];
            }

            $upid = self::PREFIX.$random;

            if (! ProductVariant::where('upid', $upid)->exists()) {
                return $upid;
            }
        }

        throw new RuntimeException("Failed to generate a unique UPID after {$maxAttempts} attempts.");
    }
}
