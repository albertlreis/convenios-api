<?php

namespace Tests\Feature;

use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefeitos_crud(): void
    {
        $create = $this->postJson('/api/v1/prefeitos', [
            'nome_completo' => 'Prefeito CRUD',
            'nome_urna' => 'CRUD',
            'dt_nascimento' => '1980-01-01',
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->getJson("/api/v1/prefeitos/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->putJson("/api/v1/prefeitos/{$id}", [
            'nome_completo' => 'Prefeito CRUD Atualizado',
            'nome_urna' => 'CRUD2',
            'dt_nascimento' => '1981-01-01',
        ])->assertOk()
            ->assertJsonPath('data.nome_urna', 'CRUD2');

        $this->deleteJson("/api/v1/prefeitos/{$id}")->assertNoContent();
    }

    public function test_partidos_crud(): void
    {
        $create = $this->postJson('/api/v1/partidos', [
            'sigla' => 'PCR',
            'nome' => 'Partido CRUD',
            'numero' => 975,
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->getJson("/api/v1/partidos/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->putJson("/api/v1/partidos/{$id}", [
            'sigla' => 'PCU',
            'nome' => 'Partido CRUD Atualizado',
            'numero' => 976,
        ])->assertOk()
            ->assertJsonPath('data.sigla', 'PCU');

        $this->deleteJson("/api/v1/partidos/{$id}")->assertNoContent();
    }

    public function test_demografia_crud(): void
    {
        $municipio = Municipio::factory()->create();

        $create = $this->postJson('/api/v1/municipio-demografias', [
            'municipio_id' => $municipio->id,
            'ano_ref' => 2025,
            'populacao' => 123456,
            'eleitores' => 65432,
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->getJson("/api/v1/municipio-demografias/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->putJson("/api/v1/municipio-demografias/{$id}", [
            'municipio_id' => $municipio->id,
            'ano_ref' => 2025,
            'populacao' => 200000,
            'eleitores' => 100000,
        ])->assertOk()
            ->assertJsonPath('data.populacao', 200000);

        $this->deleteJson("/api/v1/municipio-demografias/{$id}")->assertNoContent();
    }

    public function test_orgaos_crud(): void
    {
        $create = $this->postJson('/api/v1/orgaos', [
            'sigla' => 'OCRD',
            'nome' => 'Orgao CRUD',
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->getJson("/api/v1/orgaos/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $this->putJson("/api/v1/orgaos/{$id}", [
            'sigla' => 'OCR2',
            'nome' => 'Orgao CRUD Atualizado',
        ])->assertOk()
            ->assertJsonPath('data.sigla', 'OCR2');

        $this->deleteJson("/api/v1/orgaos/{$id}")->assertNoContent();
    }
}
