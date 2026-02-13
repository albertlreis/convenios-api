<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateMandatoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $mandatoId = $this->route('mandato')?->id ?? $this->route('mandato');
        $municipioId = $this->input('municipio_id') ?? $this->route('mandato')?->municipio_id;
        $anoEleicao = $this->input('ano_eleicao') ?? $this->route('mandato')?->ano_eleicao;
        $nrTurno = $this->input('nr_turno') ?? $this->route('mandato')?->nr_turno;

        return [
            'municipio_id' => ['sometimes', 'required', Rule::exists('municipio', 'id')],
            'prefeito_id' => ['sometimes', 'required', Rule::exists('prefeito', 'id')],
            'partido_id' => ['sometimes', 'nullable', Rule::exists('partido', 'id')],
            'ano_eleicao' => ['sometimes', 'required', 'integer', 'min:1900', 'max:2100'],
            'cd_eleicao' => ['sometimes', 'required', 'integer'],
            'dt_eleicao' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('mandato_prefeito', 'dt_eleicao')
                    ->ignore($mandatoId)
                    ->where(function ($query) use ($municipioId, $anoEleicao, $nrTurno): void {
                        $query->where('municipio_id', $municipioId)
                            ->where('ano_eleicao', $anoEleicao)
                            ->where('nr_turno', $nrTurno);
                    }),
            ],
            'nr_turno' => ['sometimes', 'required', 'integer', Rule::in([1, 2])],
            'nr_candidato' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'mandato_inicio' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'mandato_fim' => ['sometimes', 'required', 'date_format:Y-m-d', 'after_or_equal:mandato_inicio'],
            'mandato_consecutivo' => ['sometimes', 'nullable', 'integer', Rule::in([1, 2])],
            'reeleito' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}
