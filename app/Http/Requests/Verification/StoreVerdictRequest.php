<?php

namespace App\Http\Requests\Verification;

use App\Enums\Verdict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVerdictRequest extends FormRequest
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
            'verdict' => ['required', Rule::in(Verdict::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
