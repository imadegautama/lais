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
    public $timestamps = false;
}
