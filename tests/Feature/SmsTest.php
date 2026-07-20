<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Enums\DisputeStatus;
use App\Enums\PartStatus;
use App\Enums\UserRole;
use App\Enums\Verdict;
use App\Events\DisputeDecided;
use App\Events\DisputeFiled;
use App\Events\ReportIssued;
use App\Jobs\SendSms;
use App\Models\Dispute;
use App\Models\LabResult;
use App\Models\SmsLog;
use App\Models\User;
use App\Support\SmsResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

class SmsTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    public function test_report_issued_notifies_both_fbo_and_fso(): void
    {
        Queue::fake();

        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        $built['event']->premises->update(['owner_phone' => '03001112222']);
        User::whereKey($built['event']->fso_id)->update(['phone' => '03003334444']);

        ReportIssued::dispatch($built['lab']->id, false);

        Queue::assertPushed(SendSms::class, fn (SendSms $j) => $j->trigger === 'report_issued_fbo' && str_contains($j->message, $built['event']->event_code));
        Queue::assertPushed(SendSms::class, fn (SendSms $j) => $j->trigger === 'report_issued_fso');
        Queue::assertPushed(SendSms::class, 2);
    }

    public function test_retest_report_notifies_only_the_fbo_with_final_verdict(): void
    {
        Queue::fake();

        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        $built['event']->premises->update(['owner_phone' => '03001112222']);
        $reference = $built['reference'];
        $reference->update(['status' => PartStatus::REPORT_ISSUED, 'blind_code' => 'BC-2026-000901']);
        LabResult::create(['sample_part_id' => $reference->id, 'lab_section' => \App\Enums\LabSection::CHEMICAL, 'verdict' => Verdict::FIT, 'verdict_at' => now()]);

        ReportIssued::dispatch($reference->id, true);

        Queue::assertPushed(SendSms::class, 1);
        Queue::assertPushed(SendSms::class, fn (SendSms $j) => $j->trigger === 'retest_report_fbo' && str_contains($j->message, 'FIT'));
    }

    public function test_dispute_filed_and_decided_notify_the_filer(): void
    {
        Queue::fake();

        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        $dispute = Dispute::create([
            'sampling_event_id' => $built['event']->id,
            'filed_by_name' => 'Aslam', 'filed_by_phone' => '03005556666',
            'status' => DisputeStatus::FILED, 'source' => 'PUBLIC',
            'reference_no' => 'D-2026-000007', 'filed_at' => now(),
        ]);

        DisputeFiled::dispatch($dispute->id);
        Queue::assertPushed(SendSms::class, fn (SendSms $j) => $j->trigger === 'dispute_filed' && str_contains($j->message, 'D-2026-000007'));

        DisputeDecided::dispatch($dispute->id, true);
        Queue::assertPushed(SendSms::class, fn (SendSms $j) => $j->trigger === 'dispute_accepted');

        DisputeDecided::dispatch($dispute->id, false);
        Queue::assertPushed(SendSms::class, fn (SendSms $j) => $j->trigger === 'dispute_rejected');
    }

    public function test_successful_send_writes_a_sent_log(): void
    {
        $gateway = $this->stubGateway(SmsResult::ok('provider-123'));

        (new SendSms('03001234567', 'Hello', 'test'))->handle($gateway);

        $this->assertDatabaseHas('sms_logs', ['to' => '+923001234567', 'status' => 'SENT', 'trigger' => 'test']);
    }

    public function test_failed_send_logs_failure_and_throws_to_retry(): void
    {
        $gateway = $this->stubGateway(SmsResult::failed('gateway down'));

        try {
            (new SendSms('03001234567', 'Hello', 'test'))->handle($gateway);
            $this->fail('Expected the failed send to throw for retry.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $log = SmsLog::firstOrFail();
        $this->assertSame('FAILED', $log->status);
        $this->assertSame('gateway down', $log->error);
        $this->assertNull($log->sent_at);
    }

    public function test_violation_summary_command_sends_one_batched_sms(): void
    {
        Queue::fake();
        \App\Models\Setting::updateOrCreate(['key' => 'supervisor_phone'], ['value' => '03007778888']);

        $part = $this->makeLabPart(PartStatus::RECEIVED_REGISTRATION);
        foreach (range(1, 3) as $i) {
            \App\Models\SopViolation::create([
                'sample_part_id' => $part->id,
                'type' => \App\Enums\SopViolationType::COLD_CHAIN_BREACH,
                'details' => [], 'detected_at' => now()->subMinutes(10),
            ]);
        }

        $this->artisan('sms:violation-summary')->assertExitCode(0);

        // Exactly one batched summary, not one per violation.
        Queue::assertPushed(SendSms::class, 1);
        Queue::assertPushed(SendSms::class, fn (SendSms $j) => $j->trigger === 'violation_summary' && str_contains($j->message, '3'));
    }

    private function stubGateway(SmsResult $result): SmsGateway
    {
        return new class($result) implements SmsGateway
        {
            public function __construct(private SmsResult $result)
            {
            }

            public function send(string $to, string $message): SmsResult
            {
                return $this->result;
            }

            public function name(): string
            {
                return 'stub';
            }
        };
    }
}
