<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:50',
                Rule::unique('tags', 'name')->ignore($this->route('tag')),
            ],
            'color' => ['nullable', 'string', 'regex:/^#([a-f0-9]{6}|[a-f0-9]{3})$/i'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tag name is required.',
            'name.min' => 'Tag name must be at least 2 characters.',
            'name.max' => 'Tag name cannot exceed 50 characters.',
            'name.unique' => 'A tag with this name already exists.',
            'color.regex' => 'Color must be a valid hex color code (e.g., #ff0000 or #f00).',
        ];
    }
}
