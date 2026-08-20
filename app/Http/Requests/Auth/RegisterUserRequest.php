<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Deliberately no `unique:users,email` rule — PRD PUB-05 requires the
 * registration response to be identical whether or not the email already
 * exists, so uniqueness is handled in the controller, not surfaced as a
 * validation error.
 */
class RegisterUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
