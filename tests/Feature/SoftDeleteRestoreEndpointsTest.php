<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Orgao;
use App\Models\Parcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeleteRestoreEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_convenios_support_only_trashed_and_restore(): void
    {
        $convenio = Convenio::factory()->create(['numero_convenio' => 'CV-999/2026']);
        $convenio->delete();

        $this->assertSoftDeleted('convenio', ['id' => $convenio->id]);

        $this->getJson('/api/v1/convenios?only_trashed=1')
            ->assertOk()
            ->assertJsonPath('data.results.0.id', $convenio->id);

        $this->postJson("/api/v1/convenios/{$convenio->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $convenio->id)
            ->assertJsonPath('data.deleted_at', null);

        $this->assertDatabaseHas('convenio', [
            'id' => $convenio->id,
            'deleted_at' => null,
        ]);
    }

    public function test_orgaos_support_only_trashed_and_restore(): void
    {
        $orgao = Orgao::factory()->create();
        $orgao->delete();

        $this->assertSoftDeleted('orgao', ['id' => $orgao->id]);

        $this->getJson('/api/v1/orgaos?only_trashed=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $orgao->id);

        $this->postJson("/api/v1/orgaos/{$orgao->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $orgao->id)
            ->assertJsonPath('data.deleted_at', null);

        $this->assertDatabaseHas('orgao', [
            'id' => $orgao->id,
            'deleted_at' => null,
        ]);
    }

    public function test_parcelas_support_only_trashed_and_restore(): void
    {
        $convenio = Convenio::factory()->create();
        $parcela = Parcela::factory()->create([
            'convenio_id' => $convenio->id,
            'numero' => 1,
        ]);
        $parcela->delete();

        $this->assertSoftDeleted('parcela', ['id' => $parcela->id]);

        $this->getJson('/api/v1/parcelas?only_trashed=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $parcela->id);

        $this->postJson("/api/v1/parcelas/{$parcela->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $parcela->id)
            ->assertJsonPath('data.deleted_at', null);

        $this->assertDatabaseHas('parcela', [
            'id' => $parcela->id,
            'deleted_at' => null,
        ]);
    }
}
