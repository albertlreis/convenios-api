<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateConvenioRequest extends ApiFormRequest
{
    public function rules(): array
    {
        $convenioId = $this->route('convenio')?->id ?? $this->route('convenio');
        $orgaoId = $this->input('orgao_id') ?? $this->route('convenio')?->orgao_id;

        $uniqueNumeroConvenio = Rule::unique('convenio', 'numero_convenio')
            ->ignore($convenioId)
            ->where(fn ($query) => $query
                ->whereNull('deleted_at')
                ->where('orgao_id', $orgaoId));

        return [
            'orgao_id' => ['nullable', Rule::exists('orgao', 'id')->where(fn ($query) => $query->whereNull('deleted_at'))],
            'numero_convenio' => ['nullable', 'string', 'max:255', $uniqueNumeroConvenio],
            'ano_referencia' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'municipio_id' => ['nullable', Rule::exists('municipio', 'id')],
            'convenente_nome' => ['nullable', 'string', 'max:255'],
            'plano_interno' => ['nullable', 'string', 'max:32'],
            'planos_internos' => ['nullable', 'array'],
            'planos_internos.*' => ['string', 'max:32'],
            'objeto' => ['nullable', 'string'],
            'grupo_despesa' => ['nullable', 'string', 'max:255'],
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d'],
            'valor_orgao' => ['nullable', 'numeric', 'min:0'],
            'valor_contrapartida' => ['nullable', 'numeric', 'min:0'],
            'valor_aditivo' => ['nullable', 'numeric', 'min:0'],
            'valor_total_informado' => ['nullable', 'numeric', 'min:0'],
            'valor_total_calculado' => ['nullable', 'numeric', 'min:0'],
            'dados_origem' => ['nullable', 'array'],
        ];
    }
}
