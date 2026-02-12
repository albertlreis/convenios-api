<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreConvenioRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $uniqueCodigo = Rule::unique('convenio', 'codigo')
            ->where(fn ($query) => $query->whereNull('deleted_at'));

        $municipioExists = Rule::exists('municipio', 'id');

        return [
            'orgao_id' => ['nullable', Rule::exists('orgao', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'orgao_nome_informado' => ['nullable', 'string', 'max:255'],
            'numero_convenio' => ['nullable', 'string', 'max:255'],
            'ano_referencia' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'codigo' => ['nullable', 'string', 'max:32', $uniqueCodigo],
            'municipio_beneficiario_id' => ['nullable', $municipioExists],
            'municipio_beneficiario_nome_informado' => ['nullable', 'string', 'max:255'],
            'convenente_nome' => ['nullable', 'string', 'max:255'],
            'convenente_municipio_id' => ['nullable', $municipioExists],
            'convenente_municipio_nome_informado' => ['nullable', 'string', 'max:255'],
            'plano_interno' => ['nullable', 'string', 'max:32'],
            'planos_internos' => ['nullable', 'array'],
            'planos_internos.*' => ['string', 'max:32'],
            'objeto' => ['nullable', 'string'],
            'grupo_despesa' => ['nullable', 'string', 'max:255'],
            'quantidade_parcelas_informada' => ['nullable', 'integer', 'min:0'],
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d'],
            'valor_orgao' => ['nullable', 'numeric', 'min:0'],
            'valor_contrapartida' => ['nullable', 'numeric', 'min:0'],
            'valor_aditivo' => ['nullable', 'numeric', 'min:0'],
            'valor_total_informado' => ['nullable', 'numeric', 'min:0'],
            'valor_total_calculado' => ['nullable', 'numeric', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'dados_origem' => ['nullable', 'array'],
        ];
    }
}
