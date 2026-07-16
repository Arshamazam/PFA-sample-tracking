<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Premises;
use App\Models\SamplingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SamplingEvent>
 */
class SamplingEventFactory extends Factory
{
    protected $model = SamplingEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_code' => 'PFA-LHR-2026-'.$this->faker->unique()->numerify('######'),
            'premises_id' => Premises::factory(),
            'fso_id' => User::factory()->state(['role' => UserRole::FSO]),
            'food_item' => 'Loose Milk',
            'food_category' => 'MILK',
            'brand_name' => null,
            'is_perishable' => false,
            'witness_name' => $this->faker->name(),
            'witness_cnic' => $this->faker->numerify('#####-#######-#'),
            'collected_at' => now(),
            'finalized_at' => null,
        ];
    }

    public function perishable(): static
    {
        return $this->state(['is_perishable' => true]);
    }
}
