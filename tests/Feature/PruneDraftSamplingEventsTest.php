<?php

namespace Tests\Feature;

use App\Models\SamplingEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneDraftSamplingEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_flags_only_old_unfinalized_drafts_without_deleting(): void
    {
        $oldDraft = SamplingEvent::factory()->create([
            'finalized_at' => null,
            'created_at' => now()->subHours(30),
        ]);
        $recentDraft = SamplingEvent::factory()->create([
            'finalized_at' => null,
            'created_at' => now()->subHour(),
        ]);
        $finalized = SamplingEvent::factory()->create([
            'finalized_at' => now(),
            'created_at' => now()->subHours(30),
        ]);

        $this->artisan('sampling:prune-drafts')->assertExitCode(0);

        // Nothing is deleted.
        $this->assertDatabaseCount('sampling_events', 3);

        $this->assertNotNull($oldDraft->fresh()->stale_flagged_at);
        $this->assertNull($recentDraft->fresh()->stale_flagged_at);
        $this->assertNull($finalized->fresh()->stale_flagged_at);
    }
}
