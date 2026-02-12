<?php

namespace App\Http\Requests;

class StorePrefeitoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'legacy_id' => ['nullable', 'integer', 'min:0'],
            'nome_completo' => ['required', 'string', 'max:200'],
            'nome_urna' => ['nullable', 'string', 'max:200'],
            'dt_nascimento' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
