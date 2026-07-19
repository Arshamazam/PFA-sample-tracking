<?php

namespace App\Http\Requests\Dispute;

use Illuminate\Foundation\Http\FormRequest;

class FileDisputeRequest extends FormRequest
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
            'event_code' => ['required', 'string'],
            'filed_by_name' => ['required', 'string', 'max:255'],
            'filed_by_phone' => ['required', 'string', 'max:32'],
            'filed_by_cnic' => ['nullable', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
