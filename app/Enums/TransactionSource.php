<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * Where a record came from.
 */
enum TransactionSource: string
{
    use EnumValues;

    case Web = 'web';
    case Wa = 'wa';
    case Telegram = 'tg';
    case Toko = 'toko';
    case Ocr = 'ocr';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Wa => 'WhatsApp',
            self::Telegram => 'Telegram',
            self::Toko => 'Halaman toko',
            self::Ocr => 'Foto nota',
        };
    }
}
