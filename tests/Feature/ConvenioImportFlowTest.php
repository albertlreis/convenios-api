<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\ConvenioImportListaRow;
use App\Models\Municipio;
use App\Models\Orgao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ConvenioImportFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_salva_staging_mesmo_com_dados_ruins(): void
    {
        $file = $this->buildImportFile(
            listaRows: [
                [
                    'orgao' => 'SETESTE',
                    'municipio' => 'Municipio Bom',
                    'convenente' => 'Prefeitura',
                    'numero_convenio' => 'CV-001/2026',
                    'ano' => '2026',
                ],
                [
                    'orgao' => 'SETESTE',
                    'municipio' => 'Municipio Ruim',
                    'convenente' => 'Prefeitura',
                    'numero_convenio' => '',
                    'ano' => '2026',
                ],
            ],
            parcelasRows: [],
            piRows: []
        );

        $response = $this->postJson('/api/v1/imports/convenios/upload', [
            'arquivo' => $file,
        ])->assertCreated();

        $importId = $response->json('data.id');
        $this->assertNotNull($importId);

        $this->assertDatabaseHas('convenio_imports', [
            'id' => $importId,
            'total_lista_rows' => 2,
            'status' => 'parsed',
        ]);
        $this->assertDatabaseHas('convenio_import_lista_rows', [
            'import_id' => $importId,
            'row_number' => 3,
            'status' => 'parsed_with_issues',
        ]);
    }

    public function test_confirm_nao_bloqueia_e_cria_pendencias(): void
    {
        $orgao = Orgao::query()->create([
            'sigla' => 'SETESTE',
            'nome' => 'Secretaria de Teste',
        ]);

        Municipio::query()->create([
            'regiao_id' => null,
            'nome' => 'Municipio Bom',
            'uf' => 'PA',
            'codigo_ibge' => '1500001',
            'codigo_tse' => 1001,
        ]);

        $file = $this->buildImportFile(
            listaRows: [
                [
                    'orgao' => 'SETESTE',
                    'municipio' => 'Municipio Bom',
                    'convenente' => 'Municipio Bom',
                    'numero_convenio' => 'CV-100/2026',
                    'ano' => '2026',
                    'plano_interno' => 'PI000000001',
                    'objeto' => 'Teste',
                    'grupo_despesa' => 'CUSTEIO',
                    'data_inicio' => '2026-01-01',
                    'data_fim' => '2026-12-31',
                    'valor_total' => '1000,00',
                    'valor_orgao' => '800,00',
                    'valor_contrapartida' => '200,00',
                    'quantidade_parcelas' => '2',
                ],
                [
                    'orgao' => 'SETESTE',
                    'municipio' => 'Municipio Fantasma',
                    'convenente' => 'Municipio Fantasma',
                    'numero_convenio' => 'CV-200/2026',
                    'ano' => '2026',
                    'plano_interno' => 'PI000000002',
                ],
            ],
            parcelasRows: [
                [
                    'numero_convenio' => 'CV-100/2026',
                    'orgao' => 'SETESTE',
                    'numero_parcela' => '1',
                    'valor_previsto' => '500,00',
                    'situacao' => 'PREVISTA',
                    'data_pagamento' => '',
                    'valor_pago' => '',
                    'observacoes' => '',
                ],
                [
                    'numero_convenio' => 'CV-INEXISTENTE',
                    'orgao' => 'SETESTE',
                    'numero_parcela' => '1',
                    'valor_previsto' => '300,00',
                    'situacao' => 'PREVISTA',
                    'data_pagamento' => '',
                    'valor_pago' => '',
                    'observacoes' => '',
                ],
            ],
            piRows: [
                [
                    'numero_convenio' => 'CV-100/2026',
                    'orgao' => 'SETESTE',
                    'plano_interno' => 'PI000000003',
                ],
            ]
        );

        $upload = $this->postJson('/api/v1/imports/convenios/upload', ['arquivo' => $file])->assertCreated();
        $importId = $upload->json('data.id');

        $this->postJson('/api/v1/imports/convenios/confirm', ['import_id' => $importId])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('convenio', [
            'numero_convenio' => 'CV-100/2026',
            'orgao_id' => $orgao->id,
        ]);
        $this->assertDatabaseHas('convenio', [
            'numero_convenio' => 'CV-200/2026',
            'municipio_id' => null,
        ]);
        $this->assertDatabaseHas('convenio_import_pending_items', [
            'import_id' => $importId,
            'reason' => 'municipio_nao_encontrado',
            'reference_key' => 'CV-200/2026',
        ]);
        $this->assertDatabaseHas('convenio_import_pending_items', [
            'import_id' => $importId,
            'reason' => 'convenio_nao_encontrado',
            'reference_key' => 'CV-INEXISTENTE',
        ]);
        $this->assertDatabaseHas('convenio_plano_interno', [
            'plano_interno' => 'PI000000003',
        ]);
        $this->assertDatabaseHas('parcela', [
            'numero' => 1,
            'convenio_id' => Convenio::query()->where('numero_convenio', 'CV-100/2026')->where('orgao_id', $orgao->id)->value('id'),
        ]);
    }

    public function test_pi_da_aba_lista_e_ignorado_e_pi_vem_somente_da_aba_dedicada(): void
    {
        Orgao::query()->create([
            'sigla' => 'SETESTE',
            'nome' => 'Secretaria de Teste',
        ]);

        Municipio::query()->create([
            'regiao_id' => null,
            'nome' => 'Municipio Bom',
            'uf' => 'PA',
            'codigo_ibge' => '1500001',
            'codigo_tse' => 1001,
        ]);

        $file = $this->buildImportFile(
            listaRows: [
                [
                    'orgao' => 'SETESTE',
                    'municipio' => 'Municipio Bom',
                    'convenente' => 'Municipio Bom',
                    'numero_convenio' => 'CV-300/2026',
                    'ano' => '2026',
                    'plano_interno' => 'PI000000010',
                ],
            ],
            parcelasRows: [],
            piRows: [
                [
                    'numero_convenio' => 'CV-300/2026',
                    'orgao' => 'SETESTE',
                    'plano_interno' => ' pi000000999 ',
                ],
            ]
        );

        $upload = $this->postJson('/api/v1/imports/convenios/upload', ['arquivo' => $file])->assertCreated();
        $importId = $upload->json('data.id');
        $this->assertNotNull($importId);

        $listaRow = ConvenioImportListaRow::query()
            ->where('import_id', $importId)
            ->firstOrFail();

        $this->assertSame('PI000000010', $listaRow->raw_data['plano_interno'] ?? null);
        $this->assertNull($listaRow->normalized_data['plano_interno'] ?? null);
        $this->assertTrue((bool) data_get($listaRow->normalized_data, 'ignored_columns.plano_interno', false));

        $ignoredIssue = collect($listaRow->issues ?? [])->first(
            fn (mixed $issue): bool => is_array($issue)
                && ($issue['type'] ?? null) === 'ignored_column'
                && ($issue['field'] ?? null) === 'plano_interno'
        );

        $this->assertNotNull($ignoredIssue);
        $this->assertSame(
            'Coluna plano_interno da aba lista foi ignorada (PI vem da aba plano_interno).',
            $ignoredIssue['message'] ?? null
        );

        $this->postJson('/api/v1/imports/convenios/confirm', ['import_id' => $importId])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('convenio', ['numero_convenio' => 'CV-300/2026']);
        $this->assertDatabaseHas('convenio_plano_interno', [
            'plano_interno' => 'PI000000999',
        ]);
        $this->assertDatabaseMissing('convenio_plano_interno', [
            'plano_interno' => 'PI000000010',
        ]);
    }

    public function test_confirm_classifica_parcelas_pagas_e_em_aberto_a_partir_do_status_textual(): void
    {
        Orgao::query()->create([
            'sigla' => 'SETESTE',
            'nome' => 'Secretaria de Teste',
        ]);

        Municipio::query()->create([
            'regiao_id' => null,
            'nome' => 'Municipio Bom',
            'uf' => 'PA',
            'codigo_ibge' => '1500001',
            'codigo_tse' => 1001,
        ]);

        $file = $this->buildImportFile(
            listaRows: [
                [
                    'orgao' => 'SETESTE',
                    'municipio' => 'Municipio Bom',
                    'convenente' => 'Municipio Bom',
                    'numero_convenio' => 'CV-400/2026',
                    'ano' => '2026',
                ],
            ],
            parcelasRows: [
                [
                    'numero_convenio' => 'CV-400/2026',
                    'orgao' => 'SETESTE',
                    'numero_parcela' => '1',
                    'valor_previsto' => '500,00',
                    'situacao' => 'PAGO/OK',
                    'data_pagamento' => '',
                    'valor_pago' => '',
                    'observacoes' => '',
                ],
                [
                    'numero_convenio' => 'CV-400/2026',
                    'orgao' => 'SETESTE',
                    'numero_parcela' => '2',
                    'valor_previsto' => '500,00',
                    'situacao' => 'EM ABERTO',
                    'data_pagamento' => '',
                    'valor_pago' => '',
                    'observacoes' => '',
                ],
            ],
            piRows: []
        );

        $upload = $this->postJson('/api/v1/imports/convenios/upload', ['arquivo' => $file])->assertCreated();
        $importId = $upload->json('data.id');

        $this->postJson('/api/v1/imports/convenios/confirm', ['import_id' => $importId])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('parcela', [
            'numero' => 1,
            'situacao' => 'PAGA',
        ]);
        $this->assertDatabaseHas('parcela', [
            'numero' => 2,
            'situacao' => 'PREVISTA',
        ]);
    }

    public function test_import_resolve_municipio_sem_acento_e_parse_pt_br(): void
    {
        $orgao = Orgao::query()->create([
            'sigla' => 'SETESTE',
            'nome' => 'Secretaria de Teste',
        ]);

        $municipio = Municipio::query()
            ->where('nome', 'Belém')
            ->where('uf', 'PA')
            ->first();
        if (! $municipio) {
            $municipio = Municipio::query()->create([
                'regiao_id' => null,
                'nome' => 'Belém',
                'uf' => 'PA',
                'codigo_ibge' => '1501402',
                'codigo_tse' => 1002,
            ]);
        }

        $file = $this->buildImportFile(
            listaRows: [[
                'orgao' => 'SETESTE',
                'municipio' => 'Belem',
                'convenente' => 'Prefeitura de Belem',
                'numero_convenio' => 'CV-500/2026',
                'ano' => '2026',
                'valor_total' => 'R$ 1.234.567,89',
                'valor_orgao' => '1.000.000,00',
                'valor_contrapartida' => '234.567,89',
            ]],
            parcelasRows: [[
                'numero_convenio' => 'CV-500/2026',
                'orgao' => 'SETESTE',
                'numero_parcela' => '1',
                'valor_previsto' => '123.456,78',
                'situacao' => 'EM ABERTO',
                'valor_pago' => '0,00',
            ]],
            piRows: []
        );

        $upload = $this->postJson('/api/v1/imports/convenios/upload', ['arquivo' => $file])->assertCreated();
        $importId = $upload->json('data.id');

        $this->postJson('/api/v1/imports/convenios/confirm', ['import_id' => $importId])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('convenio', [
            'orgao_id' => $orgao->id,
            'municipio_id' => $municipio->id,
            'numero_convenio' => 'CV-500/2026',
            'valor_total_informado' => '1234567.89',
            'valor_orgao' => '1000000.00',
        ]);
        $this->assertDatabaseHas('parcela', [
            'valor_previsto' => '123456.78',
            'valor_pago' => '0.00',
        ]);
    }

    public function test_import_parcelas_sem_orgao_gera_pendencia(): void
    {
        Orgao::query()->create([
            'sigla' => 'SETESTE',
            'nome' => 'Secretaria de Teste',
        ]);
        Municipio::factory()->create(['nome' => 'Municipio Bom Import']);

        $file = $this->buildImportFile(
            listaRows: [[
                'orgao' => 'SETESTE',
                'municipio' => 'Municipio Bom Import',
                'convenente' => 'Prefeitura',
                'numero_convenio' => 'CV-700/2026',
                'ano' => '2026',
            ]],
            parcelasRows: [[
                'numero_convenio' => 'CV-700/2026',
                'numero_parcela' => '1',
                'valor_previsto' => '10,00',
                'situacao' => 'EM ABERTO',
            ]],
            piRows: []
        );

        $upload = $this->postJson('/api/v1/imports/convenios/upload', ['arquivo' => $file])->assertCreated();
        $importId = $upload->json('data.id');

        $this->postJson('/api/v1/imports/convenios/confirm', ['import_id' => $importId])
            ->assertOk();

        $this->assertDatabaseHas('convenio_import_pending_items', [
            'import_id' => $importId,
            'source_sheet' => 'parcelas',
            'reason' => 'orgao_nao_encontrado',
        ]);
    }

    private function buildImportFile(array $listaRows, array $parcelasRows, array $piRows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('lista');
        $spreadsheet->createSheet()->setTitle('parcelas');
        $spreadsheet->createSheet()->setTitle('plano_interno');

        $this->writeSheet(
            $spreadsheet->getSheetByName('lista'),
            ['orgao', 'municipio', 'convenente', 'numero_convenio', 'ano', 'plano_interno', 'objeto', 'grupo_despesa', 'data_inicio', 'data_fim', 'valor_total', 'valor_orgao', 'valor_contrapartida', 'quantidade_parcelas'],
            $listaRows
        );
        $this->writeSheet(
            $spreadsheet->getSheetByName('parcelas'),
            ['numero_convenio', 'orgao', 'numero_parcela', 'valor_previsto', 'situacao', 'data_pagamento', 'valor_pago', 'observacoes'],
            $parcelasRows
        );
        $this->writeSheet(
            $spreadsheet->getSheetByName('plano_interno'),
            ['numero_convenio', 'orgao', 'plano_interno'],
            $piRows
        );

        $path = storage_path('app/private/testing/convenio-import-'.uniqid('', true).'.xlsx');
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
