<?php

namespace App\Services;

use App\Enums\PartStatus;
use App\Enums\Verdict;
use App\Jobs\GenerateReportPdf;
use App\Models\SamplePart;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Verifying-officer (maker-checker) actions, shared by the API and the web panel.
 */
class VerificationService
{
    public function __construct(
        private readonly CustodyStateMachine $custody,
        private readonly DisputeService $disputes,
    ) {
    }

    /**
     * Record a verdict, move the part to VERIFIED, close any retest dispute, and
     * queue the report PDF. Enforces maker-checker (verifier != analyst).
     */
    public function recordVerdict(SamplePart $part, User $verifier, Verdict $verdict, ?string $notes): void
    {
        $labResult = $part->labResult;

        if ($labResult === null) {
            throw ValidationException::withMessages([
                'blind_code' => ['This sample has no lab result to verify.'],
            ]);
        }

        if ($labResult->analyst_id !== null && $labResult->analyst_id === $verifier->id) {
            throw ValidationException::withMessages([
                'verified_by' => ['You analysed this sample; a different officer must verify it (maker-checker).'],
            ]);
        }

        DB::transaction(function () use ($part, $labResult, $verifier, $verdict, $notes) {
            $labResult->update([
                'verdict' => $verdict,
                'verdict_at' => now(),
                'verified_by_id' => $verifier->id,
            ]);

            $this->custody->transition($part, PartStatus::VERIFIED, $verifier, [
                'notes' => $notes ?? 'Result verified.',
            ]);

            $this->disputes->closeRetestIfApplicable($part, $labResult);
        });

        GenerateReportPdf::dispatch($part->id);
    }

    /**
     * Return the result to the analyst for rework: RESULT_ENTERED -> TESTING.
     */
    public function returnToAnalyst(SamplePart $part, User $verifier, string $notes): void
    {
        $this->custody->transition($part, PartStatus::TESTING, $verifier, [
            'notes' => 'Returned to analyst: '.$notes,
        ]);
    }
}
