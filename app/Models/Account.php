<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fixed chart of accounts, seeded by migration (PRD §4.4).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property string $normal_balance
 */
class Account extends Model
{
    // Codes of the fixed chart. Plain constants, not an enum, because they are array keys in Ledger::lines().
    public const CASH = '1101';

    public const NON_CASH = '1102';

    public const RECEIVABLE = '1201';

    public const INVENTORY = '1301';

    public const EQUIPMENT = '1401';

    public const PAYABLE = '2101';

    public const CUSTOMER_DEPOSITS = '2201';

    public const OWNER_CAPITAL = '3101';

    public const DRAWINGS = '3201';

    public const SALES = '4101';

    public const COGS = '5101';

    public const EXPENSES = '6101';

    public const STOCK_DIFFERENCE = '6201';

    public $timestamps = false;
}
