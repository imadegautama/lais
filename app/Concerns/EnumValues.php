<?php

namespace App\Concerns;

/**
 * For string-backed enums: the raw values, for validation lists and URL filters.
 */
trait EnumValues
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
