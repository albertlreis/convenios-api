<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreMunicipioRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $uf = $this->input('uf', 'PA');

        return [
            'legacy_id' => ['nullable', 'integer', 'min:0'],
            'regiao_id' => ['nullable', Rule::exists('regiao_integracao', 'id')],
            'nome' => [
                'required',
                'string',
                'max:120',
                Rule::unique('municipio', 'nome')
                    ->where(fn ($query) => $query->where('uf', $uf)),
            ],
            'uf' => ['nullable', 'string', 'size:2'],
            'codigo_ibge' => [
                'nullable',
                'regex:/^[0-9]{7}$/',
                Rule::unique('municipio', 'codigo_ibge')
                    ->where(fn ($query) => $query->where('uf', $uf)),
            ],
            'codigo_tse' => [
                'nullable',
                'integer',
                'min:0',
                Rule::unique('municipio', 'codigo_tse')
                    ->where(fn ($query) => $query->where('uf', $uf)),
            ],
            'codigo_sigplan' => ['nullable', 'integer'],
        ];
    }
}
