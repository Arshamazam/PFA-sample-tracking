<?php

namespace App\Http\Requests\SamplingEvent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSamplingEventRequest extends FormRequest
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
            'premises_license' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['DRAFT', 'FINALIZED'])],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
