<?php

namespace Database\Factories;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SamplePart>
 */
class SamplePartFactory extends Factory
{
    protected $model = SamplePart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sampling_event_id' => SamplingEvent::factory(),
            'role' => PartRole::LAB,
            'qr_token' => Str::random(32),
            'blind_code' => null,
            'seal_number' => 'SEAL-'.strtoupper(Str::random(8)),
            'seal_photo_path' => 'seal-photos/'.Str::random(10).'.png',
            'status' => PartStatus::COLLECTED,
        ];
    }

    public function role(PartRole $role): static
    {
        return $this->state(['role' => $role]);
    }

    public function status(PartStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
