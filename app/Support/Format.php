<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Indonesian number formats for text the server writes (validation messages,
 * descriptions, the audit log). The frontend has its own in lib/format.ts.
 */
class Format
{
    /**
     * "Rp15.000", "−Rp5.000" (a minus sign, as lib/format.ts writes it).
     */
    public static function rupiah(int $amount): string
    {
        return ($amount < 0 ? '−' : '').'Rp'.number_format(abs($amount), 0, ',', '.');
    }

    /**
     * "17 Oktober 2026".
     */
    public static function date(CarbonInterface $date): string
    {
        $date = Carbon::instance($date);
        $date->locale('id');

        return $date->translatedFormat('j F Y');
    }

    /**
     * "0,15", "1.250", "3": up to three decimals, trailing zeros dropped.
     */
    public static function qty(float|int|string $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, ',', '.'), '0'), ',');
    }
}
