<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => UserRole::cases(), 'role' => $request->query('role')]);
    }

    public function create(): View
    {
        return view('admin.users.create', ['roles' => UserRole::cases()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(UserRole::values())],
            'phone' => ['nullable', 'string', 'max:32'],
            'cnic' => ['nullable', 'string', 'max:32', 'unique:users,cnic'],
        ]);

        User::create([
            ...$validated,
            'is_active' => true,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user, 'roles' => UserRole::cases()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(UserRole::values())],
            'phone' => ['nullable', 'string', 'max:32'],
            'cnic' => ['nullable', 'string', 'max:32', Rule::unique('users', 'cnic')->ignore($user->id)],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (! $request->boolean('is_active') && $user->id === $request->user()->id) {
            throw ValidationException::withMessages(['is_active' => ['You cannot deactivate your own account.']]);
        }

        $user->fill(collect($validated)->except('password')->all());
        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
            $user->must_change_password = true;
        }
        $user->save();

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    public function resetPasswordFlag(User $user): RedirectResponse
    {
        $user->update(['must_change_password' => true]);

        return redirect()->route('admin.users.index')->with('status', "{$user->name} must change their password at next login.");
    }
}
