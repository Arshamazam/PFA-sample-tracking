<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role = UserRole::FSO, bool $active = true): User
    {
        return User::factory()->create([
            'email' => strtolower($role->value).'@pfa.test',
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    public function test_login_returns_token_user_and_abilities(): void
    {
        $this->user(UserRole::FSO);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'fso@pfa.test',
            'password' => 'password',
            'device_name' => 'pixel-7',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.role', 'FSO')
            ->assertJsonStructure([
                'data' => ['token', 'user' => ['id', 'name', 'role'], 'abilities'],
                'meta' => ['token_type'],
            ]);

        $this->assertContains('sampling-events:create', $response->json('data.abilities'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->user(UserRole::FSO);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'fso@pfa.test',
            'password' => 'wrong',
            'device_name' => 'x',
        ])->assertStatus(422);
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $this->user(UserRole::FSO, active: false);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'fso@pfa.test',
            'password' => 'password',
            'device_name' => 'x',
        ])->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_returns_current_user(): void
    {
        $user = $this->user(UserRole::FSO);
        $token = $user->createToken('t', $user->role->abilities())->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'fso@pfa.test');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = $this->user(UserRole::FSO);
        $token = $user->createToken('t', $user->role->abilities())->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Reset the memoized guard so the next call re-authenticates from scratch
        // (in production each request is a fresh process; this mirrors that).
        $this->app['auth']->forgetGuards();

        // Same token should now be rejected.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_role_middleware_rejects_wrong_role(): void
    {
        $transport = $this->user(UserRole::TRANSPORT);
        $token = $transport->createToken('t', $transport->role->abilities())->plainTextToken;

        // rapid-tests is FSO-only.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/rapid-tests')
            ->assertStatus(403);
    }

    public function test_deactivated_user_with_valid_token_is_rejected(): void
    {
        $user = $this->user(UserRole::FSO);
        $token = $user->createToken('t', $user->role->abilities())->plainTextToken;

        // Token works while active.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')->assertOk();

        // Deactivate, then the same token must be rejected by the 'active' middleware.
        $user->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403);
    }
}
