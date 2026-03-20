<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class ResetUserPasswordRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(8)],
            'force_password_change' => ['nullable', 'boolean'],
        ];
    }
}
