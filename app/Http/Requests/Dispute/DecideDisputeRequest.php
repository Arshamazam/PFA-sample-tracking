<?php

namespace App\Http\Requests\Dispute;

use App\Enums\DisputeStatus;
use App\Enums\LabSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideDisputeRequest extends FormRequest
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
            'decision' => ['required', Rule::in([DisputeStatus::ACCEPTED->value, DisputeStatus::REJECTED->value])],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
            // Optional override of the retest section; defaults to the original's.
            'lab_section' => ['nullable', Rule::in(LabSection::values())],
        ];
    }
}
