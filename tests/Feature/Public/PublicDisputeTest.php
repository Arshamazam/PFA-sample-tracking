<?php

namespace Tests\Feature\Public;

use App\Enums\Verdict;
use App\Models\Dispute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

class PublicDisputeTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'filed_by_name' => 'Muhammad Aslam',
            'filed_by_phone' => '03001234567',
            'filed_by_cnic' => '35201-1234567-1',
            'reason' => 'Sample was mishandled.',
        ], $overrides);
    }

    public function test_public_can_file_a_dispute_and_gets_a_reference(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        $code = $built['event']->event_code;

        $this->post("/track/e/{$code}/dispute", $this->form())
            ->assertRedirect(route('track.event', ['event_code' => $code]))
            ->assertSessionHas('dispute_reference');

        $dispute = Dispute::firstOrFail();
        $this->assertSame('PUBLIC', $dispute->source);
        $this->assertMatchesRegularExpression('/^D-\d{4}-\d{6}$/', $dispute->reference_no);
    }

    public function test_honeypot_silently_drops_the_submission(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        $code = $built['event']->event_code;

        $this->post("/track/e/{$code}/dispute", $this->form(['website' => 'http://spam.example']))
            ->assertRedirect(route('track.event', ['event_code' => $code]));

        // Nothing was actually filed.
        $this->assertDatabaseCount('disputes', 0);
    }

    public function test_invalid_pk_phone_is_rejected(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        $code = $built['event']->event_code;

        $this->from(route('track.dispute.create', ['event_code' => $code]))
            ->post("/track/e/{$code}/dispute", $this->form(['filed_by_phone' => '12345']))
            ->assertSessionHasErrors('filed_by_phone');

        $this->assertDatabaseCount('disputes', 0);
    }

    public function test_fit_verdict_cannot_be_disputed_publicly(): void
    {
        // The service rule (FIT has no dispute right) applies identically to public.
        $built = $this->makeReportedEvent(verdict: Verdict::FIT);
        $code = $built['event']->event_code;

        $this->from(route('track.dispute.create', ['event_code' => $code]))
            ->post("/track/e/{$code}/dispute", $this->form())
            ->assertSessionHasErrors('event_code');

        $this->assertDatabaseCount('disputes', 0);
    }

    public function test_expired_window_is_rejected_publicly(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDays(30));
        $code = $built['event']->event_code;

        $this->from(route('track.dispute.create', ['event_code' => $code]))
            ->post("/track/e/{$code}/dispute", $this->form())
            ->assertSessionHasErrors('event_code');
    }

    public function test_rate_limited_to_three_per_day(): void
    {
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        $code = $built['event']->event_code;

        // First submission succeeds; the event now has an open dispute, so further
        // *valid* submissions would be blocked by the service. To isolate the RATE
        // limit we hit the GET form 3 times then confirm the 4th is throttled.
        for ($i = 0; $i < 3; $i++) {
            $this->get("/track/e/{$code}/dispute")->assertOk();
        }
        $this->get("/track/e/{$code}/dispute")->assertStatus(429);
    }
}
