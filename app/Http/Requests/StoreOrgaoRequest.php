<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreOrgaoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'sigla' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('orgao', 'sigla')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'nome' => ['nullable', 'string', 'max:255'],
        ];
    }
}
