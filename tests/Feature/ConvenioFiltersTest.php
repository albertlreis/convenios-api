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
            'numero_convenio' => 'CV-111/2026',
            'municipio_id' => $municipioA->id,
        ]);

        $convenioFechado = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-222/2026',
            'municipio_id' => $municipioB->id,
        ]);

        ConvenioPlanoInterno::query()->create([
            'convenio_id' => $convenioAberto->id,
            'plano_interno' => 'AA11BB22CC3',
        ]);
        ConvenioPlanoInterno::query()->create([
            'convenio_id' => $convenioFechado->id,
            'plano_interno' => 'DD44EE55FF6',
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
            ->assertJsonPath('sucesso', true)
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $convenioAberto->id);

        $this->getJson('/api/v1/convenios?municipio_id='.$municipioB->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $convenioFechado->id);
    }

    public function test_filtra_por_orgao_e_pi(): void
    {
        $orgaoA = Orgao::factory()->create(['sigla' => 'SEA']);
        $orgaoB = Orgao::factory()->create(['sigla' => 'SEB']);
        $municipio = Municipio::factory()->create();

        $convenioA = Convenio::factory()->create([
            'orgao_id' => $orgaoA->id,
            'numero_convenio' => 'CV-333/2026',
            'municipio_id' => $municipio->id,
        ]);
        $convenioB = Convenio::factory()->create([
            'orgao_id' => $orgaoB->id,
            'numero_convenio' => 'CV-444/2026',
            'municipio_id' => $municipio->id,
        ]);

        ConvenioPlanoInterno::query()->create([
            'convenio_id' => $convenioA->id,
            'plano_interno' => 'PI111111111',
        ]);
        ConvenioPlanoInterno::query()->create([
            'convenio_id' => $convenioB->id,
            'plano_interno' => 'PI222222222',
        ]);

        $this->getJson('/api/v1/convenios?orgao_id='.$orgaoA->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $convenioA->id);

        $this->getJson('/api/v1/convenios?pi[]=PI222222222')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $convenioB->id);
    }

    public function test_filtra_por_intervalo_data_pagamento_e_ordenacao(): void
    {
        $orgao = Orgao::factory()->create();
        $municipio = Municipio::factory()->create();

        $convenioAntigo = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-555/2026',
            'municipio_id' => $municipio->id,
            'updated_at' => '2026-01-01 10:00:00',
        ]);
        $convenioNovo = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-666/2026',
            'municipio_id' => $municipio->id,
            'updated_at' => '2026-01-10 10:00:00',
        ]);

        Parcela::factory()->create([
            'convenio_id' => $convenioAntigo->id,
            'numero' => 1,
            'data_pagamento' => '2026-01-05',
            'situacao' => 'PAGA',
            'valor_previsto' => 100,
            'valor_pago' => 100,
        ]);
        Parcela::factory()->create([
            'convenio_id' => $convenioNovo->id,
            'numero' => 1,
            'data_pagamento' => '2026-02-05',
            'situacao' => 'PAGA',
            'valor_previsto' => 100,
            'valor_pago' => 100,
        ]);

        $this->getJson('/api/v1/convenios?data_pagamento_de=2026-01-01&data_pagamento_ate=2026-01-31')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $convenioAntigo->id);

        $this->getJson('/api/v1/convenios?orderBy=updated_at&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.results.0.id', $convenioNovo->id);
    }

    public function test_filtra_por_nome_textual_e_por_partido_via_mandato_vigente(): void
    {
        $orgao = Orgao::factory()->create();
        $municipioPartido = Municipio::factory()->create(['nome' => 'Municipio Alpha']);
        $municipioOutro = Municipio::factory()->create(['nome' => 'Municipio Beta']);

        $partidoA = DB::table('partido')->insertGetId([
            'sigla' => 'PXA',
            'nome' => 'Partido XA',
            'numero' => 991,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $partidoB = DB::table('partido')->insertGetId([
            'sigla' => 'PXB',
            'nome' => 'Partido XB',
            'numero' => 992,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $prefeitoA = DB::table('prefeito')->insertGetId([
            'nome_completo' => 'Prefeito XA',
            'nome_urna' => 'XA',
            'dt_nascimento' => '1980-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $prefeitoB = DB::table('prefeito')->insertGetId([
            'nome_completo' => 'Prefeito XB',
            'nome_urna' => 'XB',
            'dt_nascimento' => '1981-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mandato_prefeito')->insert([
            'municipio_id' => $municipioPartido->id,
            'prefeito_id' => $prefeitoA,
            'partido_id' => $partidoA,
            'ano_eleicao' => 2024,
            'cd_eleicao' => 1,
            'dt_eleicao' => '2024-10-06',
            'nr_turno' => 1,
            'nr_candidato' => 1,
            'mandato_inicio' => now()->subMonth()->toDateString(),
            'mandato_fim' => now()->addYear()->toDateString(),
            'mandato_consecutivo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('mandato_prefeito')->insert([
            'municipio_id' => $municipioOutro->id,
            'prefeito_id' => $prefeitoB,
            'partido_id' => $partidoB,
            'ano_eleicao' => 2024,
            'cd_eleicao' => 1,
            'dt_eleicao' => '2024-10-06',
            'nr_turno' => 1,
            'nr_candidato' => 2,
            'mandato_inicio' => now()->subMonth()->toDateString(),
            'mandato_fim' => now()->addYear()->toDateString(),
            'mandato_consecutivo' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $convenioA = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-ALPHA/2026',
            'municipio_id' => $municipioPartido->id,
            'objeto' => 'Obra escola municipal',
        ]);
        Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-BETA/2026',
            'municipio_id' => $municipioOutro->id,
            'objeto' => 'Reforma de via',
        ]);

        $this->getJson('/api/v1/convenios?q=escola')
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $convenioA->id);

        $this->getJson('/api/v1/convenios?partido_id='.$partidoA)
            ->assertOk()
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.id', $convenioA->id);
    }

    public function test_paginacao_contrato_da_lista_de_convenios(): void
    {
        $orgao = Orgao::factory()->create();
        $municipio = Municipio::factory()->create();

        Convenio::factory()->count(3)->create([
            'orgao_id' => $orgao->id,
            'municipio_id' => $municipio->id,
        ]);

        $this->getJson('/api/v1/convenios?page=2&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.pagination.page', 2)
            ->assertJsonPath('data.pagination.perPage', 1)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.lastPage', 3);
    }
}
