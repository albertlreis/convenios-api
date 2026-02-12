<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrefeitoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legacy_id' => $this->legacy_id,
            'nome_completo' => $this->nome_completo,
            'nome_urna' => $this->nome_urna,
            'dt_nascimento' => $this->dt_nascimento?->format('Y-m-d'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'mandatos' => MandatoResource::collection($this->whenLoaded('mandatos')),
        ];
    }
}
