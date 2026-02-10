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
            'municipio_id' => $this->municipio_id,
            'prefeito_id' => $this->prefeito_id,
            'partido_id' => $this->partido_id,
            'eleicao_id' => $this->eleicao_id,
            'inicio' => $this->inicio?->format('Y-m-d'),
            'fim' => $this->fim?->format('Y-m-d'),
            'mandato_consecutivo' => $this->mandato_consecutivo,
            'reeleito' => $this->reeleito,
            'situacao' => $this->situacao,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'municipio' => MunicipioResource::make($this->whenLoaded('municipio')),
            'prefeito' => PrefeitoResource::make($this->whenLoaded('prefeito')),
            'partido' => PartidoResource::make($this->whenLoaded('partido')),
            'eleicao' => EleicaoResource::make($this->whenLoaded('eleicao')),
        ];
    }
}
