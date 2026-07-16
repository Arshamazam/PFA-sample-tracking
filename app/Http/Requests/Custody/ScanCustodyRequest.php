<?php

namespace App\Http\Requests\Custody;

use App\Enums\PartStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScanCustodyRequest extends FormRequest
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
            'qr_token' => ['required', 'string'],
            'to_status' => ['required', Rule::in(PartStatus::values())],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_note' => ['nullable', 'string', 'max:255'],
            'temperature_c' => ['nullable', 'numeric', 'between:-50,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }
}
