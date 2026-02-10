<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PatchParcelaPagamentoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'data_pagamento' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'valor_pago' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'valor_previsto' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'nota_empenho' => ['sometimes', 'nullable', 'string', 'max:50'],
            'data_ne' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'valor_empenhado' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'situacao' => ['sometimes', 'nullable', Rule::in(['PREVISTA', 'PAGA', 'CANCELADA'])],
            'observacoes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
