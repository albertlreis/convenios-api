<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EleicaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'municipio_id' => $this->municipio_id,
            'ano_eleicao' => $this->ano_eleicao,
            'dt_eleicao' => $this->dt_eleicao?->format('Y-m-d'),
            'turno' => $this->turno,
            'cd_eleicao' => $this->cd_eleicao,
            'cargo' => $this->cargo,
            'tipo' => $this->tipo,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'municipio' => MunicipioResource::make($this->whenLoaded('municipio')),
        ];
    }
}
