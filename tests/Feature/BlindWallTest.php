<?php

namespace Tests\Feature;

use App\Enums\LabSection;
use App\Enums\PartStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsSamples;
use Tests\TestCase;

/**
 * THE BLIND WALL REGRESSION LOCK.
 *
 * Lab analysts must never be able to learn which business a sample came from.
 * This test drives every analyst-facing endpoint and recursively scans the entire
 * JSON payload — at any depth, in keys AND in values — for anything that could
 * identify the source.
 *
 * If you are here because this test failed: you have leaked identifying data into
 * an analyst response. Fix the resource, do not weaken this test.
 */
class BlindWallTest extends TestCase
{
    use BuildsSamples, RefreshDatabase;

    /**
     * Keys that must never appear anywhere in an analyst-facing payload.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_KEYS = [
        'qr_token',
        'tracking_url',
        'qr_svg_url',
        'seal_number',
        'seal_photo_path',
        'seal_photo_url',
        'premises',
        'premises_id',
        'license_no',
        'brand_name',
        'witness_name',
        'witness_cnic',
        'witness_signature_path',
        'witness_signature_url',
        'fso_id',
        'fso',
        'event_code',
        'sampling_event',
        'sampling_event_id',
        'sample_part_id',
        'custody_events',
        'actor',
        'actor_id',
        'owner_name',
        'owner_phone',
        'address',
        'verdict',
        'report_pdf_path',
    ];

    private User $analyst;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->analyst = $this->makeUser(UserRole::LAB_ANALYST);
        Sanctum::actingAs($this->analyst, $this->analyst->role->abilities());
    }

    /**
     * Recursively collect every key used anywhere in a nested array.
     *
     * @param  mixed  $data
     * @return array<int, string>
     */
    private function allKeys(mixed $data, array &$found = []): array
    {
        if (! is_array($data)) {
            return $found;
        }

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $found[] = $key;
            }
            $this->allKeys($value, $found);
        }

        return $found;
    }

    /**
     * Recursively collect every scalar value anywhere in a nested array.
     *
     * @return array<int, string>
     */
    private function allScalarValues(mixed $data, array &$found = []): array
    {
        if (is_array($data)) {
            foreach ($data as $value) {
                $this->allScalarValues($value, $found);
            }
        } elseif (is_scalar($data)) {
            $found[] = (string) $data;
        }

        return $found;
    }

    private function assertBlind(array $payload, string $endpoint): void
    {
        $keys = $this->allKeys($payload);

        foreach (self::FORBIDDEN_KEYS as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $keys,
                "BLIND WALL BREACH: analyst endpoint [{$endpoint}] exposed the key '{$forbidden}'."
            );
        }
    }

    /**
     * Identifying *values* must not leak either, even under an innocent key name.
     */
    private function assertNoIdentifyingValues(array $payload, array $secrets, string $endpoint): void
    {
        $values = $this->allScalarValues($payload);
        $haystack = strtolower(implode(' | ', $values));

        foreach ($secrets as $label => $secret) {
            if ($secret === null || $secret === '') {
                continue;
            }
            $this->assertStringNotContainsString(
                strtolower((string) $secret),
                $haystack,
                "BLIND WALL BREACH: analyst endpoint [{$endpoint}] leaked the {$label} value '{$secret}'."
            );
        }
    }

    public function test_every_analyst_endpoint_is_blind(): void
    {
        $part = $this->makeAssignedPart(blindCode: 'BC-2026-000042', section: LabSection::CHEMICAL);
        $event = $part->samplingEvent;
        $premises = $event->premises;

        $secrets = [
            'premises name' => $premises->name,
            'license number' => $premises->license_no,
            'premises address' => $premises->address,
            'owner name' => $premises->owner_name,
            'event code' => $event->event_code,
            'brand name' => $event->brand_name,
            'witness name' => $event->witness_name,
            'qr token' => $part->qr_token,
            'seal number' => $part->seal_number,
            'sampling event id' => $event->id,
            'sample part id' => $part->id,
        ];

        // 1. The section queue.
        $queue = $this->getJson('/api/v1/lab/queue?section=CHEMICAL')->assertOk()->json();
        $this->assertBlind($queue, 'GET /lab/queue');
        $this->assertNoIdentifyingValues($queue, $secrets, 'GET /lab/queue');
        // Sanity: the sample really is in the payload (otherwise this proves nothing).
        $this->assertSame('BC-2026-000042', $queue['data'][0]['blind_code']);

        // 2. Starting a test.
        $start = $this->postJson('/api/v1/lab/BC-2026-000042/start')->assertOk()->json();
        $this->assertBlind($start, 'POST /lab/{blind_code}/start');
        $this->assertNoIdentifyingValues($start, $secrets, 'POST /lab/{blind_code}/start');

        // 3. Submitting results.
        $results = $this->postJson('/api/v1/lab/BC-2026-000042/results', [
            'parameters' => [
                ['name' => 'Fat', 'value' => '3.6', 'unit' => '%', 'permissible_limit' => 'min 3.5', 'within_limit' => true],
            ],
            'report_photo' => UploadedFile::fake()->image('bench.jpg'),
        ])->assertOk()->json();
        $this->assertBlind($results, 'POST /lab/{blind_code}/results');
        $this->assertNoIdentifyingValues($results, $secrets, 'POST /lab/{blind_code}/results');
    }

    public function test_blind_payload_still_carries_what_the_analyst_needs(): void
    {
        $this->makeAssignedPart(blindCode: 'BC-2026-000043', section: LabSection::CHEMICAL);

        $data = $this->postJson('/api/v1/lab/BC-2026-000043/start')->assertOk()->json('data');

        $this->assertSame('BC-2026-000043', $data['blind_code']);
        $this->assertSame('MILK', $data['food_category']);
        $this->assertSame('Loose Milk', $data['food_item']);
        $this->assertSame('CHEMICAL', $data['lab_section']);
        $this->assertFalse($data['is_perishable']);
        $this->assertSame('TESTING', $data['status']);
        $this->assertNotNull($data['parameters_template']);
    }

    public function test_analyst_cannot_reach_de_blinded_endpoints(): void
    {
        $part = $this->makeAssignedPart(blindCode: 'BC-2026-000044');

        // Verification queue (full record) is closed to analysts.
        $this->getJson('/api/v1/verification/queue')->assertStatus(403);

        // The field endpoints that expose the business are closed too.
        $this->getJson('/api/v1/sampling-events')->assertStatus(403);
        $this->getJson('/api/v1/rapid-tests')->assertStatus(403);

        // And the part timeline by qr_token must not be a back door.
        $this->getJson("/api/v1/custody/parts/{$part->qr_token}")->assertStatus(403);
    }

    public function test_analyst_cannot_download_the_report_pdf(): void
    {
        $part = $this->makeAssignedPart(blindCode: 'BC-2026-000045', status: PartStatus::VERIFIED);
        $part->labResult->update(['report_pdf_path' => 'reports/x/y.pdf']);

        $this->get('/api/v1/reports/BC-2026-000045.pdf')->assertStatus(403);
    }
}
