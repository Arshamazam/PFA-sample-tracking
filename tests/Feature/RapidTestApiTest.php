<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Premises;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RapidTestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_fso_can_record_a_rapid_test_and_auto_creates_unknown_premises(): void
    {
        $fso = User::factory()->create(['role' => UserRole::FSO]);
        Sanctum::actingAs($fso, $fso->role->abilities());

        $response = $this->postJson('/api/v1/rapid-tests', [
            'premises_license' => 'PFA-LHR-2025-99999',
            'device' => 'LACTOSCAN',
            'reading' => 'SNF 7.1%',
            'passed' => false,
            'tested_at' => '2026-07-15T08:30:00Z',
            'photo' => UploadedFile::fake()->image('reading.jpg'),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.device', 'LACTOSCAN')
            ->assertJsonPath('data.passed', false);

        // Unknown license was auto-created as a MANUAL fallback premises.
        $this->assertDatabaseHas('premises', [
            'license_no' => 'PFA-LHR-2025-99999',
            'source' => 'MANUAL',
        ]);

        // Photo stored on the private disk.
        Storage::disk('local')->assertExists($response->json('data.photo_path'));
    }

    public function test_stored_file_is_served_through_the_protected_route(): void
    {
        $fso = User::factory()->create(['role' => UserRole::FSO]);
        Sanctum::actingAs($fso, $fso->role->abilities());

        $path = $this->postJson('/api/v1/rapid-tests', [
            'premises_license' => Premises::factory()->create()->license_no,
            'device' => 'OTHER',
            'reading' => 'n/a',
            'passed' => true,
            'tested_at' => '2026-07-15T08:30:00Z',
            'photo' => UploadedFile::fake()->image('r.jpg'),
        ])->json('data.photo_path');

        $this->get('/api/v1/files/'.$path)->assertOk();
    }

    public function test_file_route_blocks_path_traversal(): void
    {
        $fso = User::factory()->create(['role' => UserRole::FSO]);
        Sanctum::actingAs($fso, $fso->role->abilities());

        $this->get('/api/v1/files/'.rawurlencode('../../.env'))->assertNotFound();
    }
}
