<?php

namespace App\Http\Requests\SamplingEvent;

use App\Enums\PartRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddSamplePartRequest extends FormRequest
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
            'role' => ['required', Rule::in(PartRole::values())],
            'seal_number' => ['required', 'string', 'max:255'],
            'seal_photo' => ['required', 'file', 'image', 'max:5120'],
        ];
    }
}
