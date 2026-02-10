<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParcelaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'convenio_id' => $this->convenio_id,
            'numero' => $this->numero,
            'valor_previsto' => $this->valor_previsto,
            'valor_pago' => $this->valor_pago,
            'data_pagamento' => $this->data_pagamento?->format('Y-m-d'),
            'nota_empenho' => $this->nota_empenho,
            'data_ne' => $this->data_ne?->format('Y-m-d'),
            'valor_empenhado' => $this->valor_empenhado,
            'situacao' => $this->situacao,
            'observacoes' => $this->observacoes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'convenio' => ConvenioResource::make($this->whenLoaded('convenio')),
        ];
    }
}
