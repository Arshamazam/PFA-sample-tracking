<?php

namespace App\Http\Requests\Registration;

use App\Enums\LabSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSectionRequest extends FormRequest
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
            'lab_section' => ['required', Rule::in(LabSection::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
