<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreConvenioRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $uniqueCodigo = Rule::unique('convenio', 'codigo')
            ->where(fn ($query) => $query->whereNull('deleted_at'));

        $municipioExists = Rule::exists('municipio', 'id')
            ->where(fn ($query) => $query->whereNull('deleted_at'));

        return [
            'orgao_id' => ['nullable', Rule::exists('orgao', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'numero_convenio' => ['nullable', 'string', 'max:255'],
            'codigo' => ['nullable', 'regex:/^[A-Z0-9]{2,20}:\d{3}\/\d{4}$/', $uniqueCodigo],
            'municipio_beneficiario_id' => ['nullable', $municipioExists],
            'convenente_nome' => ['nullable', 'string', 'max:255'],
            'convenente_municipio_id' => ['nullable', $municipioExists],
            'plano_interno' => ['nullable', 'regex:/^[A-Za-z0-9]{11}$/'],
            'objeto' => ['nullable', 'string'],
            'grupo_despesa' => ['nullable', 'string', 'max:255'],
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d'],
            'valor_orgao' => ['nullable', 'numeric', 'min:0'],
            'valor_contrapartida' => ['nullable', 'numeric', 'min:0'],
            'valor_aditivo' => ['nullable', 'numeric', 'min:0'],
            'valor_total_informado' => ['nullable', 'numeric', 'min:0'],
            'valor_total_calculado' => ['nullable', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
