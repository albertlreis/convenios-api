<?php

namespace Tests\Feature;

use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EleitoralUnifiedEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_eleitoral_index_filtra_por_partido_e_ano(): void
    {
        $municipio = Municipio::factory()->create(['nome' => 'Municipio Eleitoral']);

        $prefeitoA = DB::table('prefeito')->insertGetId([
            'nome_completo' => 'Prefeito A',
            'nome_urna' => 'A',
            'dt_nascimento' => '1980-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $prefeitoB = DB::table('prefeito')->insertGetId([
            'nome_completo' => 'Prefeito B',
            'nome_urna' => 'B',
            'dt_nascimento' => '1981-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $partidoA = DB::table('partido')->insertGetId([
            'sigla' => 'PAA',
            'nome' => 'Partido A',
            'numero' => 911,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $partidoB = DB::table('partido')->insertGetId([
            'sigla' => 'PBB',
            'nome' => 'Partido B',
            'numero' => 922,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mandato_prefeito')->insert([
            [
                'municipio_id' => $municipio->id,
                'prefeito_id' => $prefeitoA,
                'partido_id' => $partidoA,
                'ano_eleicao' => 2020,
                'cd_eleicao' => 1,
                'dt_eleicao' => '2020-11-15',
                'nr_turno' => 1,
                'nr_candidato' => 111,
                'mandato_inicio' => '2021-01-01',
                'mandato_fim' => '2024-12-31',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'municipio_id' => $municipio->id,
                'prefeito_id' => $prefeitoB,
                'partido_id' => $partidoB,
                'ano_eleicao' => 2024,
                'cd_eleicao' => 2,
                'dt_eleicao' => '2024-10-06',
                'nr_turno' => 1,
                'nr_candidato' => 222,
                'mandato_inicio' => '2025-01-01',
                'mandato_fim' => '2028-12-31',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->getJson('/api/v1/eleitoral?ano_eleicao=2024&partido_id='.$partidoB)
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.partido.id', $partidoB)
            ->assertJsonPath('data.meta.kpis.total_mandatos', 1);
    }

    public function test_eleitoral_show_municipio_retorna_mandatos_e_anos_disponiveis(): void
    {
        $municipio = Municipio::factory()->create(['nome' => 'Municipio Detalhe']);

        $prefeito = DB::table('prefeito')->insertGetId([
            'nome_completo' => 'Prefeito C',
            'nome_urna' => 'C',
            'dt_nascimento' => '1982-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $partido = DB::table('partido')->insertGetId([
            'sigla' => 'PCC',
            'nome' => 'Partido C',
            'numero' => 933,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mandato_prefeito')->insert([
            'municipio_id' => $municipio->id,
            'prefeito_id' => $prefeito,
            'partido_id' => $partido,
            'ano_eleicao' => 2024,
            'cd_eleicao' => 2,
            'dt_eleicao' => '2024-10-06',
            'nr_turno' => 1,
            'nr_candidato' => 333,
            'mandato_inicio' => '2025-01-01',
            'mandato_fim' => '2028-12-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/eleitoral/municipios/'.$municipio->id)
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('data.municipio.id', $municipio->id)
            ->assertJsonPath('data.kpis.total_mandatos', 1)
            ->assertJsonPath('data.anos_disponiveis.0', 2024);
    }
}
