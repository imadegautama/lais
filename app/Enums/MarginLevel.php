<?php

namespace App\Enums;

/**
 * How healthy a product's margin is. One source for the margin badge on
 * screen (lais:enums) and for "worth it?" in Tanya AI and the bot.
 */
enum MarginLevel: string
{
    case Rugi = 'rugi';
    case Tipis = 'tipis';
    case Cukup = 'cukup';
    case Sehat = 'sehat';

    /** Below this margin, in percent, it is thin. */
    public const THIN = 15;

    /** From this margin, in percent, it is healthy; also the margin price suggestions aim for. */
    public const HEALTHY = 30;

    public static function of(float $percent): self
    {
        return match (true) {
            $percent < 0 => self::Rugi,
            $percent < self::THIN => self::Tipis,
            $percent < self::HEALTHY => self::Cukup,
            default => self::Sehat,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Rugi => 'Rugi',
            self::Tipis => 'Tipis',
            self::Cukup => 'Cukup',
            self::Sehat => 'Sehat',
        };
    }
}
