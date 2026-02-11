<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['sometimes', 'email:strict', 'max:180'],
            'password' => [
                'sometimes',
                'string',
                'min:12',
                'max:128',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/',
            ],
            'role' => ['sometimes', 'string', 'in:admin,manager,analyst,viewer'],
        ];
    }
}
