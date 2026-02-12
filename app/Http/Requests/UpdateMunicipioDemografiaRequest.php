<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateMunicipioDemografiaRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $demografiaId = $this->route('municipioDemografia')?->id ?? $this->route('municipioDemografia');
        $municipioId = $this->input('municipio_id') ?? $this->route('municipioDemografia')?->municipio_id;

        return [
            'municipio_id' => ['sometimes', 'required', Rule::exists('municipio', 'id')],
            'ano_ref' => [
                'sometimes',
                'required',
                'integer',
                'min:1900',
                Rule::unique('demografia_municipio', 'ano_ref')
                    ->ignore($demografiaId)
                    ->where(function ($query) use ($municipioId): void {
                        $query->where('municipio_id', $municipioId);
                    }),
            ],
            'populacao' => ['sometimes', 'required', 'integer', 'min:0'],
            'eleitores' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
