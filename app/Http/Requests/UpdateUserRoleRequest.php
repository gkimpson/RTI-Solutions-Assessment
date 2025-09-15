<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
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
            'role' => [
                'required',
                'string',
                Rule::in(User::getRoles()),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Role is required.',
            'role.in' => 'Invalid role specified. Valid roles are: '.implode(', ', User::getRoles()),
            'reason.max' => 'Reason cannot exceed 255 characters.',
        ];
    }
}
