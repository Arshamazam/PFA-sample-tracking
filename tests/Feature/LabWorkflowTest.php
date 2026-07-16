<?php

namespace Tests\Feature;

use App\Enums\LabSection;
use App\Enums\PartStatus;
use App\Enums\UserRole;
use App\Jobs\GenerateReportPdf;
use App\Models\SamplePart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

class LabWorkflowTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    private User $analyst;
    private User $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->analyst = $this->makeUser(UserRole::LAB_ANALYST);
        $this->verifier = $this->makeUser(UserRole::VERIFYING_OFFICER);
    }

    private function asAnalyst(?User $user = null): void
    {
        $user ??= $this->analyst;
        Sanctum::actingAs($user, $user->role->abilities());
    }

    private function asVerifier(?User $user = null): void
    {
        $user ??= $this->verifier;
        Sanctum::actingAs($user, $user->role->abilities());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function validParameters(): array
    {
        return [
            ['name' => 'Fat', 'value' => '3.6', 'unit' => '%', 'permissible_limit' => 'min 3.5', 'within_limit' => true],
            ['name' => 'Solids-Not-Fat (SNF)', 'value' => '8.1', 'unit' => '%', 'permissible_limit' => 'min 8.9', 'within_limit' => false],
        ];
    }

    private function submitResults(string $blindCode): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v1/lab/{$blindCode}/results", [
            'parameters' => $this->validParameters(),
            'report_photo' => UploadedFile::fake()->image('bench.jpg'),
        ]);
    }

    public function test_full_lab_path_from_assignment_to_issued_report(): void
    {
        $part = $this->makeAssignedPart('BC-2026-000100', LabSection::CHEMICAL);

        // Analyst: queue -> start -> results
        $this->asAnalyst();
        $this->getJson('/api/v1/lab/queue?section=CHEMICAL')
            ->assertOk()
            ->assertJsonPath('data.0.blind_code', 'BC-2026-000100');

        $this->postJson('/api/v1/lab/BC-2026-000100/start')->assertOk();
        $this->assertSame(PartStatus::TESTING, $part->fresh()->status);

        $this->submitResults('BC-2026-000100')->assertOk();
        $this->assertSame(PartStatus::RESULT_ENTERED, $part->fresh()->status);

        // Verifier: sees the FULL record, then issues the verdict.
        $this->asVerifier();
        $this->getJson('/api/v1/verification/queue')
            ->assertOk()
            ->assertJsonPath('data.0.blind_code', 'BC-2026-000100')
            // De-blinded: the business is visible to this role.
            ->assertJsonPath('data.0.sampling_event.premises.license_no', $part->samplingEvent->premises->license_no);

        Bus::fake();
        $this->postJson('/api/v1/verification/BC-2026-000100/verdict', [
            'verdict' => 'UNFIT',
            'notes' => 'SNF below permissible limit.',
        ])->assertOk();

        Bus::assertDispatched(GenerateReportPdf::class);

        $part->refresh();
        $this->assertSame(PartStatus::VERIFIED, $part->status);
        $this->assertSame('UNFIT', $part->labResult->verdict->value);
        $this->assertSame($this->verifier->id, $part->labResult->verified_by_id);
        $this->assertNotNull($part->labResult->verdict_at);
    }

    public function test_report_job_generates_a_pdf_and_issues_the_report(): void
    {
        $part = $this->makeAssignedPart('BC-2026-000101', LabSection::CHEMICAL, analyst: $this->analyst);

        $this->asAnalyst();
        $this->postJson('/api/v1/lab/BC-2026-000101/start')->assertOk();
        $this->submitResults('BC-2026-000101')->assertOk();

        $this->asVerifier();
        // No Bus::fake here — the job runs synchronously (queue driver is sync in tests).
        $this->postJson('/api/v1/verification/BC-2026-000101/verdict', ['verdict' => 'FIT'])->assertOk();

        $part->refresh();
        $this->assertSame(PartStatus::REPORT_ISSUED, $part->status);

        $path = $part->labResult->report_pdf_path;
        $this->assertNotNull($path, 'report_pdf_path should be set by the job');
        Storage::disk('local')->assertExists($path);

        // It is a real PDF.
        $contents = Storage::disk('local')->get($path);
        $this->assertStringStartsWith('%PDF', $contents);

        // And the verifier can download it.
        $this->get('/api/v1/reports/BC-2026-000101.pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_owning_fso_can_download_the_report_but_a_different_fso_cannot(): void
    {
        $part = $this->makeAssignedPart('BC-2026-000102', LabSection::CHEMICAL, analyst: $this->analyst);

        $this->asAnalyst();
        $this->postJson('/api/v1/lab/BC-2026-000102/start')->assertOk();
        $this->submitResults('BC-2026-000102')->assertOk();
        $this->asVerifier();
        $this->postJson('/api/v1/verification/BC-2026-000102/verdict', ['verdict' => 'FIT'])->assertOk();

        // The FSO who collected it.
        $owner = User::find($part->samplingEvent->fso_id);
        Sanctum::actingAs($owner, $owner->role->abilities());
        $this->get('/api/v1/reports/BC-2026-000102.pdf')->assertOk();

        // A different FSO must not.
        $other = $this->makeUser(UserRole::FSO);
        Sanctum::actingAs($other, $other->role->abilities());
        $this->get('/api/v1/reports/BC-2026-000102.pdf')->assertStatus(403);
    }

    public function test_maker_checker_blocks_the_analyst_from_verifying_their_own_work(): void
    {
        // A user who is both analyst and verifier by role would still be the maker.
        $dualRole = $this->makeUser(UserRole::VERIFYING_OFFICER);
        $part = $this->makeAssignedPart('BC-2026-000103', LabSection::CHEMICAL, analyst: $dualRole);
        $part->update(['status' => PartStatus::RESULT_ENTERED]);
        $part->labResult->update(['parameters' => $this->validParameters()]);

        Sanctum::actingAs($dualRole, $dualRole->role->abilities());

        $this->postJson('/api/v1/verification/BC-2026-000103/verdict', ['verdict' => 'FIT'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('verified_by');

        $this->assertSame(PartStatus::RESULT_ENTERED, $part->fresh()->status);
    }

    public function test_analyst_cannot_set_a_verdict_via_the_results_endpoint(): void
    {
        $this->makeAssignedPart('BC-2026-000104', LabSection::CHEMICAL);

        $this->asAnalyst();
        $this->postJson('/api/v1/lab/BC-2026-000104/start')->assertOk();

        $this->postJson('/api/v1/lab/BC-2026-000104/results', [
            'parameters' => $this->validParameters(),
            'report_photo' => UploadedFile::fake()->image('b.jpg'),
            'verdict' => 'FIT',
        ])->assertStatus(422)->assertJsonValidationErrors('verdict');
    }

    public function test_analyst_cannot_call_the_verdict_endpoint_at_all(): void
    {
        $this->makeAssignedPart('BC-2026-000105', LabSection::CHEMICAL);

        $this->asAnalyst();
        $this->postJson('/api/v1/verification/BC-2026-000105/verdict', ['verdict' => 'FIT'])
            ->assertStatus(403);
    }

    public function test_verifier_can_return_work_to_the_analyst(): void
    {
        $part = $this->makeAssignedPart('BC-2026-000106', LabSection::CHEMICAL, analyst: $this->analyst);

        $this->asAnalyst();
        $this->postJson('/api/v1/lab/BC-2026-000106/start')->assertOk();
        $this->submitResults('BC-2026-000106')->assertOk();

        $this->asVerifier();
        $this->postJson('/api/v1/verification/BC-2026-000106/return', [
            'notes' => 'Please repeat the SNF determination.',
        ])->assertOk();

        $this->assertSame(PartStatus::TESTING, $part->fresh()->status);

        // The analyst can now re-submit, which advances it again.
        $this->asAnalyst();
        $this->submitResults('BC-2026-000106')->assertOk();
        $this->assertSame(PartStatus::RESULT_ENTERED, $part->fresh()->status);
    }

    public function test_return_requires_notes(): void
    {
        $this->makeAssignedPart('BC-2026-000107', LabSection::CHEMICAL, status: PartStatus::RESULT_ENTERED);

        $this->asVerifier();
        $this->postJson('/api/v1/verification/BC-2026-000107/return', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('notes');
    }

    public function test_resubmitting_results_archives_the_previous_version(): void
    {
        $part = $this->makeAssignedPart('BC-2026-000108', LabSection::CHEMICAL);

        $this->asAnalyst();
        $this->postJson('/api/v1/lab/BC-2026-000108/start')->assertOk();
        $this->submitResults('BC-2026-000108')->assertOk();

        // Re-submit with a corrected value while still RESULT_ENTERED.
        $this->postJson('/api/v1/lab/BC-2026-000108/results', [
            'parameters' => [
                ['name' => 'Fat', 'value' => '3.9', 'unit' => '%', 'permissible_limit' => 'min 3.5', 'within_limit' => true],
            ],
            'report_photo' => UploadedFile::fake()->image('bench2.jpg'),
        ])->assertOk();

        $labResult = $part->fresh()->labResult;
        $this->assertCount(1, $labResult->lab_result_revisions);
        $this->assertSame('3.6', $labResult->lab_result_revisions[0]['parameters'][0]['value']);
        $this->assertSame('3.9', $labResult->parameters[0]['value']);
        // Still awaiting verification — re-submission does not re-advance the state.
        $this->assertSame(PartStatus::RESULT_ENTERED, $part->fresh()->status);
    }

    public function test_parameters_must_match_the_catalog_unless_flagged_additional(): void
    {
        $this->makeAssignedPart('BC-2026-000109', LabSection::CHEMICAL);
        $this->asAnalyst();
        $this->postJson('/api/v1/lab/BC-2026-000109/start')->assertOk();

        // Unknown parameter, not flagged -> rejected.
        $this->postJson('/api/v1/lab/BC-2026-000109/results', [
            'parameters' => [
                ['name' => 'Melamine', 'value' => '1', 'within_limit' => false],
            ],
            'report_photo' => UploadedFile::fake()->image('b.jpg'),
        ])->assertStatus(422)->assertJsonValidationErrors('parameters.0.name');

        // Same parameter, flagged as additional -> accepted.
        $this->postJson('/api/v1/lab/BC-2026-000109/results', [
            'parameters' => [
                ['name' => 'Melamine', 'value' => '1', 'within_limit' => false, 'is_additional' => true],
            ],
            'report_photo' => UploadedFile::fake()->image('b.jpg'),
        ])->assertOk();
    }

    public function test_results_cannot_be_entered_before_testing_starts(): void
    {
        $this->makeAssignedPart('BC-2026-000110', LabSection::CHEMICAL);

        $this->asAnalyst();
        // Still ASSIGNED_TO_SECTION — start() has not been called.
        $this->submitResults('BC-2026-000110')->assertStatus(422);
    }

    public function test_lab_queue_only_shows_the_requested_section(): void
    {
        $this->makeAssignedPart('BC-2026-000111', LabSection::CHEMICAL);
        $this->makeAssignedPart('BC-2026-000112', LabSection::MICROBIOLOGY);

        $this->asAnalyst();
        $this->getJson('/api/v1/lab/queue?section=MICROBIOLOGY')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.blind_code', 'BC-2026-000112');
    }

    public function test_retry_failed_reports_requeues_missing_reports(): void
    {
        $part = $this->makeAssignedPart('BC-2026-000113', LabSection::CHEMICAL, analyst: $this->analyst);
        $part->update(['status' => PartStatus::VERIFIED]);
        $part->labResult->update([
            'parameters' => $this->validParameters(),
            'verdict' => 'FIT',
            'verdict_at' => now(),
            'verified_by_id' => $this->verifier->id,
            'report_pdf_path' => null,
        ]);

        Bus::fake();
        $this->artisan('reports:retry-failed')->assertExitCode(0);
        Bus::assertDispatched(GenerateReportPdf::class);
    }

    public function test_report_job_is_idempotent_and_skips_non_verified_parts(): void
    {
        $part = $this->makeAssignedPart('BC-2026-000114', LabSection::CHEMICAL, status: PartStatus::TESTING);

        (new GenerateReportPdf($part->id))->handle(
            app(\App\Services\CustodyStateMachine::class),
            app(\App\Services\QrService::class),
        );

        $this->assertSame(PartStatus::TESTING, $part->fresh()->status);
        $this->assertNull($part->fresh()->labResult->report_pdf_path);
    }
}
