<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Stock starts at zero; give an item stock through App\Services\Inventory so
 * the journal stays in step.
 *
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
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
            'kind' => 'produk',
            'name' => fake()->unique()->words(2, true),
            'aliases' => [],
            'unit' => 'pcs',
            'price' => 5000,
            'min_stock' => null,
            'show_in_store' => true,
            'available' => true,
        ];
    }

    public function material(): static
    {
        return $this->state(['kind' => 'bahan', 'price' => null, 'unit' => 'kg', 'show_in_store' => false]);
    }

    public function recipe(): static
    {
        return $this->state(['kind' => 'komposisi', 'unit' => 'porsi', 'price' => 15000]);
    }
}
