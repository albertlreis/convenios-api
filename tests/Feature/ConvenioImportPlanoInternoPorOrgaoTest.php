<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\ConvenioImport;
use App\Models\ConvenioPlanoInterno;
use App\Models\Orgao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ConvenioImportPlanoInternoPorOrgaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_pi_por_orgao_aceita_varios_pis_no_mesmo_convenio_sem_duplicar(): void
    {
        $orgao = Orgao::query()->create(['sigla' => 'SEGOV', 'nome' => 'Secretaria de Governo']);
        $convenio = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-PI-001/2026',
        ]);
        ConvenioPlanoInterno::query()->where('convenio_id', $convenio->id)->delete();

        $file = $this->buildPiImportFile([
            ['orgao' => 'SEGOV', 'numero_convenio' => 'CV-PI-001/2026', 'plano_interno' => 'PI-AAA-001'],
            ['orgao' => 'SEGOV', 'numero_convenio' => 'CV-PI-001/2026', 'plano_interno' => 'PI-BBB-002'],
            ['orgao' => 'SEGOV', 'numero_convenio' => 'CV-PI-001/2026', 'plano_interno' => 'PI-BBB-002'],
        ]);

        $importId = $this->uploadPiAndConfirm($file, true, false);

        $this->assertSame(2, ConvenioPlanoInterno::query()->where('convenio_id', $convenio->id)->count());
        $this->assertDatabaseHas('convenio_plano_interno', ['convenio_id' => $convenio->id, 'plano_interno' => 'PI-AAA-001']);
        $this->assertDatabaseHas('convenio_plano_interno', ['convenio_id' => $convenio->id, 'plano_interno' => 'PI-BBB-002']);
        $this->assertDatabaseHas('convenio_imports', ['id' => $importId, 'status' => 'confirmed']);
    }

    public function test_reimportar_mesma_planilha_e_idempotente(): void
    {
        $orgao = Orgao::query()->create(['sigla' => 'SEHAB', 'nome' => 'Secretaria de Habitacao']);
        $convenio = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-PI-002/2026',
        ]);
        ConvenioPlanoInterno::query()->where('convenio_id', $convenio->id)->delete();

        $rows = [
            ['orgao' => 'SEHAB', 'numero_convenio' => 'CV-PI-002/2026', 'plano_interno' => 'PI-CCC-003'],
            ['orgao' => 'SEHAB', 'numero_convenio' => 'CV-PI-002/2026', 'plano_interno' => 'PI-DDD-004'],
        ];

        $this->uploadPiAndConfirm($this->buildPiImportFile($rows), true, false);
        $this->uploadPiAndConfirm($this->buildPiImportFile($rows), true, false);

        $this->assertSame(2, ConvenioPlanoInterno::query()->where('convenio_id', $convenio->id)->count());
    }

    public function test_confirm_pi_sync_remove_pi_antigo_apenas_dos_convenios_tocados(): void
    {
        $orgao = Orgao::query()->create(['sigla' => 'SEFIN', 'nome' => 'Secretaria da Fazenda']);
        $convenioTocado = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-PI-003/2026',
        ]);
        $convenioNaoTocado = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-PI-004/2026',
        ]);

        ConvenioPlanoInterno::query()->insert([
            ['convenio_id' => $convenioTocado->id, 'plano_interno' => 'PI-OLD-001', 'created_at' => now(), 'updated_at' => now()],
            ['convenio_id' => $convenioNaoTocado->id, 'plano_interno' => 'PI-STAY-001', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $file = $this->buildPiImportFile([
            ['orgao' => 'SEFIN', 'numero_convenio' => 'CV-PI-003/2026', 'plano_interno' => 'PI-NEW-001'],
        ]);

        $this->uploadPiAndConfirm($file, true, true);

        $this->assertDatabaseHas('convenio_plano_interno', ['convenio_id' => $convenioTocado->id, 'plano_interno' => 'PI-NEW-001']);
        $this->assertDatabaseMissing('convenio_plano_interno', ['convenio_id' => $convenioTocado->id, 'plano_interno' => 'PI-OLD-001']);
        $this->assertDatabaseHas('convenio_plano_interno', ['convenio_id' => $convenioNaoTocado->id, 'plano_interno' => 'PI-STAY-001']);
    }

    public function test_confirm_pi_registra_convenio_nao_encontrado_em_pendencia_e_resumo(): void
    {
        Orgao::query()->create(['sigla' => 'SEDUC', 'nome' => 'Secretaria de Educacao']);

        $file = $this->buildPiImportFile([
            ['orgao' => 'SEDUC', 'numero_convenio' => 'CV-PI-NAO-EXISTE', 'plano_interno' => 'PI-XYZ-999'],
        ]);

        $importId = $this->uploadPiAndConfirm($file, true, false);
        $import = ConvenioImport::query()->findOrFail($importId);

        $this->assertDatabaseHas('convenio_import_pending_items', [
            'import_id' => $importId,
            'source_sheet' => 'plano_interno',
            'reason' => 'convenio_nao_encontrado',
            'reference_key' => 'CV-PI-NAO-EXISTE',
        ]);
        $this->assertSame(1, data_get($import->resumo, 'confirmacao_pi_por_orgao.convenios_nao_encontrados_total'));
        $this->assertContains('CV-PI-NAO-EXISTE', data_get($import->resumo, 'confirmacao_pi_por_orgao.convenios_nao_encontrados_lista', []));
    }

    public function test_confirm_pi_marca_convenio_excluido_e_nao_cria_pi(): void
    {
        $orgao = Orgao::query()->create(['sigla' => 'SEPLAD', 'nome' => 'Secretaria de Planejamento']);
        $convenio = Convenio::factory()->create([
            'orgao_id' => $orgao->id,
            'numero_convenio' => 'CV-PI-005/2026',
        ]);
        $convenio->delete();

        $file = $this->buildPiImportFile([
            ['orgao' => 'SEPLAD', 'numero_convenio' => 'CV-PI-005/2026', 'plano_interno' => 'PI-DEL-001'],
        ]);

        $importId = $this->uploadPiAndConfirm($file, true, false);

        $this->assertDatabaseHas('convenio_import_pending_items', [
            'import_id' => $importId,
            'reason' => 'convenio_excluido',
            'reference_key' => 'CV-PI-005/2026',
        ]);
        $this->assertDatabaseMissing('convenio_plano_interno', [
            'convenio_id' => $convenio->id,
            'plano_interno' => 'PI-DEL-001',
        ]);
    }

    public function test_confirm_pi_com_multiplos_orgaos_na_planilha_nao_executa_sync(): void
    {
        $orgaoA = Orgao::query()->create(['sigla' => 'SEMAS', 'nome' => 'Secretaria de Meio Ambiente']);
        $orgaoB = Orgao::query()->create(['sigla' => 'SECOM', 'nome' => 'Secretaria de Comunicacao']);
        $convenioA = Convenio::factory()->create(['orgao_id' => $orgaoA->id, 'numero_convenio' => 'CV-PI-006/2026']);
        $convenioB = Convenio::factory()->create(['orgao_id' => $orgaoB->id, 'numero_convenio' => 'CV-PI-007/2026']);

        ConvenioPlanoInterno::query()->insert([
            ['convenio_id' => $convenioA->id, 'plano_interno' => 'PI-OLD-A', 'created_at' => now(), 'updated_at' => now()],
            ['convenio_id' => $convenioB->id, 'plano_interno' => 'PI-OLD-B', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $file = $this->buildPiImportFile([
            ['orgao' => 'SEMAS', 'numero_convenio' => 'CV-PI-006/2026', 'plano_interno' => 'PI-NEW-A'],
            ['orgao' => 'SECOM', 'numero_convenio' => 'CV-PI-007/2026', 'plano_interno' => 'PI-NEW-B'],
        ]);

        $importId = $this->uploadPiAndConfirm($file, true, true);
        $import = ConvenioImport::query()->findOrFail($importId);

        $this->assertDatabaseHas('convenio_import_pending_items', [
            'import_id' => $importId,
            'reason' => 'orgao_multiplo_na_planilha',
        ]);
        $this->assertDatabaseHas('convenio_plano_interno', ['convenio_id' => $convenioA->id, 'plano_interno' => 'PI-OLD-A']);
        $this->assertDatabaseHas('convenio_plano_interno', ['convenio_id' => $convenioB->id, 'plano_interno' => 'PI-OLD-B']);
        $this->assertDatabaseHas('convenio_plano_interno', ['convenio_id' => $convenioA->id, 'plano_interno' => 'PI-NEW-A']);
        $this->assertDatabaseHas('convenio_plano_interno', ['convenio_id' => $convenioB->id, 'plano_interno' => 'PI-NEW-B']);
        $this->assertFalse((bool) data_get($import->resumo, 'confirmacao_pi_por_orgao.sync_executado'));
    }

    private function uploadPiAndConfirm(UploadedFile $file, bool $assertUploadCreated = true, bool $sync = true): int
    {
        $uploadResponse = $this->postJson('/api/v1/imports/convenios/upload-pi', [
            'arquivo' => $file,
        ]);

        if ($assertUploadCreated) {
            $uploadResponse->assertCreated();
        }

        $importId = (int) $uploadResponse->json('data.id');

        $this->postJson(sprintf('/api/v1/imports/convenios/%d/confirm-pi?sync=%d', $importId, $sync ? 1 : 0))
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        return $importId;
    }

    /**
     * @param  array<int, array{orgao: string, numero_convenio: string, plano_interno: string}>  $piRows
     */
    private function buildPiImportFile(array $piRows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('plano_interno');
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['orgao', 'numero_convenio', 'plano_interno'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $line = 2;
        foreach ($piRows as $row) {
            foreach ($headers as $index => $header) {
                $sheet->setCellValueByColumnAndRow($index + 1, $line, (string) ($row[$header] ?? ''));
            }
            $line++;
        }

        $path = storage_path('app/private/testing/convenio-import-pi-'.uniqid('', true).'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return new UploadedFile(
            $path,
            basename($path),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
