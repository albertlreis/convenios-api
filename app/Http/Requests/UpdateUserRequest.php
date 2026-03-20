<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($userId)
                    ->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'force_password_change' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
