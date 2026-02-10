<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StorePrefeitoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'nome_completo' => ['nullable', 'string', 'max:255'],
            'nome_urna' => ['nullable', 'string', 'max:255'],
            'data_nascimento' => ['nullable', 'date_format:Y-m-d'],
            'cpf_hash' => ['nullable', 'string', 'max:255'],
            'chave' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('prefeito', 'chave')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ];
    }
}
