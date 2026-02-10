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
            'nome_completo' => $this->nome_completo,
            'nome_urna' => $this->nome_urna,
            'data_nascimento' => $this->data_nascimento?->format('Y-m-d'),
            'cpf_hash' => $this->cpf_hash,
            'chave' => $this->chave,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'mandatos' => MandatoResource::collection($this->whenLoaded('mandatos')),
        ];
    }
}
