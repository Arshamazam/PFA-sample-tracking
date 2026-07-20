<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends each role to its home screen after login.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->to(match ($request->user()->role) {
            UserRole::REGISTRATION_OFFICER => route('registration.receiving.create'),
            UserRole::LAB_ANALYST => route('lab.queue'),
            UserRole::VERIFYING_OFFICER => route('verification.queue'),
            UserRole::ADMIN => route('admin.users.index'),
            UserRole::FSO, UserRole::TRANSPORT => route('fso.events.index'),
        });
    }
}
