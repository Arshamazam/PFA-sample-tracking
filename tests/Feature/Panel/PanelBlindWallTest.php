<?php

namespace Tests\Feature\Panel;

use App\Enums\LabSection;
use App\Enums\PartStatus;
use App\Enums\UserRole;
use App\Models\LabResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

/**
 * The blind wall in the browser. Renders every analyst-facing panel page for an
 * ACTIVATED RETEST (the hardest case — a reference part that must not betray it is
 * a retest) and asserts the rendered HTML contains none of the identifying strings.
 */
class PanelBlindWallTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    private function actingAsAnalyst(): User
    {
        $analyst = User::factory()->create(['role' => UserRole::LAB_ANALYST, 'must_change_password' => false]);
        $this->actingAs($analyst);

        return $analyst;
    }

    public function test_analyst_panel_pages_never_render_identifying_data(): void
    {
        $this->actingAsAnalyst();

        // An activated reference retest, carrying a very identifiable business.
        $built = $this->makeReportedEvent();
        $reference = $built['reference'];
        $reference->update(['status' => PartStatus::ACTIVATED_FOR_RETEST, 'blind_code' => 'BC-2026-000777']);
        LabResult::create(['sample_part_id' => $reference->id, 'lab_section' => LabSection::CHEMICAL]);
        $reference->custodyEvents()->create(['status' => PartStatus::ACTIVATED_FOR_RETEST, 'notes' => 'activated']);

        $event = $built['event'];
        $premises = $event->premises;

        $forbidden = [
            $premises->name,
            $premises->license_no,
            $premises->address,
            $premises->owner_name,
            $event->event_code,
            $event->brand_name,
            $event->witness_name,
            $built['lab']->blind_code,   // the ORIGINAL blind code must not appear
            $built['lab']->qr_token,
            $reference->qr_token,
            $reference->seal_number,
            'retest',
            'Retest',
            'RETEST',
            'dispute',
            'Dispute',
        ];

        $pages = [
            route('lab.queue', ['section' => 'CHEMICAL']),
            route('lab.show', 'BC-2026-000777'),
        ];

        foreach ($pages as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // Sanity: the sample really is on the page (else the test proves nothing).
            $this->assertStringContainsString('BC-2026-000777', $html, "Sample missing from {$url}");
            // The masked status is what an ordinary assigned sample shows.
            if (str_contains($url, 'BC-2026-000777') && str_contains($url, 'lab/BC')) {
                $this->assertStringContainsString('Assigned To Section', $html);
            }

            foreach ($forbidden as $needle) {
                if ($needle === null || $needle === '') {
                    continue;
                }
                $this->assertStringNotContainsString(
                    (string) $needle,
                    $html,
                    "BLIND WALL BREACH in {$url}: rendered HTML contains '{$needle}'."
                );
            }
        }
    }

    public function test_analyst_cannot_open_verifying_or_admin_pages(): void
    {
        $this->actingAsAnalyst();

        $this->get(route('verification.queue'))->assertForbidden();
        $this->get(route('admin.events.index'))->assertForbidden();
        $this->get(route('disputes.index'))->assertForbidden();
    }
}
