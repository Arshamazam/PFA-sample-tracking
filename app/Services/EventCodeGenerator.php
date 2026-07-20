<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates sampling-event codes of the form:
 *
 *     PFA-{DISTRICT}-{YYYY}-{6-digit sequence}    e.g. PFA-LHR-2026-000123
 *
 * The 6-digit sequence is allocated from a DB-transaction-safe counter table,
 * scoped per district + year. We deliberately do NOT use MAX(id)+1, which is
 * unsafe under concurrency.
 */
class EventCodeGenerator
{
    /**
     * Build the next event code for a district (defaults to the current year).
     */
    public function generate(string $district, ?int $year = null): string
    {
        $district = Str::upper(trim($district));
        $year ??= (int) Carbon::now()->format('Y');

        $key = sprintf('event_code:%s:%d', $district, $year);
        $sequence = $this->nextSequence($key);

        return sprintf('PFA-%s-%d-%06d', $district, $year, $sequence);
    }

    /**
     * Build the next blind code (BC-{YYYY}-{6-digit}), scoped per year, using the
     * same transaction-safe counter mechanism. Analysts see only this code.
     */
    public function generateBlindCode(?int $year = null): string
    {
        $year ??= (int) Carbon::now()->format('Y');
        $sequence = $this->nextSequence(sprintf('blind_code:%d', $year));

        return sprintf('BC-%d-%06d', $year, $sequence);
    }

    /**
     * Build the next public dispute reference (D-{YYYY}-{6-digit}), scoped per year.
     */
    public function generateDisputeReference(?int $year = null): string
    {
        $year ??= (int) Carbon::now()->format('Y');
        $sequence = $this->nextSequence(sprintf('dispute_ref:%d', $year));

        return sprintf('D-%d-%06d', $year, $sequence);
    }

    /**
     * Atomically allocate and return the next value for a named counter.
     *
     * Uses insertOrIgnore + a row lock inside a transaction so concurrent
     * requests can never receive the same number (safe on MariaDB/MySQL shared
     * hosting where no long-running processes or advisory locks are available).
     */
    public function nextSequence(string $key): int
    {
        return DB::transaction(function () use ($key): int {
            DB::table('sequence_counters')->insertOrIgnore([
                'key' => $key,
                'value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('sequence_counters')
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            $next = (int) $row->value + 1;

            DB::table('sequence_counters')
                ->where('key', $key)
                ->update([
                    'value' => $next,
                    'updated_at' => now(),
                ]);

            return $next;
        });
    }
}
