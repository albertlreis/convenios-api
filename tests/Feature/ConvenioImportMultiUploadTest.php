<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ConvenioImportMultiUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_multi_com_dois_arquivos_validos_cria_duas_importacoes(): void
    {
        $fileA = $this->buildImportFile('A', false);
        $fileB = $this->buildImportFile('B', false);

        $response = $this->postJson('/api/v1/imports/convenios/upload', [
            'files' => [$fileA, $fileB],
        ])->assertCreated();

        $items = $response->json('data');
        $this->assertCount(2, $items);
        $this->assertSame('OK', $items[0]['status'] ?? null);
        $this->assertSame('OK', $items[1]['status'] ?? null);
        $this->assertNotNull($items[0]['import_id'] ?? null);
        $this->assertNotNull($items[1]['import_id'] ?? null);

        $this->assertDatabaseCount('convenio_imports', 2);
    }

    public function test_upload_multi_best_effort_com_um_arquivo_invalido(): void
    {
        $validFile = $this->buildImportFile('VALIDO', false);
        $invalidFile = UploadedFile::fake()->create('invalido.txt', 2, 'text/plain');

        $response = $this->postJson('/api/v1/imports/convenios/upload', [
            'files' => [$validFile, $invalidFile],
        ])->assertStatus(207);

        $items = $response->json('data');
        $this->assertCount(2, $items);
        $this->assertSame('OK', $items[0]['status'] ?? null);
        $this->assertSame('ERRO', $items[1]['status'] ?? null);
        $this->assertNotNull($items[0]['import_id'] ?? null);
        $this->assertNull($items[1]['import_id'] ?? null);
        $this->assertNotEmpty($items[1]['errors'] ?? []);

        $this->assertDatabaseCount('convenio_imports', 1);
    }

    public function test_upload_reconhece_abas_com_prefixo_tb(): void
    {
        $file = $this->buildImportFile('TB', true);

        $response = $this->postJson('/api/v1/imports/convenios/upload', [
            'arquivo' => $file,
        ])->assertCreated();

        $this->assertSame(1, (int) $response->json('data.total_lista_rows'));
        $this->assertSame(1, (int) $response->json('data.total_parcelas_rows'));
        $this->assertSame(1, (int) $response->json('data.total_pi_rows'));
        $this->assertTrue((bool) $response->json('data.resumo.sheets.lista.encontrada'));
        $this->assertTrue((bool) $response->json('data.resumo.sheets.parcelas.encontrada'));
        $this->assertTrue((bool) $response->json('data.resumo.sheets.plano_interno.encontrada'));
    }

    private function buildImportFile(string $suffix, bool $useTbAliases): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $listaSheet = $spreadsheet->getActiveSheet();
        $listaSheet->setTitle($useTbAliases ? 'tb_lista' : 'lista');
        $parcelasSheet = $spreadsheet->createSheet();
        $parcelasSheet->setTitle($useTbAliases ? 'tb_parcelas' : 'parcelas');
        $piSheet = $spreadsheet->createSheet();
        $piSheet->setTitle($useTbAliases ? 'tb_plano_interno' : 'plano_interno');

        $this->writeSheet(
            $listaSheet,
            ['orgao', 'municipio', 'convenente', 'numero_convenio', 'ano', 'plano_interno', 'objeto', 'grupo_despesa', 'data_inicio', 'data_fim', 'valor_total', 'valor_orgao', 'valor_contrapartida'],
            [[
                'orgao' => 'SETESTE',
                'municipio' => 'Municipio '.$suffix,
                'convenente' => 'Prefeitura '.$suffix,
                'numero_convenio' => 'CV-'.$suffix.'/2026',
                'ano' => '2026',
                'plano_interno' => 'PI00000'.$suffix,
                'objeto' => 'Objeto '.$suffix,
                'grupo_despesa' => 'CUSTEIO',
                'data_inicio' => '2026-01-01',
                'data_fim' => '2026-12-31',
                'valor_total' => '1000,00',
                'valor_orgao' => '800,00',
                'valor_contrapartida' => '200,00',
            ]]
        );

        $this->writeSheet(
            $parcelasSheet,
            ['numero_convenio', 'numero_parcela', 'valor_previsto', 'situacao', 'data_pagamento', 'valor_pago', 'observacoes'],
            [[
                'numero_convenio' => 'CV-'.$suffix.'/2026',
                'numero_parcela' => '1',
                'valor_previsto' => '800,00',
                'situacao' => 'EM ABERTO',
                'data_pagamento' => '',
                'valor_pago' => '',
                'observacoes' => '',
            ]]
        );

        $this->writeSheet(
            $piSheet,
            ['numero_convenio', 'plano_interno'],
            [[
                'numero_convenio' => 'CV-'.$suffix.'/2026',
                'plano_interno' => 'PI00000'.$suffix,
            ]]
        );

        $path = storage_path('app/private/testing/convenio-import-multi-'.uniqid('', true).'.xlsx');
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

    private function writeSheet(mixed $sheet, array $headers, array $rows): void
    {
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $line = 2;
        foreach ($rows as $row) {
            foreach ($headers as $index => $header) {
                $sheet->setCellValueByColumnAndRow($index + 1, $line, (string) ($row[$header] ?? ''));
            }
            $line++;
        }
    }
}
