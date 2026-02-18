<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreMandatoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'municipio_id' => ['required', Rule::exists('municipio', 'id')],
            'prefeito_id' => ['required', Rule::exists('prefeito', 'id')],
            'partido_id' => ['nullable', Rule::exists('partido', 'id')],
            'ano_eleicao' => ['required', 'integer', 'min:1900', 'max:2100'],
            'cd_eleicao' => ['required', 'integer'],
            'dt_eleicao' => [
                'required',
                'date_format:Y-m-d',
                Rule::unique('mandato_prefeito', 'dt_eleicao')->where(function ($query): void {
                    $query->where('municipio_id', $this->input('municipio_id'))
                        ->where('ano_eleicao', $this->input('ano_eleicao'))
                        ->where('nr_turno', $this->input('nr_turno'));
                }),
            ],
            'nr_turno' => ['required', 'integer', Rule::in([1, 2])],
            'nr_candidato' => ['nullable', 'integer', 'min:0'],
            'mandato_inicio' => ['required', 'date_format:Y-m-d'],
            'mandato_fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:mandato_inicio'],
            'mandato_consecutivo' => ['nullable', 'integer', Rule::in([1, 2])],
            'reeleito' => ['nullable', 'boolean'],
        ];
    }
}
