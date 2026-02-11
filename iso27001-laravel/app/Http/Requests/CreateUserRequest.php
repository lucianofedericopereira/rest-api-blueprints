<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A.14: Input validation for user creation.
 * Strict rules enforced before the request reaches the controller.
 */
final class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is handled by route middleware (role:admin)
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email:strict', 'max:180'],
            'password' => [
                'required',
                'string',
                'min:12',
                'max:128',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/',
            ],
            'role' => ['required', 'string', 'in:admin,manager,analyst,viewer'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'Password must contain uppercase, lowercase, digit and special character.',
            'role.in'        => 'Role must be one of: admin, manager, analyst, viewer.',
        ];
    }
}
