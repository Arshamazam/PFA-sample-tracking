<?php

namespace Tests\Feature\Panel;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every panel section is reachable by its own role(s) and returns 403 for all
 * others. Uses index/landing GET routes per section as the access probe.
 */
class RouteRoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * section landing route => roles allowed.
     *
     * @return array<string, array{string, array<int, UserRole>}>
     */
    public static function matrix(): array
    {
        return [
            'registration receiving' => ['registration.receiving.create', [UserRole::REGISTRATION_OFFICER]],
            'registration blind' => ['registration.blind.create', [UserRole::REGISTRATION_OFFICER]],
            'registration section' => ['registration.section.create', [UserRole::REGISTRATION_OFFICER]],
            'registration file dispute' => ['registration.disputes.create', [UserRole::REGISTRATION_OFFICER]],
            'retention' => ['registration.retention.index', [UserRole::REGISTRATION_OFFICER, UserRole::ADMIN]],
            'lab queue' => ['lab.queue', [UserRole::LAB_ANALYST]],
            'verification queue' => ['verification.queue', [UserRole::VERIFYING_OFFICER]],
            'disputes index' => ['disputes.index', [UserRole::VERIFYING_OFFICER, UserRole::ADMIN]],
            'admin users' => ['admin.users.index', [UserRole::ADMIN]],
            'admin catalog' => ['admin.catalog.index', [UserRole::ADMIN]],
            'admin violations' => ['admin.violations.index', [UserRole::ADMIN]],
            'admin settings' => ['admin.settings.edit', [UserRole::ADMIN]],
            'admin events' => ['admin.events.index', [UserRole::ADMIN]],
            'fso events' => ['fso.events.index', [UserRole::FSO, UserRole::TRANSPORT]],
            'fso scan' => ['fso.scan.create', [UserRole::FSO, UserRole::TRANSPORT]],
        ];
    }

    /**
     * @param  array<int, UserRole>  $allowed
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('matrix')]
    public function test_each_section_enforces_its_roles(string $routeName, array $allowed): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role, 'must_change_password' => false]);

            $response = $this->actingAs($user)->get(route($routeName));

            if (in_array($role, $allowed, true)) {
                $response->assertOk();
            } else {
                $response->assertForbidden();
            }
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_inactive_user_is_locked_out(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN, 'is_active' => false, 'must_change_password' => false]);

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_must_change_password_intercepts_every_page(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN, 'must_change_password' => true]);

        $this->actingAs($user)->get(route('admin.users.index'))->assertRedirect(route('password.change'));
        // The change-password page itself is reachable.
        $this->actingAs($user)->get(route('password.change'))->assertOk();
    }
}
