<?php

namespace App\Http\Requests\RapidTest;

use App\Enums\RapidTestDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRapidTestRequest extends FormRequest
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
        return [
            'premises_license' => ['required', 'string', 'max:255'],
            // Optional details used only if the premises must be auto-created.
            'premises_name' => ['sometimes', 'string', 'max:255'],
            'premises_address' => ['sometimes', 'string', 'max:255'],
            'premises_city' => ['sometimes', 'string', 'max:255'],

            'device' => ['required', Rule::in(RapidTestDevice::values())],
            'reading' => ['required', 'string', 'max:255'],
            'passed' => ['required', 'boolean'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'], // 5 MB
            'tested_at' => ['required', 'date'],
        ];
    }
}
