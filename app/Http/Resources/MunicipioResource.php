<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MunicipioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legacy_id' => $this->legacy_id,
            'regiao_id' => $this->regiao_id,
            'nome' => $this->nome,
            'uf' => $this->uf,
            'codigo_ibge' => $this->codigo_ibge,
            'codigo_tse' => $this->codigo_tse,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'regiao_integracao' => RegiaoIntegracaoResource::make($this->whenLoaded('regiaoIntegracao')),
            'demografias' => MunicipioDemografiaResource::collection($this->whenLoaded('demografias')),
        ];
    }
}
