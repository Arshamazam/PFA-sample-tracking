<?php

namespace Tests\Feature;

use App\Enums\LabSection;
use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\SopViolationType;
use App\Enums\UserRole;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->officer = $this->makeUser(UserRole::REGISTRATION_OFFICER);
        Sanctum::actingAs($this->officer, $this->officer->role->abilities());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function receive(SamplePart $part, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/registration/receive', array_merge([
            'qr_token' => $part->qr_token,
            'seal_intact' => true,
            'seal_number_confirmed' => true,
            'seal_photo' => UploadedFile::fake()->image('seal.jpg'),
        ], $overrides));
    }

    public function test_receiving_an_intact_sample_accepts_it(): void
    {
        $part = $this->makeLabPart(PartStatus::IN_TRANSIT);

        $this->receive($part)->assertOk()->assertJsonPath('data.status', 'RECEIVED_REGISTRATION');

        $this->assertSame(PartStatus::RECEIVED_REGISTRATION, $part->fresh()->status);
        $this->assertDatabaseCount('sop_violations', 0);
    }

    public function test_broken_seal_rejects_the_sample(): void
    {
        $part = $this->makeLabPart(PartStatus::IN_TRANSIT);

        $this->receive($part, [
            'seal_intact' => false,
            'notes' => 'Seal was torn on arrival.',
        ])->assertOk()->assertJsonPath('data.status', 'REJECTED');

        $this->assertSame(PartStatus::REJECTED, $part->fresh()->status);
    }

    public function test_seal_number_mismatch_rejects_the_sample(): void
    {
        $part = $this->makeLabPart(PartStatus::IN_TRANSIT);

        $this->receive($part, [
            'seal_number_confirmed' => false,
            'notes' => 'Seal number does not match the record.',
        ])->assertOk()->assertJsonPath('data.status', 'REJECTED');
    }

    public function test_rejection_requires_notes(): void
    {
        $part = $this->makeLabPart(PartStatus::IN_TRANSIT);

        $this->receive($part, ['seal_intact' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors('notes');

        // Unchanged — the rejection never happened.
        $this->assertSame(PartStatus::IN_TRANSIT, $part->fresh()->status);
    }

    public function test_perishable_sample_requires_a_temperature(): void
    {
        $part = $this->makeLabPart(PartStatus::IN_TRANSIT, perishable: true);

        $this->receive($part)->assertStatus(422);

        $this->assertSame(PartStatus::IN_TRANSIT, $part->fresh()->status);
    }

    public function test_out_of_range_temperature_is_accepted_but_flagged(): void
    {
        $part = $this->makeLabPart(PartStatus::IN_TRANSIT, perishable: true);

        $this->receive($part, ['temperature_c' => 14.2])
            ->assertOk()
            ->assertJsonPath('data.status', 'RECEIVED_REGISTRATION');

        $this->assertDatabaseHas('sop_violations', [
            'sample_part_id' => $part->id,
            'type' => SopViolationType::COLD_CHAIN_BREACH->value,
        ]);
    }

    public function test_late_receipt_is_accepted_but_flagged_as_same_day_violation(): void
    {
        // Collected yesterday, received today.
        $part = $this->makeLabPart(PartStatus::IN_TRANSIT);
        $part->samplingEvent->update(['collected_at' => Carbon::now()->subDay()]);

        $this->receive($part)->assertOk()->assertJsonPath('data.status', 'RECEIVED_REGISTRATION');

        $this->assertDatabaseHas('sop_violations', [
            'sample_part_id' => $part->id,
            'type' => SopViolationType::SAME_DAY_TRANSFER->value,
        ]);
    }

    public function test_receipt_after_the_same_day_deadline_is_flagged(): void
    {
        Setting::updateOrCreate(['key' => 'same_day_transfer_deadline'], ['value' => '20:00']);
        // Collected today at 09:00; it is now 21:30 — past the deadline.
        Carbon::setTestNow(Carbon::today()->setTime(21, 30));

        $part = $this->makeLabPart(PartStatus::IN_TRANSIT);
        $part->samplingEvent->update(['collected_at' => Carbon::today()->setTime(9, 0)]);

        $this->receive($part)->assertOk();

        $this->assertDatabaseHas('sop_violations', [
            'sample_part_id' => $part->id,
            'type' => SopViolationType::SAME_DAY_TRANSFER->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_on_time_receipt_is_not_flagged(): void
    {
        Setting::updateOrCreate(['key' => 'same_day_transfer_deadline'], ['value' => '20:00']);
        Carbon::setTestNow(Carbon::today()->setTime(15, 0));

        $part = $this->makeLabPart(PartStatus::IN_TRANSIT);
        $part->samplingEvent->update(['collected_at' => Carbon::today()->setTime(9, 0)]);

        $this->receive($part)->assertOk();

        $this->assertDatabaseCount('sop_violations', 0);

        Carbon::setTestNow();
    }

    public function test_reference_part_is_received_then_retained(): void
    {
        $event = SamplingEvent::factory()->create(['finalized_at' => now()]);
        $part = SamplePart::factory()->for($event, 'samplingEvent')->create([
            'role' => PartRole::REFERENCE,
            'status' => PartStatus::IN_TRANSIT,
        ]);

        $this->receive($part)->assertOk()->assertJsonPath('data.status', 'RECEIVED_REGISTRATION');

        $this->postJson('/api/v1/registration/retain', [
            'qr_token' => $part->qr_token,
            'storage_location' => 'Retention Cabinet B, Shelf 3',
        ])->assertOk()->assertJsonPath('data.status', 'IN_RETENTION');
    }

    public function test_lab_part_cannot_be_retained(): void
    {
        $part = $this->makeLabPart(PartStatus::RECEIVED_REGISTRATION);

        $this->postJson('/api/v1/registration/retain', [
            'qr_token' => $part->qr_token,
            'storage_location' => 'Cabinet B',
        ])->assertStatus(422)->assertJsonValidationErrors('qr_token');
    }

    public function test_blind_coding_assigns_a_sequential_code(): void
    {
        $first = $this->makeLabPart(PartStatus::RECEIVED_REGISTRATION);
        $second = $this->makeLabPart(PartStatus::RECEIVED_REGISTRATION);

        $a = $this->postJson('/api/v1/registration/blind-code', ['qr_token' => $first->qr_token])
            ->assertOk()->json('data.blind_code');
        $b = $this->postJson('/api/v1/registration/blind-code', ['qr_token' => $second->qr_token])
            ->assertOk()->json('data.blind_code');

        $year = now()->year;
        $this->assertSame("BC-{$year}-000001", $a);
        $this->assertSame("BC-{$year}-000002", $b);
        $this->assertSame(PartStatus::BLIND_CODED, $first->fresh()->status);
    }

    public function test_reference_part_cannot_be_blind_coded(): void
    {
        $event = SamplingEvent::factory()->create();
        $part = SamplePart::factory()->for($event, 'samplingEvent')->create([
            'role' => PartRole::REFERENCE,
            'status' => PartStatus::RECEIVED_REGISTRATION,
        ]);

        // Only the LAB part is blind-coded in this phase.
        $this->postJson('/api/v1/registration/blind-code', ['qr_token' => $part->qr_token])
            ->assertStatus(422);
    }

    public function test_assign_section_records_the_section_and_advances(): void
    {
        $part = $this->makeLabPart(PartStatus::BLIND_CODED, blindCode: 'BC-2026-000900');

        $this->postJson('/api/v1/registration/assign-section', [
            'qr_token' => $part->qr_token,
            'lab_section' => 'MICROBIOLOGY',
        ])->assertOk()->assertJsonPath('data.status', 'ASSIGNED_TO_SECTION');

        $this->assertSame(LabSection::MICROBIOLOGY, $part->fresh()->labResult->lab_section);
    }

    public function test_suggest_section_uses_the_test_catalog(): void
    {
        $part = $this->makeLabPart(PartStatus::BLIND_CODED, foodCategory: 'OIL_GHEE');
        $this->makeCatalogEntry('OIL_GHEE', LabSection::FAT_OIL);

        $this->getJson('/api/v1/registration/suggest-section?qr_token='.$part->qr_token)
            ->assertOk()
            ->assertJsonPath('data.food_category', 'OIL_GHEE')
            ->assertJsonPath('data.suggested_lab_section', 'FAT_OIL');
    }

    public function test_other_roles_cannot_use_registration_endpoints(): void
    {
        $analyst = $this->makeUser(UserRole::LAB_ANALYST);
        Sanctum::actingAs($analyst, $analyst->role->abilities());

        $part = $this->makeLabPart(PartStatus::IN_TRANSIT);

        $this->receive($part)->assertStatus(403);
    }
}
