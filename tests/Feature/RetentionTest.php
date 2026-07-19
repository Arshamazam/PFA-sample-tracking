<?php

namespace Tests\Feature;

use App\Enums\PartStatus;
use App\Enums\UserRole;
use App\Enums\Verdict;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Setting::updateOrCreate(['key' => 'dispute_window_days'], ['value' => '7']);
    }

    // --- retention:process eligibility rules -----------------------------

    public function test_fit_verdict_makes_the_reference_eligible(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);

        $this->artisan('retention:process')->assertExitCode(0);

        $this->assertNotNull($built['reference']->fresh()->destruction_eligible_at);
    }

    public function test_unfit_within_window_is_not_yet_eligible(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());

        $this->artisan('retention:process')->assertExitCode(0);

        $this->assertNull($built['reference']->fresh()->destruction_eligible_at);
    }

    public function test_unfit_after_window_with_no_dispute_is_eligible(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDays(10));

        $this->artisan('retention:process')->assertExitCode(0);

        $this->assertNotNull($built['reference']->fresh()->destruction_eligible_at);
    }

    public function test_unfit_after_window_with_an_open_dispute_is_not_eligible(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDays(10));
        // An open dispute (e.g. filed just before expiry, still being decided).
        $built['event']->disputes()->create([
            'filed_by_name' => 'X', 'filed_by_phone' => '0300', 'status' => 'FILED', 'filed_at' => now(),
        ]);

        $this->artisan('retention:process')->assertExitCode(0);

        $this->assertNull($built['reference']->fresh()->destruction_eligible_at);
    }

    public function test_process_never_sets_eligibility_before_a_verdict(): void
    {
        // Reference in retention but the LAB part not yet reported.
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDays(10));
        $built['lab']->labResult->update(['verdict' => null, 'verdict_at' => null]);

        $this->artisan('retention:process')->assertExitCode(0);

        $this->assertNull($built['reference']->fresh()->destruction_eligible_at);
    }

    // --- destruction endpoint --------------------------------------------

    private function actAsRegistration(): void
    {
        $officer = $this->makeUser(UserRole::REGISTRATION_OFFICER);
        Sanctum::actingAs($officer, $officer->role->abilities());
    }

    public function test_destroying_an_eligible_reference_moves_it_to_destroyed(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);
        $built['reference']->update(['destruction_eligible_at' => Carbon::now()->subHour()]);

        $this->actAsRegistration();
        $this->postJson('/api/v1/registration/destroy', [
            'qr_token' => $built['reference']->qr_token,
            'photo' => UploadedFile::fake()->image('destroyed.jpg'),
            'notes' => 'Incinerated per SOP, batch 12.',
        ])->assertOk()->assertJsonPath('data.status', 'DESTROYED');

        $this->assertSame(PartStatus::DESTROYED, $built['reference']->fresh()->status);
    }

    public function test_destruction_requires_a_photo(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);
        $built['reference']->update(['destruction_eligible_at' => Carbon::now()->subHour()]);

        $this->actAsRegistration();
        $this->postJson('/api/v1/registration/destroy', [
            'qr_token' => $built['reference']->qr_token,
            'notes' => 'no photo attached',
        ])->assertStatus(422)->assertJsonValidationErrors('photo');
    }

    public function test_cannot_destroy_before_eligibility(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        // Not eligible (within window, retention:process has not flagged it).
        $this->assertNull($built['reference']->fresh()->destruction_eligible_at);

        $this->actAsRegistration();
        $this->postJson('/api/v1/registration/destroy', [
            'qr_token' => $built['reference']->qr_token,
            'photo' => UploadedFile::fake()->image('x.jpg'),
            'notes' => 'attempting early destruction',
        ])->assertStatus(422);

        $this->assertSame(PartStatus::IN_RETENTION, $built['reference']->fresh()->status);
    }

    public function test_cannot_destroy_with_a_future_eligibility_date(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);
        $built['reference']->update(['destruction_eligible_at' => Carbon::now()->addDay()]);

        $this->actAsRegistration();
        $this->postJson('/api/v1/registration/destroy', [
            'qr_token' => $built['reference']->qr_token,
            'photo' => UploadedFile::fake()->image('x.jpg'),
            'notes' => 'too early',
        ])->assertStatus(422);
    }

    public function test_retention_list_reports_eligibility(): void
    {
        $eligible = $this->makeReportedEvent(verdict: Verdict::FIT);
        $eligible['reference']->update(['destruction_eligible_at' => Carbon::now()->subHour()]);
        $eligible['reference']->custodyEvents()->create([
            'status' => PartStatus::IN_RETENTION, 'location_note' => 'Cabinet A/1', 'notes' => 'stored',
        ]);

        $this->actAsRegistration();
        $this->getJson('/api/v1/registration/retention?eligible=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_destruction_eligible', true)
            ->assertJsonPath('data.0.storage_location', 'Cabinet A/1');
    }

    public function test_destroy_is_denied_to_a_lab_analyst(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);
        $built['reference']->update(['destruction_eligible_at' => Carbon::now()->subHour()]);

        $analyst = $this->makeUser(UserRole::LAB_ANALYST);
        Sanctum::actingAs($analyst, $analyst->role->abilities());

        $this->postJson('/api/v1/registration/destroy', [
            'qr_token' => $built['reference']->qr_token,
            'photo' => UploadedFile::fake()->image('x.jpg'),
            'notes' => 'should be blocked',
        ])->assertStatus(403);
    }
}
