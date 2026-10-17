<?php

namespace App\Enums;

use App\Concerns\EnumValues;

/**
 * A cancelled transaction stays as void, next to its reversing journal.
 */
enum TransactionStatus: string
{
    use EnumValues;

    case Posted = 'posted';
    case Void = 'void';
}
