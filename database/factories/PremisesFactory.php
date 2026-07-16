<?php

namespace Database\Factories;

use App\Models\Premises;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Premises>
 */
class PremisesFactory extends Factory
{
    protected $model = Premises::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'license_no' => 'PFA-LHR-2025-'.$this->faker->unique()->numberBetween(20000, 99999),
            'name' => $this->faker->company().' Foods',
            'address' => $this->faker->streetAddress(),
            'city' => 'Lahore',
            'owner_name' => $this->faker->name(),
            'owner_phone' => '03'.$this->faker->numerify('##-#######'),
            'source' => 'MANUAL',
        ];
    }
}
