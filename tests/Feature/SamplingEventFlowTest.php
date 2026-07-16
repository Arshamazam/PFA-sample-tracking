<?php

namespace Tests\Feature;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\UserRole;
use App\Models\Premises;
use App\Models\SamplingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SamplingEventFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $fso;
    private Premises $premises;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->fso = User::factory()->create(['role' => UserRole::FSO]);
        $this->premises = Premises::factory()->create(['license_no' => 'PFA-LHR-2025-40001']);
        Sanctum::actingAs($this->fso, $this->fso->role->abilities());
    }

    private function createDraftEvent(bool $perishable = true): string
    {
        return $this->postJson('/api/v1/sampling-events', [
            'premises_license' => $this->premises->license_no,
            'food_item' => 'Loose Milk',
            'is_perishable' => $perishable,
            'collected_at' => '2026-07-15T09:00:00Z',
            'witness_name' => 'Shop Assistant',
        ])->assertStatus(201)->json('data.id');
    }

    private function addPart(string $eventId, PartRole $role): void
    {
        $this->postJson("/api/v1/sampling-events/{$eventId}/parts", [
            'role' => $role->value,
            'seal_number' => 'SEAL-'.$role->value,
            'seal_photo' => UploadedFile::fake()->image("seal-{$role->value}.jpg"),
        ])->assertStatus(201);
    }

    private function uploadSignature(string $eventId): void
    {
        $this->patchJson("/api/v1/sampling-events/{$eventId}", [
            'witness_signature' => UploadedFile::fake()->image('sig.jpg'),
        ])->assertOk();
    }

    public function test_full_happy_path_create_parts_finalize_and_scan(): void
    {
        $eventId = $this->createDraftEvent(perishable: true);

        foreach ([PartRole::LAB, PartRole::REFERENCE, PartRole::FBO_COPY] as $role) {
            $this->addPart($eventId, $role);
        }

        $this->uploadSignature($eventId);

        $finalize = $this->postJson("/api/v1/sampling-events/{$eventId}/finalize")->assertOk();
        $finalize->assertJsonPath('data.status', 'FINALIZED');

        // All three parts are now SEALED.
        $parts = collect($finalize->json('data.parts'));
        $this->assertCount(3, $parts);
        $this->assertTrue($parts->every(fn ($p) => $p['status'] === 'SEALED'));

        // Scan the LAB part into transit WITH a temperature (perishable).
        $labToken = $parts->firstWhere('role', 'LAB')['qr_token'];
        $scan = $this->postJson('/api/v1/custody/scan', [
            'qr_token' => $labToken,
            'to_status' => 'IN_TRANSIT',
            'latitude' => 31.5204,
            'longitude' => 74.3587,
            'temperature_c' => 4.5,
        ])->assertOk();

        $this->assertSame('IN_TRANSIT', $scan->json('data.status'));

        // Custody trail: COLLECTED -> SEALED -> IN_TRANSIT.
        $timeline = $this->getJson("/api/v1/custody/parts/{$labToken}")->assertOk();
        $statuses = collect($timeline->json('data.part.custody_events'))->pluck('status')->all();
        $this->assertSame(['COLLECTED', 'SEALED', 'IN_TRANSIT'], $statuses);

        $this->assertDatabaseHas('sample_parts', [
            'qr_token' => $labToken,
            'status' => PartStatus::IN_TRANSIT->value,
        ]);
    }

    public function test_finalize_fails_with_only_two_parts(): void
    {
        $eventId = $this->createDraftEvent();
        $this->addPart($eventId, PartRole::LAB);
        $this->addPart($eventId, PartRole::REFERENCE);
        $this->uploadSignature($eventId);

        $this->postJson("/api/v1/sampling-events/{$eventId}/finalize")
            ->assertStatus(422)
            ->assertJsonValidationErrors('parts');

        $this->assertNull(SamplingEvent::find($eventId)->finalized_at);
    }

    public function test_adding_a_duplicate_role_is_rejected(): void
    {
        $eventId = $this->createDraftEvent();
        $this->addPart($eventId, PartRole::LAB);

        $this->postJson("/api/v1/sampling-events/{$eventId}/parts", [
            'role' => 'LAB',
            'seal_number' => 'SEAL-DUP',
            'seal_photo' => UploadedFile::fake()->image('dup.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_finalize_fails_without_witness_signature(): void
    {
        $eventId = $this->createDraftEvent();
        foreach ([PartRole::LAB, PartRole::REFERENCE, PartRole::FBO_COPY] as $role) {
            $this->addPart($eventId, $role);
        }
        // No signature uploaded.

        $this->postJson("/api/v1/sampling-events/{$eventId}/finalize")
            ->assertStatus(422)
            ->assertJsonValidationErrors('witness_signature');
    }

    public function test_patch_after_finalize_is_rejected(): void
    {
        $eventId = $this->createDraftEvent();
        foreach ([PartRole::LAB, PartRole::REFERENCE, PartRole::FBO_COPY] as $role) {
            $this->addPart($eventId, $role);
        }
        $this->uploadSignature($eventId);
        $this->postJson("/api/v1/sampling-events/{$eventId}/finalize")->assertOk();

        $this->patchJson("/api/v1/sampling-events/{$eventId}", [
            'witness_name' => 'Changed Name',
        ])->assertStatus(422)->assertJsonValidationErrors('event');
    }

    public function test_perishable_scan_without_temperature_is_rejected(): void
    {
        $eventId = $this->createDraftEvent(perishable: true);
        foreach ([PartRole::LAB, PartRole::REFERENCE, PartRole::FBO_COPY] as $role) {
            $this->addPart($eventId, $role);
        }
        $this->uploadSignature($eventId);
        $parts = collect($this->postJson("/api/v1/sampling-events/{$eventId}/finalize")->json('data.parts'));
        $labToken = $parts->firstWhere('role', 'LAB')['qr_token'];

        $this->postJson('/api/v1/custody/scan', [
            'qr_token' => $labToken,
            'to_status' => 'IN_TRANSIT',
        ])->assertStatus(422)->assertJsonValidationErrors('to_status');
    }
}
