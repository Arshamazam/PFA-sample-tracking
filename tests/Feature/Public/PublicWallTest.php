<?php

namespace Tests\Feature\Public;

use App\Enums\PartStatus;
use App\Enums\Verdict;
use App\Http\Resources\PublicEventResource;
use App\Models\LabResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

/**
 * THE PUBLIC WALL. Recursively scans the public payload (keys AND scalar values)
 * for anything that must stay internal. Same pattern as the blind wall.
 */
class PublicWallTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    /** @var array<int, string> */
    private const FORBIDDEN_KEYS = [
        'seal_number', 'seal_photo_path', 'blind_code', 'qr_token',
        'witness_name', 'witness_cnic', 'witness_signature_path',
        'fso_id', 'fso', 'analyst', 'analyst_id', 'verified_by_id', 'verified_by',
        'temperature_c', 'latitude', 'longitude', 'parameters', 'notes',
        'sop_violations', 'details', 'address', 'owner_name', 'owner_phone',
        'report_pdf_path', 'sampling_event_id', 'sample_part_id',
    ];

    private function allKeys(mixed $data, array &$found = []): array
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (is_string($k)) {
                    $found[] = $k;
                }
                $this->allKeys($v, $found);
            }
        }

        return $found;
    }

    private function allValues(mixed $data, array &$found = []): array
    {
        if (is_array($data)) {
            foreach ($data as $v) {
                $this->allValues($v, $found);
            }
        } elseif (is_scalar($data)) {
            $found[] = (string) $data;
        }

        return $found;
    }

    public function test_public_payload_exposes_only_allow_listed_data(): void
    {
        // A fully-reported UNFIT event with all the sensitive bits populated.
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT, verdictAt: Carbon::now()->subDay());
        $event = $built['event']->fresh();
        $event->update([
            'witness_name' => 'Witness Person',
            'witness_cnic' => '35201-9999999-9',
            'witness_signature_path' => 'witness-signatures/secret.jpg',
        ]);
        $event->load(['premises', 'parts.labResult', 'parts.custodyEvents', 'disputes']);

        $payload = (new PublicEventResource($event))->resolve();
        $keys = $this->allKeys($payload);

        foreach (self::FORBIDDEN_KEYS as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "PUBLIC WALL BREACH: key '{$forbidden}' exposed.");
        }

        // Identifying values must not leak either.
        $haystack = strtolower(implode(' | ', $this->allValues($payload)));
        foreach ([
            'blind code' => $built['lab']->blind_code,
            'qr token' => $built['lab']->qr_token,
            'seal number' => $built['lab']->seal_number,
            'witness' => 'Witness Person',
            'witness cnic' => '35201-9999999-9',
            'analyst name' => $built['analyst']->name,
            'verifier name' => $built['verifier']->name,
        ] as $label => $secret) {
            if ($secret) {
                $this->assertStringNotContainsString(strtolower((string) $secret), $haystack, "PUBLIC WALL BREACH: {$label} leaked.");
            }
        }

        // Sanity: it DOES carry what the public should see.
        $this->assertSame($event->event_code, $payload['event_code']);
        $this->assertSame('Unfit for use', $payload['verdict_label']);
        $this->assertTrue($payload['report_issued']);
    }

    public function test_verdict_is_hidden_until_report_issued(): void
    {
        // A LAB part with a verdict recorded but not yet at REPORT_ISSUED.
        $built = $this->makeReportedEvent(verdict: Verdict::UNFIT);
        $built['lab']->update(['status' => PartStatus::VERIFIED]);
        $event = $built['event']->fresh()->load(['premises', 'parts.labResult', 'parts.custodyEvents', 'disputes']);

        $payload = (new PublicEventResource($event))->resolve();

        $this->assertFalse($payload['report_issued']);
        $this->assertNull($payload['verdict']);
        $this->assertNull($payload['verdict_label']);
    }
}
