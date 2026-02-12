<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConvenioImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'arquivo_nome' => $this->arquivo_nome,
            'status' => $this->status,
            'total_lista_rows' => $this->total_lista_rows,
            'total_parcelas_rows' => $this->total_parcelas_rows,
            'total_pi_rows' => $this->total_pi_rows,
            'total_issues' => $this->total_issues,
            'total_processados' => $this->total_processados,
            'total_pendencias' => $this->total_pendencias,
            'resumo' => $this->resumo,
            'confirmado_em' => $this->confirmado_em?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'preview' => [
                'lista_rows' => $this->whenLoaded('listaRows', fn () => $this->listaRows->map(fn ($row) => [
                    'id' => $row->id,
                    'row_number' => $row->row_number,
                    'status' => $row->status,
                    'issues' => $row->issues ?? [],
                    'normalized_data' => $row->normalized_data ?? [],
                ])),
                'parcelas_rows' => $this->whenLoaded('parcelasRows', fn () => $this->parcelasRows->map(fn ($row) => [
                    'id' => $row->id,
                    'row_number' => $row->row_number,
                    'status' => $row->status,
                    'issues' => $row->issues ?? [],
                    'normalized_data' => $row->normalized_data ?? [],
                ])),
                'pi_rows' => $this->whenLoaded('piRows', fn () => $this->piRows->map(fn ($row) => [
                    'id' => $row->id,
                    'row_number' => $row->row_number,
                    'status' => $row->status,
                    'issues' => $row->issues ?? [],
                    'normalized_data' => $row->normalized_data ?? [],
                ])),
                'pending_items' => $this->whenLoaded('pendingItems', fn () => $this->pendingItems->map(fn ($item) => [
                    'id' => $item->id,
                    'source_sheet' => $item->source_sheet,
                    'source_row_number' => $item->source_row_number,
                    'reference_key' => $item->reference_key,
                    'reason' => $item->reason,
                    'payload' => $item->payload ?? [],
                    'resolved_at' => $item->resolved_at?->format('Y-m-d H:i:s'),
                ])),
            ],
        ];
    }
}

