<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\ConvenioPlanoInterno;
use App\Models\Municipio;
use App\Models\MunicipioDemografia;
use App\Models\Orgao;
use App\Models\Parcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvenioFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_municipio_e_parcelas_em_aberto(): void
    {
        $orgao = Orgao::factory()->create();
        $municipioA = Municipio::factory()->create();
        $municipioB = Municipio::factory()->create();

        MunicipioDemografia::query()->create([
            'municipio_id' => $municipioA->id,
            'ano_ref' => 2023,
            'populacao' => 50000,
            'eleitores' => 30000,
        ]);

        MunicipioDemografia::query()->create([
            'municipio_id' => $municipioB->id,
            'ano_ref' => 2023,
            'populacao' => 10000,
            'eleitores' => 7000,
        ]);

        $convenioAberto = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'codigo' => 'AA:111/2026',
            'municipio_beneficiario_id' => $municipioA->id,
        ]);

        $convenioFechado = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'codigo' => 'BB:222/2026',
            'municipio_beneficiario_id' => $municipioB->id,
        ]);

        ConvenioPlanoInterno::query()->create([
            'convenio_id' => $convenioAberto->id,
            'plano_interno' => 'AA11BB22CC3',
            'origem' => 'test',
        ]);
        ConvenioPlanoInterno::query()->create([
            'convenio_id' => $convenioFechado->id,
            'plano_interno' => 'DD44EE55FF6',
            'origem' => 'test',
        ]);

        Parcela::factory()->create([
            'convenio_id' => $convenioAberto->id,
            'numero' => 1,
            'valor_previsto' => 1000,
            'valor_pago' => 100,
            'data_pagamento' => null,
            'situacao' => 'PREVISTA',
        ]);

        Parcela::factory()->create([
            'convenio_id' => $convenioFechado->id,
            'numero' => 1,
            'valor_previsto' => 1000,
            'valor_pago' => 1000,
            'data_pagamento' => '2026-02-01',
            'situacao' => 'PAGA',
        ]);

        $this->getJson('/api/v1/convenios?com_parcelas_em_aberto=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $convenioAberto->id);

        $this->getJson('/api/v1/convenios?municipio_id='.$municipioB->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $convenioFechado->id);
    }
}
