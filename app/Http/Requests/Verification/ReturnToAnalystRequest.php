<?php

namespace App\Http\Requests\Verification;

use Illuminate\Foundation\Http\FormRequest;

class ReturnToAnalystRequest extends FormRequest
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
            // Returning work to the analyst always needs a reason.
            'notes' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
