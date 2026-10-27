<?php

namespace Database\Factories;

use App\Enums\MoneyAccountKind;
use App\Models\Business;
use App\Models\MoneyAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A non-tunai rekening; every business already has its Kas tunai.
 *
 * @extends Factory<MoneyAccount>
 */
class MoneyAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'kind' => MoneyAccountKind::NonTunai,
            'name' => fake()->unique()->randomElement(['BCA', 'BRI', 'Mandiri', 'BNI', 'GoPay', 'OVO', 'DANA', 'ShopeePay']),
        ];
    }
}
