<?php

namespace App\Enums;

use App\Concerns\EnumValues;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Period filter on lists, counted in the app time zone (APP_TIMEZONE).
 */
enum Period: string
{
    use EnumValues;

    case HariIni = 'hari_ini';
    case MingguIni = 'minggu_ini';
    case BulanIni = 'bulan_ini';
    case BulanLalu = 'bulan_lalu';

    public function label(): string
    {
        return match ($this) {
            self::HariIni => 'Hari ini',
            self::MingguIni => 'Minggu ini',
            self::BulanIni => 'Bulan ini',
            self::BulanLalu => 'Bulan lalu',
        };
    }

    public function comparisonLabel(): string
    {
        return match ($this) {
            self::HariIni => 'vs kemarin di jam yang sama',
            self::MingguIni => 'vs minggu lalu',
            self::BulanIni => 'vs bulan lalu',
            self::BulanLalu => 'vs bulan sebelumnya',
        };
    }

    /** The column heading for prior(). */
    public function priorLabel(): string
    {
        return match ($this) {
            self::HariIni => 'Kemarin',
            self::MingguIni => 'Minggu lalu',
            self::BulanIni => 'Bulan lalu',
            self::BulanLalu => 'Bulan sebelumnya',
        };
    }

    /**
     * The whole period before this one, [start, before): the comparative
     * column of a report (SAK EMKM par. 3.8). previous() stops at the same
     * moment instead, for the dashboard's like-for-like change.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function prior(): array
    {
        $start = $this->start();

        return [match ($this) {
            self::HariIni => $start->subDay(),
            self::MingguIni => $start->subWeek(),
            self::BulanIni, self::BulanLalu => $start->subMonthNoOverflow(),
        }, $start];
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
            self::BulanLalu => $now->subMonthNoOverflow()->startOfMonth(),
        };
    }

    /**
     * Where the period stops (exclusive), or null when it runs up to now.
     */
    public function end(?CarbonImmutable $at = null): ?CarbonImmutable
    {
        return $this === self::BulanLalu ? ($at ?? now()->toImmutable())->startOfMonth() : null;
    }

    /**
     * The period before, up to the same moment: [start, before). A finished
     * month is compared with the whole month before it.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function previous(): array
    {
        $now = now()->toImmutable();

        if ($this === self::BulanLalu) {
            return [$now->subMonthsNoOverflow(2)->startOfMonth(), $now->subMonthNoOverflow()->startOfMonth()];
        }

        $then = match ($this) {
            self::HariIni => $now->subDay(),
            self::MingguIni => $now->subWeek(),
            self::BulanIni => $now->subMonthNoOverflow(),
        };

        return [$this->start($then), $then];
    }
}
