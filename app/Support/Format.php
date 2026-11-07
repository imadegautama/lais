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
     * "Rp4,8 jt" from a million up, exact below. Same units as formatRupiahCompact.
     */
    public static function compact(int $amount): string
    {
        $size = abs($amount);

        if ($size < 1_000_000) {
            return self::rupiah($amount);
        }

        [$unit, $word] = $size >= 1_000_000_000 ? [1_000_000_000, 'M'] : [1_000_000, 'jt'];
        $value = rtrim(rtrim(number_format($size / $unit, 1, ',', '.'), '0'), ',');

        return ($amount < 0 ? '−' : '')."Rp{$value} {$word}";
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
     * "0,5", "1250": a quantity as the owner types it into a field, no
     * thousands separator (lib/number.ts decimalInput).
     */
    public static function decimalInput(float|int|string $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, ',', ''), '0'), ',');
    }

    /**
     * "0,15", "1.250", "3": up to three decimals, trailing zeros dropped.
     */
    public static function qty(float|int|string $qty): string
    {
        return rtrim(rtrim(number_format((float) $qty, 3, ',', '.'), '0'), ',');
    }

    /**
     * How owners type money in a chat: "20rb", "20 ribu", "15k", "1,5jt",
     * "20.000". Same reading as parseMoney() in resources/js/lib/format.ts.
     */
    public static function parseMoney(string $input): ?int
    {
        $text = (string) preg_replace(['/rp\.?/u', '/\s+/u'], '', mb_strtolower($input));

        if (preg_match('/^(\d+(?:[.,]\d+)*)(rb|ribu|k|jt|juta)?$/', $text, $match) !== 1) {
            return null;
        }

        $suffix = $match[2] ?? '';
        $multiplier = in_array($suffix, ['jt', 'juta'], true) ? 1_000_000 : ($suffix !== '' ? 1_000 : 1);
        // With a suffix "1,5" means one and a half; without one, "." groups thousands and "," is decimal.
        $normalized = $suffix !== ''
            ? str_replace(',', '.', (string) preg_replace('/\.(?=\d{3}(?!\d))/', '', $match[1]))
            : str_replace(',', '.', str_replace('.', '', $match[1]));

        return is_numeric($normalized) ? (int) round((float) $normalized * $multiplier) : null;
    }
}
