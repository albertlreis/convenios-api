<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StorePartidoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'sigla' => [
                'required',
                'string',
                'max:10',
                Rule::unique('partido', 'sigla'),
            ],
            'nome' => ['nullable', 'string', 'max:120'],
            'numero' => [
                'nullable',
                'integer',
                'min:0',
                'max:32767',
                Rule::unique('partido', 'numero'),
            ],
        ];
    }
}
