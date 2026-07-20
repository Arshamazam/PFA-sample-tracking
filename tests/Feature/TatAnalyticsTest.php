<?php

namespace Tests\Feature;

use App\Enums\LabSection;
use App\Enums\PartRole;
use App\Enums\PartStatus;
use App\Enums\Verdict;
use App\Models\LabResult;
use App\Models\SamplePart;
use App\Models\SamplingEvent;
use App\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

class TatAnalyticsTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function analytics(): AnalyticsService
    {
        return app(AnalyticsService::class);
    }

    /**
     * Insert a custody event at an exact timestamp (append-only create is allowed).
     */
    private function custody(SamplePart $part, PartStatus $status, Carbon $at): void
    {
        $event = $part->custodyEvents()->create(['status' => $status]);
        // created_at is not fillable on the append-only model; set the fixed
        // timestamp with a raw update (which bypasses the model's update guard).
        \Illuminate\Support\Facades\DB::table('custody_events')
            ->where('id', $event->id)
            ->update(['created_at' => $at]);
    }

    public function test_tat_segments_are_computed_exactly_from_custody_timestamps(): void
    {
        $event = SamplingEvent::factory()->create([
            'food_category' => 'MILK',
            'collected_at' => Carbon::create(2026, 7, 1, 8, 0),
            'finalized_at' => now(),
        ]);
        $part = SamplePart::factory()->for($event, 'samplingEvent')->create([
            'role' => PartRole::LAB,
            'status' => PartStatus::REPORT_ISSUED,
        ]);
        LabResult::create(['sample_part_id' => $part->id, 'lab_section' => LabSection::CHEMICAL]);

        $t0 = Carbon::create(2026, 7, 1, 8, 0);
        $this->custody($part, PartStatus::COLLECTED, $t0);
        $this->custody($part, PartStatus::RECEIVED_REGISTRATION, $t0->copy()->addHours(10)); // +10
        $this->custody($part, PartStatus::TESTING, $t0->copy()->addHours(15));               // +5
        $this->custody($part, PartStatus::VERIFIED, $t0->copy()->addHours(35));              // +20
        $this->custody($part, PartStatus::REPORT_ISSUED, $t0->copy()->addHours(37));         // +2

        $report = $this->analytics()->tatReport(null, null, null);
        $seg = $report['segments'];

        $this->assertSame(10.0, $seg['collected_to_received']['avg']);
        $this->assertSame(5.0, $seg['received_to_testing']['avg']);
        $this->assertSame(20.0, $seg['testing_to_verdict']['avg']);
        $this->assertSame(2.0, $seg['verdict_to_report']['avg']);
        // Single sample: avg == median == max.
        $this->assertSame(20.0, $seg['testing_to_verdict']['max']);
        $this->assertSame(20.0, $seg['testing_to_verdict']['median']);
        $this->assertSame(1, $seg['testing_to_verdict']['n']);
    }

    public function test_median_across_two_samples(): void
    {
        foreach ([[10, 20], [10, 40]] as $i => [$recv, $verify]) {
            $event = SamplingEvent::factory()->create(['food_category' => 'MILK', 'finalized_at' => now(), 'collected_at' => now()]);
            $part = SamplePart::factory()->for($event, 'samplingEvent')->create(['role' => PartRole::LAB, 'status' => PartStatus::VERIFIED]);
            LabResult::create(['sample_part_id' => $part->id, 'lab_section' => LabSection::CHEMICAL]);
            $t0 = Carbon::create(2026, 7, 1, 8, 0);
            $this->custody($part, PartStatus::TESTING, $t0);
            $this->custody($part, PartStatus::VERIFIED, $t0->copy()->addHours($verify));
        }

        $seg = $this->analytics()->tatReport(null, null, null)['segments'];
        // testing_to_verdict values: 20 and 40 -> median 30, max 40, avg 30.
        $this->assertSame(30.0, $seg['testing_to_verdict']['median']);
        $this->assertSame(40.0, $seg['testing_to_verdict']['max']);
        $this->assertSame(2, $seg['testing_to_verdict']['n']);
    }

    public function test_overturn_rate_counts_unfit_to_fit_retests(): void
    {
        Cache::flush();
        // Original UNFIT, retest FIT -> one overturn.
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT);
        LabResult::create([
            'sample_part_id' => $built['reference']->id,
            'lab_section' => LabSection::CHEMICAL,
            'verdict' => Verdict::FIT,
            'verdict_at' => now(),
        ]);

        $volume = $this->analytics()->volume(null, null);

        $this->assertSame(1, $volume['retests']);
        $this->assertSame(1, $volume['overturns']);
        $this->assertSame(100.0, $volume['overturn_rate']);
    }
}
