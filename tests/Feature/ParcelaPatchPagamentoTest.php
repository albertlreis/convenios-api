<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Parcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParcelaPatchPagamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_patch_pagamento_define_situacao_paga_quando_condicao_atendida(): void
    {
        $convenio = Convenio::factory()->create();

        $parcela = Parcela::factory()->create([
            'convenio_id' => $convenio->id,
            'numero' => 1,
            'valor_previsto' => 1000.00,
            'valor_pago' => null,
            'data_pagamento' => null,
            'situacao' => 'PREVISTA',
        ]);

        $this->patchJson("/api/parcelas/{$parcela->id}/pagamento", [
            'data_pagamento' => '2026-02-01',
            'valor_pago' => 1000.00,
            'nota_empenho' => 'NE-12345',
            'data_ne' => '2026-01-15',
            'valor_empenhado' => 1000.00,
        ])
            ->assertOk()
            ->assertJsonPath('data.situacao', 'PAGA')
            ->assertJsonPath('data.nota_empenho', 'NE-12345');

        $this->assertDatabaseHas('parcela', [
            'id' => $parcela->id,
            'situacao' => 'PAGA',
            'nota_empenho' => 'NE-12345',
        ]);
    }
}
