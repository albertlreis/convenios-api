<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MunicipioDemografiaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'municipio_id' => $this->municipio_id,
            'ano_ref' => $this->ano_ref,
            'populacao' => $this->populacao,
            'eleitores' => $this->eleitores,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'municipio' => MunicipioResource::make($this->whenLoaded('municipio')),
        ];
    }
}
