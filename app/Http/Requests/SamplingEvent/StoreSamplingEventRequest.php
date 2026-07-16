<?php

namespace App\Http\Requests\SamplingEvent;

use Illuminate\Foundation\Http\FormRequest;

class StoreSamplingEventRequest extends FormRequest
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
            'premises_name' => ['sometimes', 'string', 'max:255'],
            'premises_address' => ['sometimes', 'string', 'max:255'],
            'premises_city' => ['sometimes', 'string', 'max:255'],

            'food_item' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'is_perishable' => ['required', 'boolean'],
            'collected_at' => ['required', 'date'],

            // Witness fields may be set now or via PATCH before finalize.
            'witness_name' => ['nullable', 'string', 'max:255'],
            'witness_cnic' => ['nullable', 'string', 'max:255'],

            // Optional linkage to a prior rapid test.
            'rapid_test_id' => ['nullable', 'string', 'exists:rapid_tests,id'],
        ];
    }
}
