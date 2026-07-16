<?php

namespace Tests\Unit;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\SopViolationType;
use App\Enums\UserRole;
use App\Exceptions\IllegalTransitionException;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use App\Services\CustodyStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

/**
 * Phase 3 additions to the custody state machine: the lab chain, its role guards,
 * and cold-chain breach flagging.
 */
class CustodyStateMachinePhase3Test extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    private CustodyStateMachine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->machine = new CustodyStateMachine();
    }

    private function partAt(PartStatus $status, bool $perishable = false): SamplePart
    {
        $event = SamplingEvent::factory()->create(['is_perishable' => $perishable]);

        return SamplePart::factory()->for($event, 'samplingEvent')->create([
            'role' => PartRole::LAB,
            'status' => $status,
        ]);
    }

    public function test_lab_part_walks_the_full_technical_wing_path(): void
    {
        $part = $this->partAt(PartStatus::IN_TRANSIT);
        $reg = $this->makeUser(UserRole::REGISTRATION_OFFICER);
        $analyst = $this->makeUser(UserRole::LAB_ANALYST);
        $verifier = $this->makeUser(UserRole::VERIFYING_OFFICER);

        $steps = [
            [PartStatus::RECEIVED_REGISTRATION, $reg],
            [PartStatus::BLIND_CODED, $reg],
            [PartStatus::ASSIGNED_TO_SECTION, $reg],
            [PartStatus::TESTING, $analyst],
            [PartStatus::RESULT_ENTERED, $analyst],
            [PartStatus::VERIFIED, $verifier],
            [PartStatus::REPORT_ISSUED, null], // system actor (PDF job)
        ];

        foreach ($steps as [$status, $actor]) {
            $this->machine->transition($part->fresh(), $status, $actor);
            $this->assertSame($status, $part->fresh()->status, "failed moving into {$status->value}");
        }

        $this->assertTrue($this->machine->isTerminal(PartStatus::REPORT_ISSUED));
        $this->assertSame([], $this->machine->allowedTransitions($part->fresh()));
    }

    public function test_reference_part_goes_into_retention(): void
    {
        $event = SamplingEvent::factory()->create();
        $part = SamplePart::factory()->for($event, 'samplingEvent')->create([
            'role' => PartRole::REFERENCE,
            'status' => PartStatus::RECEIVED_REGISTRATION,
        ]);

        $this->machine->transition($part, PartStatus::IN_RETENTION, $this->makeUser(UserRole::REGISTRATION_OFFICER));

        $this->assertSame(PartStatus::IN_RETENTION, $part->fresh()->status);
    }

    /**
     * @return array<string, array{PartStatus, UserRole}>
     */
    public static function wrongRoleProvider(): array
    {
        return [
            'blind coding is registration-only' => [PartStatus::BLIND_CODED, UserRole::LAB_ANALYST],
            'section assignment is registration-only' => [PartStatus::ASSIGNED_TO_SECTION, UserRole::LAB_ANALYST],
            'testing is analyst-only' => [PartStatus::TESTING, UserRole::REGISTRATION_OFFICER],
            'result entry is analyst-only' => [PartStatus::RESULT_ENTERED, UserRole::VERIFYING_OFFICER],
            'verification is verifier-only' => [PartStatus::VERIFIED, UserRole::LAB_ANALYST],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('wrongRoleProvider')]
    public function test_role_guards_reject_the_wrong_actor(PartStatus $target, UserRole $wrongRole): void
    {
        $from = match ($target) {
            PartStatus::BLIND_CODED => PartStatus::RECEIVED_REGISTRATION,
            PartStatus::ASSIGNED_TO_SECTION => PartStatus::BLIND_CODED,
            PartStatus::TESTING => PartStatus::ASSIGNED_TO_SECTION,
            PartStatus::RESULT_ENTERED => PartStatus::TESTING,
            PartStatus::VERIFIED => PartStatus::RESULT_ENTERED,
            default => PartStatus::IN_TRANSIT,
        };

        $part = $this->partAt($from);

        $this->expectException(IllegalTransitionException::class);
        $this->machine->transition($part, $target, $this->makeUser($wrongRole));
    }

    public function test_verifying_officer_may_return_work_to_testing_but_analyst_may_not(): void
    {
        // The verifier returns RESULT_ENTERED -> TESTING (transition-specific override).
        $part = $this->partAt(PartStatus::RESULT_ENTERED);
        $this->machine->transition($part, PartStatus::TESTING, $this->makeUser(UserRole::VERIFYING_OFFICER), [
            'notes' => 'Please recheck the fat reading.',
        ]);
        $this->assertSame(PartStatus::TESTING, $part->fresh()->status);

        // An analyst cannot use that same return path.
        $other = $this->partAt(PartStatus::RESULT_ENTERED);
        $this->expectException(IllegalTransitionException::class);
        $this->machine->transition($other, PartStatus::TESTING, $this->makeUser(UserRole::LAB_ANALYST));
    }

    public function test_report_issued_is_terminal_and_cannot_be_revisited(): void
    {
        $part = $this->partAt(PartStatus::REPORT_ISSUED);

        $this->expectException(IllegalTransitionException::class);
        $this->machine->transition($part, PartStatus::TESTING, $this->makeUser(UserRole::LAB_ANALYST));
    }

    public function test_lab_chain_cannot_be_skipped(): void
    {
        $part = $this->partAt(PartStatus::RECEIVED_REGISTRATION);

        // RECEIVED_REGISTRATION -> TESTING skips blind coding and assignment.
        $this->expectException(IllegalTransitionException::class);
        $this->machine->transition($part, PartStatus::TESTING, $this->makeUser(UserRole::LAB_ANALYST));
    }

    public function test_in_range_temperature_records_no_violation(): void
    {
        $part = $this->partAt(PartStatus::IN_TRANSIT, perishable: true);

        $this->machine->transition($part, PartStatus::RECEIVED_REGISTRATION, $this->makeUser(UserRole::REGISTRATION_OFFICER), [
            'temperature_c' => 4.0,
        ]);

        $this->assertSame(PartStatus::RECEIVED_REGISTRATION, $part->fresh()->status);
        $this->assertDatabaseCount('sop_violations', 0);
    }

    public function test_out_of_range_temperature_is_accepted_but_flagged(): void
    {
        $part = $this->partAt(PartStatus::IN_TRANSIT, perishable: true);

        $this->machine->transition($part, PartStatus::RECEIVED_REGISTRATION, $this->makeUser(UserRole::REGISTRATION_OFFICER), [
            'temperature_c' => 15.5, // configured range is 0-8
        ]);

        // Accepted...
        $this->assertSame(PartStatus::RECEIVED_REGISTRATION, $part->fresh()->status);
        // ...but flagged.
        $this->assertDatabaseHas('sop_violations', [
            'sample_part_id' => $part->id,
            'type' => SopViolationType::COLD_CHAIN_BREACH->value,
        ]);
    }

    public function test_non_perishable_out_of_range_temperature_is_not_flagged(): void
    {
        $part = $this->partAt(PartStatus::IN_TRANSIT, perishable: false);

        $this->machine->transition($part, PartStatus::RECEIVED_REGISTRATION, $this->makeUser(UserRole::REGISTRATION_OFFICER), [
            'temperature_c' => 30.0,
        ]);

        $this->assertDatabaseCount('sop_violations', 0);
    }
}
