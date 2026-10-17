<?php

namespace App\Enums;

use App\Concerns\EnumValues;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Period filter on lists, counted in the app time zone (WIB).
 */
enum Period: string
{
    use EnumValues;

    case HariIni = 'hari_ini';
    case MingguIni = 'minggu_ini';
    case BulanIni = 'bulan_ini';

    public function label(): string
    {
        return match ($this) {
            self::HariIni => 'Hari ini',
            self::MingguIni => 'Minggu ini',
            self::BulanIni => 'Bulan ini',
        };
    }

    public function start(): CarbonImmutable
    {
        $now = now()->toImmutable();

        return match ($this) {
            self::HariIni => $now->startOfDay(),
            self::MingguIni => $now->startOfWeek(CarbonInterface::MONDAY),
            self::BulanIni => $now->startOfMonth(),
        };
    }
}
