<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * Where an order stands (PRD US-09): Menunggu → Diproses → (Siap) → Selesai,
 * or Ditolak. Siap may be skipped; only a waiting order is rejected, and an
 * accepted one is cancelled instead, which also ends at Ditolak.
 */
enum OrderStatus: string
{
    use EnumValues;

    case Menunggu = 'menunggu';
    case Diproses = 'diproses';
    case Siap = 'siap';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu konfirmasi',
            self::Diproses => 'Diproses',
            self::Siap => 'Siap / diantar',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Ditolak',
        };
    }

    public function canAccept(): bool
    {
        return $this === self::Menunggu;
    }

    public function canReject(): bool
    {
        return $this === self::Menunggu;
    }

    public function canMarkReady(): bool
    {
        return $this === self::Diproses;
    }

    public function canComplete(): bool
    {
        return in_array($this, [self::Diproses, self::Siap], true);
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Diproses, self::Siap], true);
    }

    /** Payments come in once accepted, and after Selesai until the rest is paid. */
    public function acceptsPayment(): bool
    {
        return in_array($this, [self::Diproses, self::Siap, self::Selesai], true);
    }
}
