<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\ConvenioPlanoInterno;
use App\Models\Municipio;
use App\Models\Orgao;
use App\Models\Parcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvenioUnifiedDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_retorna_convenio_parcelas_paginadas_e_agregados(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SEDET']);
        $municipio = Municipio::factory()->create(['nome' => 'Belem '.uniqid()]);

        $convenio = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'municipio_id' => $municipio->id,
            'numero_convenio' => 'CV-999/2026',
        ]);

        ConvenioPlanoInterno::query()->create([
            'convenio_id' => $convenio->id,
            'plano_interno' => 'PI999999999',
        ]);

        Parcela::factory()->create([
            'convenio_id' => $convenio->id,
            'numero' => 1,
            'valor_previsto' => 1000,
            'valor_pago' => 1000,
            'data_pagamento' => '2026-02-10',
            'situacao' => 'PAGA',
        ]);
        Parcela::factory()->create([
            'convenio_id' => $convenio->id,
            'numero' => 2,
            'valor_previsto' => 1000,
            'valor_pago' => 250,
            'data_pagamento' => null,
            'situacao' => 'PREVISTA',
        ]);

        $response = $this->getJson("/api/v1/convenios/{$convenio->id}?parcelas_per_page=1&parcelas_page=1")
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('data.convenio.id', $convenio->id)
            ->assertJsonPath('data.agregados.total_parcelas', 2)
            ->assertJsonPath('data.agregados.parcelas_pagas', 1)
            ->assertJsonPath('data.parcelas.pagination.total', 2)
            ->assertJsonCount(1, 'data.parcelas.results');

        $planosInternos = collect($response->json('data.convenio.planos_internos', []));
        $this->assertTrue($planosInternos->contains('PI999999999'));
    }

    public function test_filtros_lookup_retorna_orgaos_municipios_e_pi(): void
    {
        $orgao = Orgao::factory()->create(['sigla' => 'SEFIN', 'nome' => 'Secretaria da Fazenda']);
        $municipio = Municipio::factory()->create(['nome' => 'Santarem '.uniqid()]);
        $convenio = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'municipio_id' => $municipio->id,
        ]);

        ConvenioPlanoInterno::query()->create([
            'convenio_id' => $convenio->id,
            'plano_interno' => 'PI123ABC999',
        ]);

        $this->getJson('/api/v1/convenios/filtros?q=pi123')
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('data.planos_internos.0.codigo', 'PI123ABC999');
    }
}
