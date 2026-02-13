<?php

namespace Tests\Feature;

use App\Models\Municipio;
use App\Models\MunicipioDemografia;
use App\Models\RegiaoIntegracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemografiaUnifiedEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_demografia_index_filtra_por_regiao_e_ano(): void
    {
        $regiaoA = RegiaoIntegracao::query()->create(['descricao' => 'Regiao A']);
        $regiaoB = RegiaoIntegracao::query()->create(['descricao' => 'Regiao B']);

        $municipioA = Municipio::factory()->create(['nome' => 'Municipio A', 'regiao_id' => $regiaoA->id]);
        $municipioB = Municipio::factory()->create(['nome' => 'Municipio B', 'regiao_id' => $regiaoB->id]);

        MunicipioDemografia::query()->create([
            'municipio_id' => $municipioA->id,
            'ano_ref' => 2024,
            'populacao' => 120000,
            'eleitores' => 80000,
        ]);
        MunicipioDemografia::query()->create([
            'municipio_id' => $municipioB->id,
            'ano_ref' => 2024,
            'populacao' => 90000,
            'eleitores' => 60000,
        ]);

        $this->getJson('/api/v1/demografia?regiao_integracao_id='.$regiaoA->id.'&ano=2024')
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.municipio.id', $municipioA->id)
            ->assertJsonPath('data.meta.kpis.total_municipios', 1);
    }

    public function test_demografia_show_municipio_retorna_series(): void
    {
        $municipio = Municipio::factory()->create(['nome' => 'Municipio Serie']);

        MunicipioDemografia::query()->create([
            'municipio_id' => $municipio->id,
            'ano_ref' => 2023,
            'populacao' => 100000,
            'eleitores' => 70000,
        ]);
        MunicipioDemografia::query()->create([
            'municipio_id' => $municipio->id,
            'ano_ref' => 2024,
            'populacao' => 101000,
            'eleitores' => 71000,
        ]);

        $this->getJson('/api/v1/demografia/municipios/'.$municipio->id)
            ->assertOk()
            ->assertJsonPath('sucesso', true)
            ->assertJsonPath('data.municipio.id', $municipio->id)
            ->assertJsonCount(2, 'data.series')
            ->assertJsonPath('data.indicadores.total_registros', 2);
    }
}
