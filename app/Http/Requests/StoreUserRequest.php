<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'is_active' => ['required', 'boolean'],
            'force_password_change' => ['required', 'boolean'],
        ];
    }
}
