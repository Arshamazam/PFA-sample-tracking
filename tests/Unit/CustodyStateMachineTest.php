<?php

namespace Tests\Unit;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\UserRole;
use App\Exceptions\IllegalTransitionException;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use App\Models\User;
use App\Services\CustodyStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustodyStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private CustodyStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new CustodyStateMachine();
    }

    private function part(PartRole $role, PartStatus $status, bool $perishable = false): SamplePart
    {
        $event = SamplingEvent::factory()->create(['is_perishable' => $perishable]);

        return SamplePart::factory()->for($event, 'samplingEvent')->create([
            'role' => $role,
            'status' => $status,
        ]);
    }

    private function actor(UserRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_lab_part_walks_the_legal_path(): void
    {
        $part = $this->part(PartRole::LAB, PartStatus::COLLECTED);
        $fso = $this->actor(UserRole::FSO);
        $reg = $this->actor(UserRole::REGISTRATION_OFFICER);

        $this->machine->transition($part, PartStatus::SEALED, $fso);
        $this->assertSame(PartStatus::SEALED, $part->fresh()->status);

        $this->machine->transition($part->fresh(), PartStatus::IN_TRANSIT, $fso);
        $this->assertSame(PartStatus::IN_TRANSIT, $part->fresh()->status);

        $this->machine->transition($part->fresh(), PartStatus::RECEIVED_REGISTRATION, $reg);
        $this->assertSame(PartStatus::RECEIVED_REGISTRATION, $part->fresh()->status);
    }

    public function test_reference_part_can_reach_retention(): void
    {
        $part = $this->part(PartRole::REFERENCE, PartStatus::RECEIVED_REGISTRATION);

        $this->machine->transition($part, PartStatus::IN_RETENTION, null);

        $this->assertSame(PartStatus::IN_RETENTION, $part->fresh()->status);
    }

    public function test_fbo_copy_is_released_and_terminal(): void
    {
        $part = $this->part(PartRole::FBO_COPY, PartStatus::SEALED);

        $this->machine->transition($part, PartStatus::RELEASED_TO_FBO, $this->actor(UserRole::FSO));

        $released = $part->fresh();
        $this->assertSame(PartStatus::RELEASED_TO_FBO, $released->status);
        $this->assertTrue($this->machine->isTerminal($released->status));
        $this->assertSame([], $this->machine->allowedTransitions($released));
    }

    public function test_illegal_transition_throws(): void
    {
        $part = $this->part(PartRole::LAB, PartStatus::COLLECTED);

        $this->expectException(IllegalTransitionException::class);
        $this->machine->transition($part, PartStatus::IN_TRANSIT, $this->actor(UserRole::FSO));
    }

    public function test_fbo_copy_cannot_go_into_transit(): void
    {
        $part = $this->part(PartRole::FBO_COPY, PartStatus::SEALED);

        $this->expectException(IllegalTransitionException::class);
        $this->machine->transition($part, PartStatus::IN_TRANSIT, $this->actor(UserRole::FSO));
    }

    public function test_cold_chain_guard_fires_only_for_perishable(): void
    {
        // Non-perishable: no temperature needed.
        $ok = $this->part(PartRole::LAB, PartStatus::SEALED, perishable: false);
        $this->machine->transition($ok, PartStatus::IN_TRANSIT, $this->actor(UserRole::FSO));
        $this->assertSame(PartStatus::IN_TRANSIT, $ok->fresh()->status);

        // Perishable without temperature: rejected.
        $perishable = $this->part(PartRole::LAB, PartStatus::SEALED, perishable: true);
        $this->expectException(IllegalTransitionException::class);
        $this->machine->transition($perishable, PartStatus::IN_TRANSIT, $this->actor(UserRole::FSO));
    }

    public function test_cold_chain_passes_with_temperature(): void
    {
        $perishable = $this->part(PartRole::LAB, PartStatus::SEALED, perishable: true);

        $event = $this->machine->transition($perishable, PartStatus::IN_TRANSIT, $this->actor(UserRole::FSO), [
            'temperature_c' => 4.5,
        ]);

        $this->assertSame(PartStatus::IN_TRANSIT, $perishable->fresh()->status);
        $this->assertEquals(4.5, (float) $event->temperature_c);
    }

    public function test_rejected_requires_notes(): void
    {
        $part = $this->part(PartRole::LAB, PartStatus::SEALED);

        $this->expectException(IllegalTransitionException::class);
        $this->machine->transition($part, PartStatus::REJECTED, $this->actor(UserRole::FSO));
    }

    public function test_rejected_succeeds_with_notes(): void
    {
        $part = $this->part(PartRole::LAB, PartStatus::SEALED);

        $this->machine->transition($part, PartStatus::REJECTED, $this->actor(UserRole::FSO), [
            'notes' => 'Seal found broken on arrival.',
        ]);

        $this->assertSame(PartStatus::REJECTED, $part->fresh()->status);
    }

    public function test_received_registration_requires_registration_officer(): void
    {
        $part = $this->part(PartRole::LAB, PartStatus::IN_TRANSIT);

        $this->expectException(IllegalTransitionException::class);
        // An FSO may not move a part into RECEIVED_REGISTRATION.
        $this->machine->transition($part, PartStatus::RECEIVED_REGISTRATION, $this->actor(UserRole::FSO));
    }

    public function test_system_actor_bypasses_role_requirement(): void
    {
        $part = $this->part(PartRole::LAB, PartStatus::IN_TRANSIT);

        $this->machine->transition($part, PartStatus::RECEIVED_REGISTRATION, null);

        $this->assertSame(PartStatus::RECEIVED_REGISTRATION, $part->fresh()->status);
    }

    public function test_transition_writes_a_custody_event_and_denormalizes_status(): void
    {
        $part = $this->part(PartRole::LAB, PartStatus::COLLECTED);

        $this->machine->transition($part, PartStatus::SEALED, $this->actor(UserRole::FSO), [
            'notes' => 'sealed',
        ]);

        $this->assertDatabaseHas('custody_events', [
            'sample_part_id' => $part->id,
            'status' => PartStatus::SEALED->value,
        ]);
        $this->assertSame(PartStatus::SEALED, $part->fresh()->status);
    }

    public function test_allowed_transitions_include_rejected_for_non_terminal(): void
    {
        $part = $this->part(PartRole::LAB, PartStatus::SEALED);

        $allowed = $this->machine->allowedTransitions($part);

        $this->assertContains(PartStatus::IN_TRANSIT, $allowed);
        $this->assertContains(PartStatus::REJECTED, $allowed);
    }
}
