<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreMunicipioDemografiaRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'municipio_id' => ['required', Rule::exists('municipio', 'id')],
            'ano_ref' => [
                'required',
                'integer',
                'min:1900',
                Rule::unique('demografia_municipio', 'ano_ref')->where(function ($query): void {
                    $query->where('municipio_id', $this->input('municipio_id'));
                }),
            ],
            'populacao' => ['required', 'integer', 'min:0'],
            'eleitores' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
