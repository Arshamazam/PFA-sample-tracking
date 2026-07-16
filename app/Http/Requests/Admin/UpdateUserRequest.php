<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(UserRole::values())],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'cnic' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('users', 'cnic')->ignore($userId)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
