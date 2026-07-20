<?php

namespace Tests\Feature\Panel;

use App\Enums\DisputeStatus;
use App\Enums\LabSection;
use App\Enums\PartStatus;
use App\Enums\UserRole;
use App\Enums\Verdict;
use App\Models\LabResult;
use App\Models\User;
use App\Services\DisputeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

/**
 * Exercises the panel's key actions end-to-end through the WEB routes (session
 * auth, CSRF-free in tests), proving the web controllers drive the shared services.
 */
class PanelWorkflowTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function actingAsRole(UserRole $role): User
    {
        $user = User::factory()->create(['role' => $role, 'must_change_password' => false]);
        $this->actingAs($user);

        return $user;
    }

    public function test_registration_officer_receives_a_sample_through_the_web(): void
    {
        $this->actingAsRole(UserRole::REGISTRATION_OFFICER);
        $part = $this->makeLabPart(PartStatus::IN_TRANSIT);

        $this->post(route('registration.receiving.store'), [
            'qr_token' => $part->qr_token,
            'seal_intact' => 1,
            'seal_number_confirmed' => 1,
            'seal_photo' => UploadedFile::fake()->image('seal.jpg'),
        ])->assertRedirect(route('registration.receiving.create'))->assertSessionHas('status');

        $this->assertSame(PartStatus::RECEIVED_REGISTRATION, $part->fresh()->status);
    }

    public function test_broken_seal_rejects_through_the_web(): void
    {
        $this->actingAsRole(UserRole::REGISTRATION_OFFICER);
        $part = $this->makeLabPart(PartStatus::IN_TRANSIT);

        $this->post(route('registration.receiving.store'), [
            'qr_token' => $part->qr_token,
            'seal_intact' => 0,
            'seal_number_confirmed' => 1,
            'seal_photo' => UploadedFile::fake()->image('seal.jpg'),
            'notes' => 'Seal was torn.',
        ])->assertRedirect();

        $this->assertSame(PartStatus::REJECTED, $part->fresh()->status);
    }

    public function test_verifying_officer_records_a_verdict_through_the_web(): void
    {
        $analyst = User::factory()->create(['role' => UserRole::LAB_ANALYST]);
        $part = $this->makeLabPart(PartStatus::RESULT_ENTERED, blindCode: 'BC-2026-000500');
        LabResult::create([
            'sample_part_id' => $part->id,
            'lab_section' => LabSection::CHEMICAL,
            'analyst_id' => $analyst->id,
            'parameters' => [['name' => 'Fat', 'value' => '2.9', 'within_limit' => false]],
        ]);

        $verifier = $this->actingAsRole(UserRole::VERIFYING_OFFICER);

        // Isolate the verdict action from the queued report job (which, running
        // synchronously in tests, would advance the part on to REPORT_ISSUED).
        \Illuminate\Support\Facades\Bus::fake();

        $this->post(route('verification.verdict', 'BC-2026-000500'), [
            'verdict' => 'UNFIT',
            'notes' => 'Below limit.',
        ])->assertRedirect(route('verification.queue'))->assertSessionHas('status');

        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\GenerateReportPdf::class);

        $part->refresh();
        $this->assertSame(PartStatus::VERIFIED, $part->status);
        $this->assertSame(Verdict::UNFIT, $part->labResult->verdict);
        $this->assertSame($verifier->id, $part->labResult->verified_by_id);
    }

    public function test_maker_checker_is_enforced_on_the_web_verdict(): void
    {
        // The verifier is also the analyst — must be blocked.
        $verifier = $this->actingAsRole(UserRole::VERIFYING_OFFICER);
        $part = $this->makeLabPart(PartStatus::RESULT_ENTERED, blindCode: 'BC-2026-000501');
        LabResult::create([
            'sample_part_id' => $part->id,
            'lab_section' => LabSection::CHEMICAL,
            'analyst_id' => $verifier->id,
            'parameters' => [['name' => 'Fat', 'value' => '3', 'within_limit' => true]],
        ]);

        $this->from(route('verification.show', 'BC-2026-000501'))
            ->post(route('verification.verdict', 'BC-2026-000501'), ['verdict' => 'FIT'])
            ->assertSessionHasErrors('verified_by');

        $this->assertSame(PartStatus::RESULT_ENTERED, $part->fresh()->status);
    }

    public function test_dispute_can_be_filed_and_decided_through_the_web(): void
    {
        // An UNFIT reported event, within the window.
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        $event = $built['event'];

        // Registration officer files.
        $this->actingAsRole(UserRole::REGISTRATION_OFFICER);
        $this->post(route('registration.disputes.store'), [
            'event_code' => $event->event_code,
            'filed_by_name' => 'Muhammad Aslam',
            'filed_by_phone' => '0300-4412233',
        ])->assertRedirect()->assertSessionHas('status');

        $dispute = $event->disputes()->firstOrFail();
        $this->assertSame(DisputeStatus::FILED, $dispute->status);

        // A different verifier decides (accept -> activates the reference).
        $this->actingAsRole(UserRole::VERIFYING_OFFICER);
        $this->post(route('disputes.decide', $dispute), [
            'decision' => 'ACCEPTED',
            'notes' => 'Retest approved.',
        ])->assertRedirect(route('disputes.show', $dispute));

        $dispute->refresh();
        $this->assertSame(DisputeStatus::RETEST_IN_PROGRESS, $dispute->status);
        $this->assertSame(PartStatus::ACTIVATED_FOR_RETEST, $built['reference']->fresh()->status);
        $this->assertNotSame($built['lab']->blind_code, $built['reference']->fresh()->blind_code);
    }

    public function test_registration_officer_can_destroy_an_eligible_reference_through_the_web(): void
    {
        $this->actingAsRole(UserRole::REGISTRATION_OFFICER);
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);
        $built['reference']->update(['destruction_eligible_at' => Carbon::now()->subHour()]);

        $this->post(route('registration.retention.destroy'), [
            'qr_token' => $built['reference']->qr_token,
            'photo' => UploadedFile::fake()->image('d.jpg'),
            'notes' => 'Incinerated per SOP.',
        ])->assertRedirect(route('registration.retention.index'));

        $this->assertSame(PartStatus::DESTROYED, $built['reference']->fresh()->status);
    }
}
