<?php

namespace App\Http\Requests;

class UpdatePrefeitoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'legacy_id' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'nome_completo' => ['sometimes', 'required', 'string', 'max:200'],
            'nome_urna' => ['sometimes', 'nullable', 'string', 'max:200'],
            'dt_nascimento' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }
}
