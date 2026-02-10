<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreMunicipioDemografiaRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'municipio_id' => ['nullable', Rule::exists('municipio', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'ano_ref' => [
                'nullable',
                'integer',
                'min:1900',
                Rule::unique('municipio_demografia', 'ano_ref')->where(function ($query): void {
                    $query->where('municipio_id', $this->input('municipio_id'))
                        ->whereNull('deleted_at');
                }),
            ],
            'populacao' => ['nullable', 'integer', 'min:0'],
            'eleitores' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
