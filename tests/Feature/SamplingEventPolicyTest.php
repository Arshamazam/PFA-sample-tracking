<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SamplingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SamplingEventPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_fso_cannot_read_another_fsos_event(): void
    {
        $owner = User::factory()->create(['role' => UserRole::FSO]);
        $other = User::factory()->create(['role' => UserRole::FSO]);

        $event = SamplingEvent::factory()->create(['fso_id' => $owner->id]);

        Sanctum::actingAs($other, $other->role->abilities());

        $this->getJson("/api/v1/sampling-events/{$event->id}")->assertStatus(403);
    }

    public function test_fso_can_read_their_own_event(): void
    {
        $owner = User::factory()->create(['role' => UserRole::FSO]);
        $event = SamplingEvent::factory()->create(['fso_id' => $owner->id]);

        Sanctum::actingAs($owner, $owner->role->abilities());

        $this->getJson("/api/v1/sampling-events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $event->id);
    }

    public function test_event_list_only_returns_own_events(): void
    {
        $owner = User::factory()->create(['role' => UserRole::FSO]);
        $other = User::factory()->create(['role' => UserRole::FSO]);
        SamplingEvent::factory()->count(2)->create(['fso_id' => $owner->id]);
        SamplingEvent::factory()->count(3)->create(['fso_id' => $other->id]);

        Sanctum::actingAs($owner, $owner->role->abilities());

        $this->getJson('/api/v1/sampling-events')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
