<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConvenioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'orgao_id' => $this->orgao_id,
            'numero_convenio' => $this->numero_convenio,
            'codigo' => $this->codigo,
            'municipio_beneficiario_id' => $this->municipio_beneficiario_id,
            'convenente_nome' => $this->convenente_nome,
            'convenente_municipio_id' => $this->convenente_municipio_id,
            'plano_interno' => $this->plano_interno,
            'objeto' => $this->objeto,
            'grupo_despesa' => $this->grupo_despesa,
            'data_inicio' => $this->data_inicio?->format('Y-m-d'),
            'data_fim' => $this->data_fim?->format('Y-m-d'),
            'valor_orgao' => $this->valor_orgao,
            'valor_contrapartida' => $this->valor_contrapartida,
            'valor_aditivo' => $this->valor_aditivo,
            'valor_total_informado' => $this->valor_total_informado,
            'valor_total_calculado' => $this->valor_total_calculado,
            'metadata' => $this->metadata,
            'parcelas_agg' => [
                'parcelas_total' => (int) ($this->parcelas_total ?? 0),
                'parcelas_pagas' => (int) ($this->parcelas_pagas ?? 0),
                'parcelas_em_aberto' => (int) ($this->parcelas_em_aberto ?? 0),
                'valor_previsto_total' => $this->decimal($this->valor_previsto_total ?? 0),
                'valor_pago_total' => $this->decimal($this->valor_pago_total ?? 0),
                'valor_em_aberto_total' => $this->decimal($this->valor_em_aberto_total ?? 0),
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'orgao' => OrgaoResource::make($this->whenLoaded('orgao')),
            'municipio_beneficiario' => MunicipioResource::make($this->whenLoaded('municipioBeneficiario')),
            'municipio_convenente' => MunicipioResource::make($this->whenLoaded('municipioConvenente')),
            'parcelas' => ParcelaResource::collection($this->whenLoaded('parcelas')),
        ];
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
