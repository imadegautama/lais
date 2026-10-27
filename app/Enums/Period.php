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

    public function comparisonLabel(): string
    {
        return match ($this) {
            self::HariIni => 'vs kemarin di jam yang sama',
            self::MingguIni => 'vs minggu lalu',
            self::BulanIni => 'vs bulan lalu',
        };
    }

    /**
     * Where the period that holds $at (default: now) begins.
     */
    public function start(?CarbonImmutable $at = null): CarbonImmutable
    {
        $now = $at ?? now()->toImmutable();

        return match ($this) {
            self::HariIni => $now->startOfDay(),
            self::MingguIni => $now->startOfWeek(CarbonInterface::MONDAY),
            self::BulanIni => $now->startOfMonth(),
        };
    }

    /**
     * The period before, up to the same moment: [start, before).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function previous(): array
    {
        $now = now()->toImmutable();
        $then = match ($this) {
            self::HariIni => $now->subDay(),
            self::MingguIni => $now->subWeek(),
            self::BulanIni => $now->subMonthNoOverflow(),
        };

        return [$this->start($then), $then];
    }
}
