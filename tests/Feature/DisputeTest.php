<?php

namespace Tests\Feature;

use App\Enums\DisputeStatus;
use App\Enums\PartStatus;
use App\Enums\UserRole;
use App\Enums\Verdict;
use App\Models\Dispute;
use App\Models\Setting;
use App\Models\User;
use App\Services\DisputeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

class DisputeTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake('local');
    }

    private function service(): DisputeService
    {
        return app(DisputeService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function filing(string $eventCode, array $overrides = []): array
    {
        return array_merge([
            'event_code' => $eventCode,
            'filed_by_name' => 'Muhammad Aslam',
            'filed_by_phone' => '0300-1234567',
            'filed_by_cnic' => '35201-1234567-1',
            'reason' => 'We believe the milk sample was mishandled.',
        ], $overrides);
    }

    // --- §2 filing rules (service level) ---------------------------------

    public function test_fit_verdict_cannot_be_disputed(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);

        $this->expectException(ValidationException::class);
        $this->service()->file($this->filing($built['event']->event_code));
    }

    public function test_dispute_can_be_filed_within_the_window(): void
    {
        $built = $this->makeReportedEvent(verdictAt: Carbon::now()->subDays(3));

        $dispute = $this->service()->file($this->filing($built['event']->event_code));

        $this->assertSame(DisputeStatus::FILED, $dispute->status);
        $this->assertDatabaseHas('disputes', ['sampling_event_id' => $built['event']->id, 'status' => 'FILED']);
    }

    public function test_window_boundary_is_enforced_to_the_second(): void
    {
        // Window is 7 days. Verdict issued exactly 7 days ago minus/plus a second.
        Setting::updateOrCreate(['key' => 'dispute_window_days'], ['value' => '7']);

        $verdictAt = Carbon::create(2026, 7, 1, 12, 0, 0);

        // Just inside: now = verdict + 7 days - 1 second.
        Carbon::setTestNow($verdictAt->copy()->addDays(7)->subSecond());
        $inWindow = $this->makeReportedEvent(verdictAt: $verdictAt);
        $dispute = $this->service()->file($this->filing($inWindow['event']->event_code));
        $this->assertSame(DisputeStatus::FILED, $dispute->status);

        // Just outside: now = verdict + 7 days + 1 second.
        Carbon::setTestNow($verdictAt->copy()->addDays(7)->addSecond());
        $expired = $this->makeReportedEvent(verdictAt: $verdictAt);
        try {
            $this->service()->file($this->filing($expired['event']->event_code));
            $this->fail('Expected the dispute window to be closed.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('dispute window closed', strtolower($e->getMessage()));
        }

        Carbon::setTestNow();
    }

    public function test_only_one_open_dispute_per_event(): void
    {
        $built = $this->makeReportedEvent(verdictAt: Carbon::now()->subDay());

        $this->service()->file($this->filing($built['event']->event_code));

        $this->expectException(ValidationException::class);
        $this->service()->file($this->filing($built['event']->event_code, ['filed_by_name' => 'Someone Else']));
    }

    public function test_a_closed_or_rejected_dispute_does_not_reopen_the_right_after_the_window(): void
    {
        Setting::updateOrCreate(['key' => 'dispute_window_days'], ['value' => '7']);
        $verdictAt = Carbon::now()->subDays(3);
        $built = $this->makeReportedEvent(verdictAt: $verdictAt);

        // File then reject inside the window.
        $dispute = $this->service()->file($this->filing($built['event']->event_code));
        $dispute->update(['status' => DisputeStatus::REJECTED]);

        // Now the window lapses.
        Carbon::setTestNow(Carbon::now()->addDays(10));
        try {
            $this->service()->file($this->filing($built['event']->event_code, ['filed_by_name' => 'Second Attempt']));
            $this->fail('Expected the window to be closed.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('dispute window closed', strtolower($e->getMessage()));
        }
        Carbon::setTestNow();
    }

    public function test_missing_reference_part_blocks_filing(): void
    {
        $built = $this->makeReportedEvent(verdictAt: Carbon::now()->subDay(), withReference: false);

        $this->expectException(ValidationException::class);
        $this->service()->file($this->filing($built['event']->event_code));
    }

    public function test_destroyed_reference_blocks_filing(): void
    {
        $built = $this->makeReportedEvent(
            verdictAt: Carbon::now()->subDay(),
            referenceStatus: PartStatus::DESTROYED,
        );

        $this->expectException(ValidationException::class);
        $this->service()->file($this->filing($built['event']->event_code));
    }

    // --- §2 filing endpoint (auth) ---------------------------------------

    public function test_registration_officer_can_file_a_dispute(): void
    {
        $built = $this->makeReportedEvent(verdictAt: Carbon::now()->subDay());
        $officer = $this->makeUser(UserRole::REGISTRATION_OFFICER);
        Sanctum::actingAs($officer, $officer->role->abilities());

        $this->postJson('/api/v1/disputes', $this->filing($built['event']->event_code))
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'FILED');
    }

    public function test_analyst_cannot_file_a_dispute(): void
    {
        $built = $this->makeReportedEvent(verdictAt: Carbon::now()->subDay());
        $analyst = $this->makeUser(UserRole::LAB_ANALYST);
        Sanctum::actingAs($analyst, $analyst->role->abilities());

        $this->postJson('/api/v1/disputes', $this->filing($built['event']->event_code))
            ->assertStatus(403);
    }

    // --- §3 decision + activation ----------------------------------------

    public function test_maker_checker_blocks_the_original_verifier_from_deciding(): void
    {
        $built = $this->makeReportedEvent(verdictAt: Carbon::now()->subDay());
        $dispute = $this->service()->file($this->filing($built['event']->event_code));

        // The original verifier also happens to hold VERIFYING_OFFICER — still blocked.
        Sanctum::actingAs($built['verifier'], $built['verifier']->role->abilities());

        $this->postJson("/api/v1/disputes/{$dispute->id}/decide", [
            'decision' => 'ACCEPTED',
            'notes' => 'Approving the retest.',
        ])->assertStatus(422)->assertJsonValidationErrors('decided_by');

        $this->assertSame(DisputeStatus::FILED, $dispute->fresh()->status);
    }

    public function test_accepting_a_dispute_activates_the_reference_with_a_fresh_blind_code(): void
    {
        $built = $this->makeReportedEvent(verdictAt: Carbon::now()->subDay());
        $dispute = $this->service()->file($this->filing($built['event']->event_code));

        $decider = $this->makeUser(UserRole::VERIFYING_OFFICER);
        Sanctum::actingAs($decider, $decider->role->abilities());

        $this->postJson("/api/v1/disputes/{$dispute->id}/decide", [
            'decision' => 'ACCEPTED',
            'notes' => 'Retest approved.',
        ])->assertOk()->assertJsonPath('data.status', 'RETEST_IN_PROGRESS');

        $reference = $built['reference']->fresh();
        $this->assertSame(PartStatus::ACTIVATED_FOR_RETEST, $reference->status);
        $this->assertNotNull($reference->blind_code);
        // Fresh code, distinct from the original LAB part's.
        $this->assertNotSame($built['lab']->blind_code, $reference->blind_code);
        // Section copied from the original result.
        $this->assertSame($built['lab']->labResult->lab_section, $reference->labResult->lab_section);
        // The activation event carries the dispute id.
        $this->assertDatabaseHas('custody_events', [
            'sample_part_id' => $reference->id,
            'status' => PartStatus::ACTIVATED_FOR_RETEST->value,
        ]);
    }

    public function test_rejecting_a_dispute_does_not_touch_the_reference(): void
    {
        $built = $this->makeReportedEvent(verdictAt: Carbon::now()->subDay());
        $dispute = $this->service()->file($this->filing($built['event']->event_code));

        $decider = $this->makeUser(UserRole::VERIFYING_OFFICER);
        Sanctum::actingAs($decider, $decider->role->abilities());

        $this->postJson("/api/v1/disputes/{$dispute->id}/decide", [
            'decision' => 'REJECTED',
            'notes' => 'Insufficient grounds.',
        ])->assertOk()->assertJsonPath('data.status', 'REJECTED');

        $this->assertSame(PartStatus::IN_RETENTION, $built['reference']->fresh()->status);
    }

    public function test_decision_requires_notes(): void
    {
        $built = $this->makeReportedEvent(verdictAt: Carbon::now()->subDay());
        $dispute = $this->service()->file($this->filing($built['event']->event_code));
        $decider = $this->makeUser(UserRole::VERIFYING_OFFICER);
        Sanctum::actingAs($decider, $decider->role->abilities());

        $this->postJson("/api/v1/disputes/{$dispute->id}/decide", ['decision' => 'ACCEPTED'])
            ->assertStatus(422)->assertJsonValidationErrors('notes');
    }

    public function test_full_retest_flow_closes_the_dispute_and_keeps_both_results(): void
    {
        $built = $this->makeReportedEvent(verdictAt: Carbon::now()->subDay());
        $event = $built['event'];
        $originalBlind = $built['lab']->blind_code;

        // File + accept.
        $officer = $this->makeUser(UserRole::REGISTRATION_OFFICER);
        Sanctum::actingAs($officer, $officer->role->abilities());
        $dispute = $this->postJson('/api/v1/disputes', $this->filing($event->event_code))->json('data.id');

        $decider = $this->makeUser(UserRole::VERIFYING_OFFICER);
        Sanctum::actingAs($decider, $decider->role->abilities());
        $retestBlind = $this->postJson("/api/v1/disputes/{$dispute}/decide", [
            'decision' => 'ACCEPTED', 'notes' => 'Approved.',
        ])->assertOk()->json();
        // Grab the fresh blind code off the reference part.
        $retestBlindCode = $built['reference']->fresh()->blind_code;
        $this->assertNotSame($originalBlind, $retestBlindCode);

        // Analyst runs the retest — a DIFFERENT analyst from the original.
        $analyst = $this->makeUser(UserRole::LAB_ANALYST);
        Sanctum::actingAs($analyst, $analyst->role->abilities());
        $this->postJson("/api/v1/lab/{$retestBlindCode}/start")->assertOk();
        $this->postJson("/api/v1/lab/{$retestBlindCode}/results", [
            'parameters' => [
                ['name' => 'Fat', 'value' => '3.7', 'unit' => '%', 'permissible_limit' => 'min 3.5', 'within_limit' => true],
            ],
            'report_photo' => \Illuminate\Http\UploadedFile::fake()->image('retest.jpg'),
        ])->assertOk();

        // A DIFFERENT verifier signs off the retest (FIT this time).
        $verifier2 = $this->makeUser(UserRole::VERIFYING_OFFICER);
        Sanctum::actingAs($verifier2, $verifier2->role->abilities());
        $this->postJson("/api/v1/verification/{$retestBlindCode}/verdict", ['verdict' => 'FIT'])->assertOk();

        // Dispute closed, linked to the retest result.
        $dispute = Dispute::find($dispute);
        $this->assertSame(DisputeStatus::CLOSED, $dispute->status);
        $this->assertNotNull($dispute->retest_lab_result_id);

        // Both results survive on the event detail, and the retest verdict wins.
        Sanctum::actingAs($decider, $decider->role->abilities());
        $detail = $this->getJson("/api/v1/events/{$event->id}/detail")->assertOk()->json('data');
        $this->assertSame('UNFIT', $detail['original_result']['verdict']);
        $this->assertSame('FIT', $detail['retest_result']['verdict']);
        $this->assertSame('FIT', $detail['final_verdict']);
        $this->assertSame('RETEST', $detail['final_verdict_source']);
    }
}
