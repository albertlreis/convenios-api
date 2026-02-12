<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MandatoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legacy_id' => $this->legacy_id,
            'municipio_id' => $this->municipio_id,
            'prefeito_id' => $this->prefeito_id,
            'partido_id' => $this->partido_id,
            'ano_eleicao' => $this->ano_eleicao,
            'cd_eleicao' => $this->cd_eleicao,
            'dt_eleicao' => $this->dt_eleicao?->format('Y-m-d'),
            'nr_turno' => $this->nr_turno,
            'nr_candidato' => $this->nr_candidato,
            'mandato_inicio' => $this->mandato_inicio?->format('Y-m-d'),
            'mandato_fim' => $this->mandato_fim?->format('Y-m-d'),
            'mandato_consecutivo' => $this->mandato_consecutivo,
            'reeleito' => $this->reeleito,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'municipio' => MunicipioResource::make($this->whenLoaded('municipio')),
            'prefeito' => PrefeitoResource::make($this->whenLoaded('prefeito')),
            'partido' => PartidoResource::make($this->whenLoaded('partido')),
        ];
    }
}
