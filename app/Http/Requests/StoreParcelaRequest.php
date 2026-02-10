<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreParcelaRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'convenio_id' => ['nullable', Rule::exists('convenio', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'numero' => ['nullable', 'integer', 'min:1'],
            'valor_previsto' => ['nullable', 'numeric', 'min:0'],
            'valor_pago' => ['nullable', 'numeric', 'min:0'],
            'data_pagamento' => ['nullable', 'date_format:Y-m-d'],
            'nota_empenho' => ['nullable', 'string', 'max:50'],
            'data_ne' => ['nullable', 'date_format:Y-m-d'],
            'valor_empenhado' => ['nullable', 'numeric', 'min:0'],
            'situacao' => ['nullable', Rule::in(['PREVISTA', 'PAGA', 'CANCELADA'])],
            'observacoes' => ['nullable', 'string'],
        ];
    }
}
