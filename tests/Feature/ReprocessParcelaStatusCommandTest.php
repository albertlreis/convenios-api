<?php

namespace Tests\Feature;

use App\Models\Parcela;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReprocessParcelaStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reprocessa_status_de_parcela_em_dry_run_e_apply(): void
    {
        $parcela = Parcela::factory()->create([
            'situacao' => 'PREVISTA',
            'dados_origem' => [
                'import_id' => 321,
                'row_number' => 17,
                'raw_data' => [
                    'numero_convenio' => 'CV-TESTE/2026',
                    'situacao' => 'PAGO/OK',
                ],
            ],
        ]);

        $this->artisan('parcelas:reprocessar-status', [
            '--import-id' => [321],
        ])->assertSuccessful();

        $this->assertDatabaseHas('parcela', [
            'id' => $parcela->id,
            'situacao' => 'PREVISTA',
        ]);

        $this->artisan('parcelas:reprocessar-status', [
            '--import-id' => [321],
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('parcela', [
            'id' => $parcela->id,
            'situacao' => 'PAGA',
        ]);
    }
}
