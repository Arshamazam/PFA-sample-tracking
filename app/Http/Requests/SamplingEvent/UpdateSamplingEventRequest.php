<?php

namespace App\Http\Requests\SamplingEvent;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Corrections and witness details, allowed only while the event is a draft
 * (enforced in the controller — finalized events are immutable via the API).
 */
class UpdateSamplingEventRequest extends FormRequest
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
            'food_item' => ['sometimes', 'string', 'max:255'],
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_perishable' => ['sometimes', 'boolean'],
            'collected_at' => ['sometimes', 'date'],
            'witness_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'witness_cnic' => ['sometimes', 'nullable', 'string', 'max:255'],
            'witness_signature' => ['sometimes', 'file', 'image', 'max:5120'],
        ];
    }
}
