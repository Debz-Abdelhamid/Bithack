<?php

namespace Database\Factories;

use App\Models\PurchaseReference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReference>
 */
class PurchaseReferenceFactory extends Factory
{
    protected $model = PurchaseReference::class;

    public function definition(): array
    {
        return [
            'external_purchase_id' => fake()->unique()->bothify('R7-####-????'),
            'supplier' => fake()->optional()->company(),
            'order_date' => fake()->optional()->dateTimeBetween('-5 years'),
        ];
    }
}
