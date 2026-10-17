<?php

namespace App\Support;

/**
 * Indonesian number formats for text the server writes (validation messages,
 * descriptions, the audit log). The frontend has its own in lib/format.ts.
 */
class Format
{
    /**
     * "Rp15.000".
     */
    public static function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }

    /**
     * "0,15", "1.250", "3": up to three decimals, trailing zeros dropped.
     */
    public static function qty(float|int|string $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, ',', '.'), '0'), ',');
    }
}
