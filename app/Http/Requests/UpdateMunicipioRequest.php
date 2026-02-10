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
            'legacy_id' => [
                'nullable',
                'integer',
                Rule::unique('municipio', 'legacy_id')
                    ->ignore($municipioId)
                    ->where(fn ($query) => $query->where('uf', $uf)->whereNull('deleted_at')),
            ],
            'regiao_id' => ['nullable', Rule::exists('regiao_integracao', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'nome' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('municipio', 'nome')
                    ->ignore($municipioId)
                    ->where(fn ($query) => $query->where('uf', $uf)->whereNull('deleted_at')),
            ],
            'uf' => ['nullable', 'string', 'size:2'],
            'codigo_ibge' => [
                'nullable',
                'regex:/^[0-9]{7}$/',
                Rule::unique('municipio', 'codigo_ibge')
                    ->ignore($municipioId)
                    ->where(fn ($query) => $query->where('uf', $uf)->whereNull('deleted_at')),
            ],
            'codigo_tse' => [
                'nullable',
                'integer',
                'min:0',
                Rule::unique('municipio', 'codigo_tse')
                    ->ignore($municipioId)
                    ->where(fn ($query) => $query->where('uf', $uf)->whereNull('deleted_at')),
            ],
        ];
    }
}
