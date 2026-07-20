<?php

namespace Tests\Feature\Public;

use App\Enums\PartStatus;
use App\Enums\Verdict;
use App\Models\LabResult;
use App\Models\Premises;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

class PublicTrackingTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    public function test_landing_page_loads_and_is_noindex(): void
    {
        $this->get('/track')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Track a food sample');
    }

    public function test_unknown_qr_token_404s(): void
    {
        $this->get('/track/p/'.str_repeat('x', 32))->assertNotFound();
    }

    public function test_draft_events_are_invisible(): void
    {
        $event = SamplingEvent::factory()->create(['finalized_at' => null]);
        $part = SamplePart::factory()->for($event, 'samplingEvent')->create(['qr_token' => 'draftqrtoken000000000000000000aa']);

        $this->get('/track/p/'.$part->qr_token)->assertNotFound();
        $this->get('/track/e/'.$event->event_code)->assertNotFound();
    }

    public function test_finalized_event_is_visible_by_qr_and_code(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);
        $event = $built['event'];

        $this->get('/track/e/'.$event->event_code)->assertOk()->assertSee($event->event_code);
        $this->get('/track/p/'.$built['lab']->qr_token)->assertOk()->assertSee('Fit for use');
    }

    public function test_verdict_only_shows_at_report_issued(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT);
        // Move the LAB part back to VERIFIED (not yet reported).
        $built['lab']->update(['status' => PartStatus::VERIFIED]);

        $this->get('/track/e/'.$built['event']->event_code)
            ->assertOk()
            ->assertDontSee('Unfit for use');
    }

    public function test_retest_tag_shows_after_a_closed_dispute(): void
    {
        // Original UNFIT reported; reference retested FIT and dispute CLOSED.
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        $reference = $built['reference'];
        $reference->update(['status' => PartStatus::REPORT_ISSUED, 'blind_code' => 'BC-2026-000900']);
        LabResult::create([
            'sample_part_id' => $reference->id,
            'lab_section' => \App\Enums\LabSection::CHEMICAL,
            'verdict' => Verdict::FIT,
            'verdict_at' => now(),
            'report_photo_path' => 'lab-reports/retest.jpg',
        ]);
        $built['event']->disputes()->create([
            'filed_by_name' => 'X', 'filed_by_phone' => '03001234567',
            'status' => 'CLOSED', 'source' => 'PUBLIC', 'reference_no' => 'D-2026-000001',
            'filed_at' => now()->subHours(2),
        ]);

        $this->get('/track/e/'.$built['event']->event_code)
            ->assertOk()
            ->assertSee('Fit for use')
            ->assertSee('after retest');
    }

    public function test_report_photo_route_only_serves_report_issued_parts(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);
        // Give the LAB part a stored report photo.
        \Illuminate\Support\Facades\Storage::disk('local')->put('lab-reports/r.jpg', 'x');
        $built['lab']->labResult->update(['report_photo_path' => 'lab-reports/r.jpg']);

        $this->get(route('track.report-photo', ['part' => $built['lab']->id]))->assertOk();

        // A non-reported part must 404 even with a photo.
        $other = $this->makeLabPart(PartStatus::TESTING, blindCode: 'BC-2026-000950');
        LabResult::create(['sample_part_id' => $other->id, 'lab_section' => \App\Enums\LabSection::CHEMICAL, 'report_photo_path' => 'lab-reports/r.jpg']);
        $this->get(route('track.report-photo', ['part' => $other->id]))->assertNotFound();
    }

    public function test_short_link_redirects_to_the_event(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);

        $this->get('/t/'.$built['event']->event_code)
            ->assertRedirect(route('track.event', ['event_code' => $built['event']->event_code]));
    }

    public function test_license_lookup_lists_only_finalized_events(): void
    {
        $premises = Premises::factory()->create(['license_no' => 'PFA-LHR-2025-77777']);
        SamplingEvent::factory()->for($premises, 'premises')->create(['finalized_at' => now(), 'event_code' => 'PFA-LHR-2026-070001']);
        SamplingEvent::factory()->for($premises, 'premises')->create(['finalized_at' => null, 'event_code' => 'PFA-LHR-2026-070002']);

        $this->get('/track/l/PFA-LHR-2025-77777')
            ->assertOk()
            ->assertSee('PFA-LHR-2026-070001')
            ->assertDontSee('PFA-LHR-2026-070002');
    }
}
