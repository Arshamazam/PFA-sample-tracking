<?php

namespace App\Http\Requests\Admin;

use App\Enums\LabSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestCatalogRequest extends FormRequest
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
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'food_category' => [$required, 'string', 'max:255'],
            'lab_section' => [$required, Rule::in(LabSection::values())],
            'test_name' => [$required, 'string', 'max:255'],
            'tat_hours' => [$required, 'integer', 'min:1'],
            'parameters' => [$required, 'array', 'min:1'],
            'parameters.*.name' => ['required', 'string', 'max:255'],
            'parameters.*.unit' => ['nullable', 'string', 'max:64'],
            'parameters.*.permissible_limit' => ['nullable', 'string', 'max:64'],
        ];
    }
}
