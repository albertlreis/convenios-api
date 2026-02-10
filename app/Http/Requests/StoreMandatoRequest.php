<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreMandatoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'municipio_id' => ['nullable', Rule::exists('municipio', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'prefeito_id' => ['nullable', Rule::exists('prefeito', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'partido_id' => ['nullable', Rule::exists('partido', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'eleicao_id' => ['nullable', Rule::exists('eleicao', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d'],
            'mandato_consecutivo' => ['nullable', Rule::in([1, 2])],
            'reeleito' => ['nullable', 'boolean'],
            'situacao' => ['nullable', Rule::in(['EM_EXERCICIO', 'AFASTADO', 'CASSADO', 'INTERINO', 'ENCERRADO'])],
        ];
    }
}
