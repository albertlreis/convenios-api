<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreMunicipioRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $uf = $this->input('uf', 'PA');

        return [
            'legacy_id' => [
                'nullable',
                'integer',
                Rule::unique('municipio', 'legacy_id')
                    ->where(fn ($query) => $query->where('uf', $uf)->whereNull('deleted_at')),
            ],
            'regiao_id' => ['nullable', Rule::exists('regiao_integracao', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'nome' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('municipio', 'nome')
                    ->where(fn ($query) => $query->where('uf', $uf)->whereNull('deleted_at')),
            ],
            'uf' => ['nullable', 'string', 'size:2'],
            'codigo_ibge' => [
                'nullable',
                'regex:/^[0-9]{7}$/',
                Rule::unique('municipio', 'codigo_ibge')
                    ->where(fn ($query) => $query->where('uf', $uf)->whereNull('deleted_at')),
            ],
            'codigo_tse' => [
                'nullable',
                'integer',
                'min:0',
                Rule::unique('municipio', 'codigo_tse')
                    ->where(fn ($query) => $query->where('uf', $uf)->whereNull('deleted_at')),
            ],
        ];
    }
}
