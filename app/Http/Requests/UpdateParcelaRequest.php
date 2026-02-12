<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateParcelaRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $parcelaId = $this->route('parcela')?->id ?? $this->route('parcela');
        $convenioId = $this->input('convenio_id') ?? $this->route('parcela')?->convenio_id;

        return [
            'convenio_id' => ['sometimes', 'required', Rule::exists('convenio', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'numero' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('parcela', 'numero')
                    ->ignore($parcelaId)
                    ->where(function ($query) use ($convenioId): void {
                        $query->where('convenio_id', $convenioId)
                            ->whereNull('deleted_at');
                    }),
            ],
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
