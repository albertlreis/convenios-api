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
            'ano_referencia' => $this->ano_referencia,
            'municipio_id' => $this->municipio_id,
            'convenente_nome' => $this->convenente_nome,
            'plano_interno' => $this->when(
                $this->relationLoaded('planosInternos'),
                fn () => $this->planosInternos->pluck('plano_interno')->first()
            ),
            'planos_internos' => $this->when(
                $this->relationLoaded('planosInternos'),
                fn () => $this->planosInternos->pluck('plano_interno')->values()
            ),
            'planos_internos_detalhes' => $this->when(
                $this->relationLoaded('planosInternos'),
                fn () => $this->planosInternos->map(fn ($pi) => [
                    'id' => $pi->id,
                    'codigo' => $pi->plano_interno,
                ])->values()
            ),
            'objeto' => $this->objeto,
            'grupo_despesa' => $this->grupo_despesa,
            'data_inicio' => $this->data_inicio?->format('Y-m-d'),
            'data_fim' => $this->data_fim?->format('Y-m-d'),
            'valor_orgao' => $this->valor_orgao,
            'valor_contrapartida' => $this->valor_contrapartida,
            'valor_aditivo' => $this->valor_aditivo,
            'valor_total_informado' => $this->valor_total_informado,
            'valor_total_calculado' => $this->valor_total_calculado,
            'dados_origem' => $this->dados_origem,
            'parcelas_agg' => [
                'parcelas_total' => (int) ($this->parcelas_total ?? 0),
                'parcelas_pagas' => (int) ($this->parcelas_pagas ?? 0),
                'parcelas_em_aberto' => (int) ($this->parcelas_em_aberto ?? 0),
                'valor_previsto_total' => $this->decimal($this->valor_previsto_total ?? 0),
                'valor_pago_total' => $this->decimal($this->valor_pago_total ?? 0),
                'valor_em_aberto_total' => $this->decimal($this->valor_em_aberto_total ?? 0),
                'percentual_execucao' => $this->calcExecucaoPercentual(
                    (float) ($this->valor_previsto_total ?? 0),
                    (float) ($this->valor_pago_total ?? 0)
                ),
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'orgao' => OrgaoResource::make($this->whenLoaded('orgao')),
            'municipio' => MunicipioResource::make($this->whenLoaded('municipio')),
            'parcelas' => ParcelaResource::collection($this->whenLoaded('parcelas')),
        ];
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function calcExecucaoPercentual(float $valorTotalParcelas, float $valorPago): string
    {
        if ($valorTotalParcelas <= 0) {
            return '0.00';
        }

        $percentual = max(0, min(100, ($valorPago / $valorTotalParcelas) * 100));

        return number_format($percentual, 2, '.', '');
    }
}
