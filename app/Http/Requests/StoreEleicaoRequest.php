<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreEleicaoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'municipio_id' => ['nullable', Rule::exists('municipio', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'ano_eleicao' => ['nullable', 'integer', 'min:1900'],
            'dt_eleicao' => ['nullable', 'date_format:Y-m-d'],
            'turno' => ['nullable', Rule::in([1, 2])],
            'cd_eleicao' => ['nullable', 'string', 'max:255'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
