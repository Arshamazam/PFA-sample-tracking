<?php

namespace Tests\Feature;

use App\Enums\LabSection;
use App\Enums\PartStatus;
use App\Enums\SopViolationType;
use App\Enums\UserRole;
use App\Models\SopViolation;
use App\Models\TestCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser(UserRole::ADMIN);
        Sanctum::actingAs($this->admin, $this->admin->role->abilities());
    }

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $this->postJson('/api/v1/admin/users', [
            'name' => 'New Analyst',
            'email' => 'new.analyst@pfa.test',
            'password' => 'secret-password',
            'role' => 'LAB_ANALYST',
        ])->assertStatus(201)->assertJsonPath('data.role', 'LAB_ANALYST');

        $this->assertDatabaseHas('users', ['email' => 'new.analyst@pfa.test', 'role' => 'LAB_ANALYST']);
    }

    public function test_admin_can_deactivate_and_reactivate_a_user(): void
    {
        $user = $this->makeUser(UserRole::FSO);

        $this->patchJson("/api/v1/admin/users/{$user->id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->patchJson("/api/v1/admin/users/{$user->id}", ['is_active' => true])
            ->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $this->patchJson("/api/v1/admin/users/{$this->admin->id}", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors('is_active');

        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_non_admin_cannot_touch_admin_endpoints(): void
    {
        $fso = $this->makeUser(UserRole::FSO);
        Sanctum::actingAs($fso, $fso->role->abilities());

        $this->getJson('/api/v1/admin/users')->assertStatus(403);
        $this->getJson('/api/v1/admin/test-catalog')->assertStatus(403);
        $this->getJson('/api/v1/admin/sop-violations')->assertStatus(403);
    }

    public function test_admin_can_crud_the_test_catalog(): void
    {
        $id = $this->postJson('/api/v1/admin/test-catalog', [
            'food_category' => 'MEAT',
            'lab_section' => 'MICROBIOLOGY',
            'test_name' => 'Meat Microbiological Quality',
            'tat_hours' => 72,
            'parameters' => [
                ['name' => 'Total Plate Count (TPC)', 'unit' => 'CFU/g', 'permissible_limit' => 'max 100000'],
            ],
        ])->assertStatus(201)->json('data.id');

        $this->getJson('/api/v1/admin/test-catalog?food_category=MEAT')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->patchJson("/api/v1/admin/test-catalog/{$id}", ['tat_hours' => 48])
            ->assertOk()->assertJsonPath('data.tat_hours', 48);

        $this->deleteJson("/api/v1/admin/test-catalog/{$id}")->assertOk();
        $this->assertDatabaseMissing('test_catalog', ['id' => $id]);
    }

    public function test_admin_can_filter_and_resolve_sop_violations(): void
    {
        $part = $this->makeLabPart(PartStatus::RECEIVED_REGISTRATION);

        $violation = SopViolation::create([
            'sample_part_id' => $part->id,
            'type' => SopViolationType::COLD_CHAIN_BREACH,
            'details' => ['temperature_c' => 14],
            'detected_at' => now(),
        ]);
        SopViolation::create([
            'sample_part_id' => $part->id,
            'type' => SopViolationType::SAME_DAY_TRANSFER,
            'details' => [],
            'detected_at' => now(),
        ]);

        // Filter by type.
        $this->getJson('/api/v1/admin/sop-violations?type=COLD_CHAIN_BREACH')
            ->assertOk()->assertJsonCount(1, 'data');

        // Unresolved list has both.
        $this->getJson('/api/v1/admin/sop-violations?resolved=0')
            ->assertOk()->assertJsonCount(2, 'data');

        // Resolve one.
        $this->patchJson("/api/v1/admin/sop-violations/{$violation->id}", [
            'resolved' => true,
            'resolution_notes' => 'Cold box replaced; officer briefed.',
        ])->assertOk()->assertJsonPath('data.resolved', true);

        $this->getJson('/api/v1/admin/sop-violations?resolved=1')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->assertNotNull($violation->fresh()->resolved_at);
        $this->assertSame($this->admin->id, $violation->fresh()->resolved_by_id);
    }

    public function test_resolving_requires_notes(): void
    {
        $part = $this->makeLabPart(PartStatus::RECEIVED_REGISTRATION);
        $violation = SopViolation::create([
            'sample_part_id' => $part->id,
            'type' => SopViolationType::OTHER,
            'details' => [],
            'detected_at' => now(),
        ]);

        $this->patchJson("/api/v1/admin/sop-violations/{$violation->id}", ['resolved' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('resolution_notes');
    }
}
