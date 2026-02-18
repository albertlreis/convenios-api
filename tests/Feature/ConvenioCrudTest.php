<?php

namespace Tests\Feature;

use App\Models\Municipio;
use App\Models\Orgao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvenioCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_convenio_crud_completo(): void
    {
        $orgao = Orgao::factory()->create();
        $municipio = Municipio::factory()->create();

        $payload = [
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-123/2026',
            'municipio_id' => $municipio->id,
            'plano_interno' => 'AB12CD34EF5',
            'objeto' => 'Pavimentacao urbana',
            'grupo_despesa' => 'CUSTEIO',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2026-12-31',
            'valor_orgao' => 10000,
            'valor_contrapartida' => 1000,
            'valor_aditivo' => 0,
            'valor_total_informado' => 11000,
            'valor_total_calculado' => 11000,
        ];

        $createResponse = $this->postJson('/api/v1/convenios', $payload)
            ->assertCreated()
            ->assertJsonPath('data.numero_convenio', 'CV-123/2026')
            ->assertJsonMissingPath('data.codigo')
            ->assertJsonMissingPath('data.metadata')
            ->assertJsonMissingPath('data.municipio_beneficiario_id');

        $convenioId = $createResponse->json('data.id');

        $this->assertDatabaseHas('convenio', [
            'id' => $convenioId,
            'numero_convenio' => 'CV-123/2026',
        ]);

        $this->getJson("/api/v1/convenios/{$convenioId}")
            ->assertOk()
            ->assertJsonPath('data.convenio.id', $convenioId);

        $this->patchJson("/api/v1/convenios/{$convenioId}", [
            'grupo_despesa' => 'CAPITAL',
        ])->assertOk()
            ->assertJsonPath('data.grupo_despesa', 'CAPITAL');

        $this->deleteJson("/api/v1/convenios/{$convenioId}")
            ->assertNoContent();

        $this->assertSoftDeleted('convenio', [
            'id' => $convenioId,
        ]);
    }
}
