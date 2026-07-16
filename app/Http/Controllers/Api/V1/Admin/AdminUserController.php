<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Minimal staff administration. Accounts are deactivated, never deleted, so the
 * custody trail keeps referring to a real user.
 */
class AdminUserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'role' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = User::query()->orderBy('name');

        if (isset($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return UserResource::collection(
            $query->paginate($validated['per_page'] ?? 20)->withQueryString()
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->safe()->all(),
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Update a user, including deactivation/reactivation via is_active.
     */
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        // An admin locking themselves out would leave the system unadministrable.
        if ($request->has('is_active')
            && ! $request->boolean('is_active')
            && $user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'is_active' => ['You cannot deactivate your own account.'],
            ]);
        }

        $user->update($request->safe()->all());

        return new UserResource($user->refresh());
    }
}
