<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StorePartidoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'sigla' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('partido', 'sigla')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'nome' => ['nullable', 'string', 'max:255'],
            'numero' => [
                'nullable',
                'integer',
                'min:0',
                Rule::unique('partido', 'numero')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ];
    }
}
