<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Municipio;
use App\Models\Orgao;
use App\Models\Parcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvenioCompositeKeyAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_permite_numero_repetido_em_orgaos_diferentes_e_bloqueia_no_mesmo_orgao(): void
    {
        $orgaoA = Orgao::factory()->create();
        $orgaoB = Orgao::factory()->create();
        $municipio = Municipio::factory()->create();

        $this->postJson('/api/v1/convenios', [
            'orgao_id' => $orgaoA->id,
            'municipio_id' => $municipio->id,
            'numero_convenio' => 'CV-123/2026',
        ])->assertCreated();

        $this->postJson('/api/v1/convenios', [
            'orgao_id' => $orgaoB->id,
            'municipio_id' => $municipio->id,
            'numero_convenio' => 'CV-123/2026',
        ])->assertCreated();

        $this->postJson('/api/v1/convenios', [
            'orgao_id' => $orgaoA->id,
            'municipio_id' => $municipio->id,
            'numero_convenio' => 'CV-123/2026',
        ])->assertStatus(422);
    }

    public function test_dashboard_top_10_por_valor_em_aberto_retorna_ordenado(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SEA']);
        $municipio = Municipio::factory()->create(['nome' => 'Municipio Top']);

        $convenioMaior = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'municipio_id' => $municipio->id,
            'numero_convenio' => 'CV-TOP-1',
        ]);
        $convenioMenor = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'municipio_id' => $municipio->id,
            'numero_convenio' => 'CV-TOP-2',
        ]);

        Parcela::factory()->create([
            'convenio_id' => $convenioMaior->id,
            'numero' => 1,
            'valor_previsto' => 2000,
            'valor_pago' => 100,
            'data_pagamento' => null,
            'situacao' => 'PREVISTA',
        ]);
        Parcela::factory()->create([
            'convenio_id' => $convenioMenor->id,
            'numero' => 1,
            'valor_previsto' => 1000,
            'valor_pago' => 600,
            'data_pagamento' => null,
            'situacao' => 'PREVISTA',
        ]);

        $this->getJson('/api/v1/convenios?com_parcelas_em_aberto=1&sort=-valor_em_aberto&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.results.0.id', $convenioMaior->id)
            ->assertJsonPath('data.results.1.id', $convenioMenor->id)
            ->assertJsonPath('data.results.0.parcelas_agg.valor_em_aberto_total', '1900.00')
            ->assertJsonPath('data.results.1.parcelas_agg.valor_em_aberto_total', '400.00');
    }
}
