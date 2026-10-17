<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
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
            'type' => 'pelanggan',
            'name' => fake()->unique()->firstName(),
            'phone' => null,
        ];
    }

    public function supplier(): static
    {
        return $this->state(['type' => 'supplier', 'name' => 'Toko '.fake()->unique()->lastName()]);
    }
}
