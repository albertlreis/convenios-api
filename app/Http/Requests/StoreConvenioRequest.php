<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreConvenioRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $orgaoId = $this->input('orgao_id');
        $uniqueNumeroConvenio = Rule::unique('convenio', 'numero_convenio')
            ->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('orgao_id', $orgaoId));

        return [
            'orgao_id' => ['nullable', Rule::exists('orgao', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'numero_convenio' => ['nullable', 'string', 'max:255', $uniqueNumeroConvenio],
            'ano_referencia' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'municipio_id' => ['nullable', Rule::exists('municipio', 'id')],
            'convenente_nome' => ['nullable', 'string', 'max:255'],
            'plano_interno' => ['nullable', 'string', 'max:32'],
            'planos_internos' => ['nullable', 'array'],
            'planos_internos.*' => ['string', 'max:32'],
            'objeto' => ['nullable', 'string'],
            'grupo_despesa' => ['nullable', 'string', 'max:255'],
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d'],
            'valor_orgao' => ['nullable', 'numeric', 'min:0'],
            'valor_contrapartida' => ['nullable', 'numeric', 'min:0'],
            'valor_aditivo' => ['nullable', 'numeric', 'min:0'],
            'valor_total_informado' => ['nullable', 'numeric', 'min:0'],
            'valor_total_calculado' => ['nullable', 'numeric', 'min:0'],
            'dados_origem' => ['nullable', 'array'],
            'parcelas' => ['nullable', 'array'],
            'parcelas.*.id' => ['nullable', 'integer'],
            'parcelas.*.delete' => ['nullable', 'boolean'],
            'parcelas.*.numero' => ['nullable', 'integer', 'min:1'],
            'parcelas.*.valor_previsto' => ['nullable', 'numeric', 'min:0'],
            'parcelas.*.valor_pago' => ['nullable', 'numeric', 'min:0'],
            'parcelas.*.data_pagamento' => ['nullable', 'date_format:Y-m-d'],
            'parcelas.*.nota_empenho' => ['nullable', 'string', 'max:50'],
            'parcelas.*.data_ne' => ['nullable', 'date_format:Y-m-d'],
            'parcelas.*.valor_empenhado' => ['nullable', 'numeric', 'min:0'],
            'parcelas.*.situacao' => ['nullable', Rule::in(['PREVISTA', 'PAGA', 'CANCELADA'])],
            'parcelas.*.observacoes' => ['nullable', 'string'],
        ];
    }
}
