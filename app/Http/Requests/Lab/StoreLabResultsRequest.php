<?php

namespace App\Http\Requests\Lab;

use Illuminate\Foundation\Http\FormRequest;

class StoreLabResultsRequest extends FormRequest
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
            'parameters' => ['required', 'array', 'min:1'],
            'parameters.*.name' => ['required', 'string', 'max:255'],
            'parameters.*.value' => ['required'],
            'parameters.*.unit' => ['nullable', 'string', 'max:64'],
            'parameters.*.permissible_limit' => ['nullable', 'string', 'max:64'],
            'parameters.*.within_limit' => ['required', 'boolean'],
            // Parameters outside the catalog template must be flagged explicitly.
            'parameters.*.is_additional' => ['sometimes', 'boolean'],

            'report_photo' => ['required', 'file', 'image', 'max:5120'],

            // The verdict is the verifying officer's call, never the analyst's.
            // Reject the attempt loudly rather than silently dropping it.
            'verdict' => ['prohibited'],
            'verdict_at' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'verdict.prohibited' => 'Analysts may not set a verdict; that is the verifying officer\'s decision.',
            'verdict_at.prohibited' => 'Analysts may not set a verdict; that is the verifying officer\'s decision.',
        ];
    }
}
