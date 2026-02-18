<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdatePartidoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $partidoId = $this->route('partido')?->id ?? $this->route('partido');

        return [
            'sigla' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                Rule::unique('partido', 'sigla')
                    ->ignore($partidoId),
            ],
            'nome' => ['sometimes', 'nullable', 'string', 'max:120'],
            'numero' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:32767',
                Rule::unique('partido', 'numero')
                    ->ignore($partidoId),
            ],
        ];
    }
}
