<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateMunicipioRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $municipioId = $this->route('municipio')?->id ?? $this->route('municipio');
        $uf = $this->input('uf', 'PA');

        return [
            'regiao_id' => ['sometimes', 'nullable', Rule::exists('regiao_integracao', 'id')],
            'nome' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('municipio', 'nome')
                    ->ignore($municipioId)
                    ->where(fn ($query) => $query->where('uf', $uf)),
            ],
            'uf' => ['sometimes', 'nullable', 'string', 'size:2'],
            'codigo_ibge' => [
                'sometimes',
                'nullable',
                'regex:/^[0-9]{7}$/',
                Rule::unique('municipio', 'codigo_ibge')
                    ->ignore($municipioId)
                    ->where(fn ($query) => $query->where('uf', $uf)),
            ],
            'codigo_tse' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                Rule::unique('municipio', 'codigo_tse')
                    ->ignore($municipioId)
                    ->where(fn ($query) => $query->where('uf', $uf)),
            ],
            'codigo_sigplan' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
