<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateOrgaoRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $orgaoId = $this->route('orgao')?->id ?? $this->route('orgao');

        return [
            'sigla' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('orgao', 'sigla')
                    ->ignore($orgaoId)
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'nome' => ['nullable', 'string', 'max:255'],
        ];
    }
}
