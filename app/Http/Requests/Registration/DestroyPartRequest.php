<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class DestroyPartRequest extends FormRequest
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
            // Both are mandatory — destruction is an auditable, photographed act.
            'photo' => ['required', 'file', 'image', 'max:5120'],
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
