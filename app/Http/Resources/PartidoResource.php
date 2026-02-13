<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartidoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sigla' => $this->sigla,
            'nome' => $this->nome,
            'numero' => $this->numero,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'mandatos' => MandatoResource::collection($this->whenLoaded('mandatos')),
        ];
    }
}
