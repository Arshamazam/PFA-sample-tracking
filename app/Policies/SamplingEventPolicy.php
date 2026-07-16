<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SamplingEvent;
use App\Models\User;

/**
 * FSOs may only see and modify their own sampling events. ADMIN bypasses all
 * checks via before().
 */
class SamplingEventPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role === UserRole::ADMIN ? true : null;
    }

    public function view(User $user, SamplingEvent $event): bool
    {
        return $event->fso_id === $user->id;
    }

    public function update(User $user, SamplingEvent $event): bool
    {
        return $event->fso_id === $user->id;
    }
}
