<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\ConvenioPlanoInterno;
use App\Models\Municipio;
use App\Models\MunicipioDemografia;
use App\Models\Orgao;
use App\Models\Parcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_mandato_crud(): void
    {
        $municipio = Municipio::factory()->create();

        $prefeitoId = DB::table('prefeito')->insertGetId([
            'nome_completo' => 'Prefeito Teste',
            'nome_urna' => 'Teste',
            'dt_nascimento' => '1980-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $partidoId = DB::table('partido')->insertGetId([
            'sigla' => 'PTT',
            'nome' => 'Partido Teste',
            'numero' => 99,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'municipio_id' => $municipio->id,
            'prefeito_id' => $prefeitoId,
            'partido_id' => $partidoId,
            'ano_eleicao' => 2024,
            'cd_eleicao' => 1234,
            'dt_eleicao' => '2024-10-06',
            'nr_turno' => 1,
            'nr_candidato' => 456,
            'mandato_inicio' => '2025-01-01',
            'mandato_fim' => '2028-12-31',
            'mandato_consecutivo' => 1,
            'reeleito' => false,
        ];

        $createResponse = $this->postJson('/api/v1/mandatos', $payload)
            ->assertCreated()
            ->assertJsonPath('data.ano_eleicao', 2024);

        $mandatoId = $createResponse->json('data.id');

        $this->getJson("/api/v1/mandatos/{$mandatoId}")
            ->assertOk()
            ->assertJsonPath('data.id', $mandatoId);

        $this->patchJson("/api/v1/mandatos/{$mandatoId}", [
            'mandato_consecutivo' => 2,
            'mandato_inicio' => '2025-01-01',
            'mandato_fim' => '2028-12-31',
        ])->assertOk()
            ->assertJsonPath('data.mandato_consecutivo', 2);

        $this->deleteJson("/api/v1/mandatos/{$mandatoId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('mandato_prefeito', ['id' => $mandatoId]);
    }

    public function test_indicadores_endpoints(): void
    {
        $orgao = Orgao::factory()->create();
        $municipio = Municipio::factory()->create();

        MunicipioDemografia::query()->create([
            'municipio_id' => $municipio->id,
            'ano_ref' => 2025,
            'populacao' => 120000,
            'eleitores' => 80000,
        ]);

        $convenio = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'municipio_id' => $municipio->id,
            'numero_convenio' => 'CV-001/2026',
        ]);

        ConvenioPlanoInterno::query()->create([
            'convenio_id' => $convenio->id,
            'plano_interno' => 'AB12CD34EF5',
        ]);

        Parcela::factory()->create([
            'convenio_id' => $convenio->id,
            'numero' => 1,
            'valor_previsto' => 1000,
            'valor_pago' => 200,
            'data_pagamento' => null,
            'situacao' => 'PREVISTA',
        ]);

        $this->getJson('/api/v1/convenios/indicadores/quantidade-com-parcelas-em-aberto')
            ->assertOk()
            ->assertJsonStructure(['quantidade']);

        $this->getJson('/api/v1/convenios/indicadores/valores-em-aberto')
            ->assertOk()
            ->assertJsonStructure(['valor_em_aberto_total', 'valor_previsto_total', 'valor_pago_total']);

        $this->getJson('/api/v1/convenios/indicadores/populacao-atendida')
            ->assertOk()
            ->assertJsonStructure(['populacao_atendida']);

        $this->getJson('/api/v1/convenios/indicadores/eleitores-atendidos')
            ->assertOk()
            ->assertJsonStructure(['eleitores_atendidos']);

        $this->getJson('/api/v1/municipios/indicadores/populacao-por-regiao')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
