<?php

namespace Tests\Unit;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Services\CustodyStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Guards the completeness of the custody state machine now that Phase 4 closes the
 * lifecycle. Reads the transition map reflectively and asserts, mechanically, that:
 *
 *  (a) every PartStatus is reachable — as a transition target, as the COLLECTED
 *      initial state, or as REJECTED (reachable from any non-terminal), and
 *  (b) the terminal states really are terminal (no outgoing transitions).
 */
class TransitionMapCompletenessTest extends TestCase
{
    /**
     * @return array<string, array<int, array<string, array<int, PartStatus>>>>
     */
    private function transitionMap(): array
    {
        $reflection = new ReflectionClass(CustodyStateMachine::class);

        return $reflection->getConstant('TRANSITIONS');
    }

    public function test_every_part_status_is_reachable_for_some_role(): void
    {
        $reachable = [];

        // COLLECTED is the initial state every part is created in.
        $reachable[PartStatus::COLLECTED->value] = true;

        foreach ($this->transitionMap() as $byStatus) {
            foreach ($byStatus as $targets) {
                foreach ($targets as $target) {
                    $reachable[$target->value] = true;
                }
            }
        }

        // REJECTED is reachable from any non-terminal state (generic rule, not in
        // the explicit map).
        $reachable[PartStatus::REJECTED->value] = true;

        foreach (PartStatus::cases() as $status) {
            $this->assertArrayHasKey(
                $status->value,
                $reachable,
                "PartStatus::{$status->value} is unreachable — no role can move a part into it."
            );
        }
    }

    /**
     * @return array<string, array{PartStatus}>
     */
    public static function terminalProvider(): array
    {
        return [
            'REPORT_ISSUED' => [PartStatus::REPORT_ISSUED],
            'DESTROYED' => [PartStatus::DESTROYED],
            'RELEASED_TO_FBO' => [PartStatus::RELEASED_TO_FBO],
            'REJECTED' => [PartStatus::REJECTED],
        ];
    }

    #[DataProvider('terminalProvider')]
    public function test_terminal_states_have_no_outgoing_transitions(PartStatus $terminal): void
    {
        $machine = new CustodyStateMachine();
        $this->assertTrue($machine->isTerminal($terminal), "{$terminal->value} should be terminal.");

        // No role may define an outgoing edge from a terminal state.
        foreach ($this->transitionMap() as $role => $byStatus) {
            $this->assertArrayNotHasKey(
                $terminal->value,
                $byStatus,
                "Terminal state {$terminal->value} must not have outgoing transitions (found under role {$role})."
            );
        }
    }

    public function test_each_role_reaches_its_own_terminal(): void
    {
        // A sanity check that each part type actually ends somewhere terminal.
        $machine = new CustodyStateMachine();
        $expectedTerminals = [
            PartRole::LAB->value => PartStatus::REPORT_ISSUED,
            PartRole::REFERENCE->value => PartStatus::REPORT_ISSUED, // via retest; also DESTROYED
            PartRole::FBO_COPY->value => PartStatus::RELEASED_TO_FBO,
        ];

        $map = $this->transitionMap();

        foreach ($expectedTerminals as $role => $terminal) {
            $targets = collect($map[$role])->flatten()->map(fn (PartStatus $s) => $s->value)->all();
            $this->assertContains(
                $terminal->value,
                $targets,
                "Role {$role} never reaches its terminal {$terminal->value}."
            );
            $this->assertTrue($machine->isTerminal($terminal));
        }
    }
}
