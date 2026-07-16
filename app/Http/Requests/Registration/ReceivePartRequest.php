<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePartRequest extends FormRequest
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
            'seal_intact' => ['required', 'boolean'],
            // The officer confirms the seal number on the sample matches the record.
            'seal_number_confirmed' => ['required', 'boolean'],
            // Receiving-side seal photo is always mandatory, including on rejection.
            'seal_photo' => ['required', 'file', 'image', 'max:5120'],
            // Mandatory for perishables — enforced by the custody state machine,
            // which owns the cold-chain rule.
            'temperature_c' => ['nullable', 'numeric', 'between:-50,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
