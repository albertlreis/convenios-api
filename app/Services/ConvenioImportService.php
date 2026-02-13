<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\ConvenioImport;
use App\Models\ConvenioImportListaRow;
use App\Models\ConvenioImportParcelaRow;
use App\Models\ConvenioImportPendingItem;
use App\Models\ConvenioImportPiRow;
use App\Models\ConvenioPlanoInterno;
use App\Models\Municipio;
use App\Models\Orgao;
use App\Models\Parcela;
use App\Support\NormalizeParcelaStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ConvenioImportService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function expectedColumns(): array
    {
        return [
            'lista' => [
                'orgao' => ['orgao'],
                'municipio' => ['municipio'],
                'convenente' => ['convenente'],
                'numero_convenio' => ['numero_convenio'],
                'ano' => ['ano'],
                'objeto' => ['objeto'],
                'grupo_despesa' => ['grupo_despesa'],
                'data_inicio' => ['data_inicio'],
                'data_fim' => ['data_fim'],
                'valor_total' => ['valor_total'],
                'valor_orgao' => ['valor_orgao'],
                'valor_contrapartida' => ['valor_contrapartida'],
                'quantidade_parcelas' => ['quantidade_parcelas'],
            ],
            'parcelas' => [
                'numero_convenio' => ['numero_convenio'],
                'numero_parcela' => ['numero_parcela'],
                'valor_previsto' => ['valor_previsto'],
                'situacao' => ['situacao'],
                'data_pagamento' => ['data_pagamento'],
                'valor_pago' => ['valor_pago'],
                'observacoes' => ['observacoes'],
            ],
            'plano_interno' => [
                'numero_convenio' => ['numero_convenio'],
                'plano_interno' => ['plano_interno'],
            ],
        ];
    }

    public function uploadAndParse(UploadedFile $file): ConvenioImport
    {
        $disk = Storage::disk('private');

        try {
            $disk->makeDirectory('imports/convenios');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension() ?: 'xlsx';
            $safeBaseName = Str::of($originalName)->ascii()->replaceMatches('/[^A-Za-z0-9_-]+/', '_')->trim('_')->limit(70, '');
            $fileName = sprintf('%s_%s.%s', now()->format('Ymd_His'), Str::random(12), strtolower((string) $extension));
            if ((string) $safeBaseName !== '') {
                $fileName = sprintf('%s_%s.%s', (string) $safeBaseName, Str::random(12), strtolower((string) $extension));
            }

            $arquivoPath = $file->storeAs('imports/convenios', $fileName, 'private');
        } catch (\Throwable $exception) {
            Log::error('Falha ao salvar arquivo de importacao de convenios.', [
                'disk' => 'private',
                'disk_root' => config('filesystems.disks.private.root'),
                'target_path' => storage_path('app/private/imports/convenios'),
                'php_user' => get_current_user(),
                'uid' => function_exists('posix_getuid') ? posix_getuid() : null,
                'gid' => function_exists('posix_getgid') ? posix_getgid() : null,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $import = ConvenioImport::query()->create([
            'arquivo_nome' => $file->getClientOriginalName(),
            'arquivo_path' => $arquivoPath,
            'status' => 'uploaded',
        ]);

        $absolutePath = $disk->path($arquivoPath);
        $spreadsheet = IOFactory::load($absolutePath);

        $parsedLista = $this->parseSheet($spreadsheet, 'lista', $this->expectedColumns()['lista']);
        $parsedParcelas = $this->parseSheet($spreadsheet, 'parcelas', $this->expectedColumns()['parcelas']);
        $parsedPi = $this->parseSheet($spreadsheet, 'plano_interno', $this->expectedColumns()['plano_interno']);

        foreach ($parsedLista['rows'] as $row) {
            ConvenioImportListaRow::query()->create([
                'import_id' => $import->id,
                'row_number' => $row['row_number'],
                'raw_data' => $row['raw_data'],
                'normalized_data' => $row['normalized_data'],
                'status' => $row['status'],
                'issues' => $row['issues'],
            ]);
        }

        foreach ($parsedParcelas['rows'] as $row) {
            ConvenioImportParcelaRow::query()->create([
                'import_id' => $import->id,
                'row_number' => $row['row_number'],
                'raw_data' => $row['raw_data'],
                'normalized_data' => $row['normalized_data'],
                'status' => $row['status'],
                'issues' => $row['issues'],
            ]);
        }

        foreach ($parsedPi['rows'] as $row) {
            ConvenioImportPiRow::query()->create([
                'import_id' => $import->id,
                'row_number' => $row['row_number'],
                'raw_data' => $row['raw_data'],
                'normalized_data' => $row['normalized_data'],
                'status' => $row['status'],
                'issues' => $row['issues'],
            ]);
        }

        $totalIssues = $this->countIssueRows($parsedLista['rows']) + $this->countIssueRows($parsedParcelas['rows']) + $this->countIssueRows($parsedPi['rows']);

        $import->update([
            'status' => 'parsed',
            'total_lista_rows' => count($parsedLista['rows']),
            'total_parcelas_rows' => count($parsedParcelas['rows']),
            'total_pi_rows' => count($parsedPi['rows']),
            'total_issues' => $totalIssues,
            'resumo' => [
                'sheets' => [
                    'lista' => [
                        'encontrada' => $parsedLista['sheet_found'],
                        'rows' => count($parsedLista['rows']),
                    ],
                    'parcelas' => [
                        'encontrada' => $parsedParcelas['sheet_found'],
                        'rows' => count($parsedParcelas['rows']),
                    ],
                    'plano_interno' => [
                        'encontrada' => $parsedPi['sheet_found'],
                        'rows' => count($parsedPi['rows']),
                    ],
                ],
            ],
        ]);

        return $import->fresh();
    }

    public function confirmImport(ConvenioImport $import, int $batchSize = 500): ConvenioImport
    {
        $import->update(['status' => 'processing']);
        $import->pendingItems()->delete();

        $counters = [
            'processados' => 0,
            'pendencias' => 0,
            'parcelas_status' => [
                'total' => 0,
                'paga' => 0,
                'em_aberto' => 0,
                'desconhecido' => 0,
                'desconhecidos_top' => [],
            ],
        ];

        $convenioIdByNumero = [];

        ConvenioImportListaRow::query()
            ->where('import_id', $import->id)
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) use ($import, &$counters, &$convenioIdByNumero): void {
                foreach ($rows as $row) {
                    $data = $row->normalized_data ?? [];
                    $numeroConvenio = $this->normalizeString($data['numero_convenio'] ?? null);

                    if ($numeroConvenio === null) {
                        $this->registerPending($import, 'lista', $row->id, $row->row_number, null, 'numero_convenio_ausente', $row->raw_data ?? []);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        $counters['pendencias']++;
                        continue;
                    }

                    $orgaoNome = $this->normalizeString($data['orgao'] ?? null);
                    $municipioNome = $this->normalizeString($data['municipio'] ?? null);
                    $convenente = $this->normalizeString($data['convenente'] ?? null);

                    $orgao = $this->resolveOrgao($orgaoNome);
                    $municipioBeneficiario = $this->resolveMunicipio($municipioNome);
                    $convenenteMunicipio = $this->resolveMunicipio($convenente);

                    $payload = [
                        'orgao_id' => $orgao?->id,
                        'orgao_nome_informado' => $orgao ? null : $orgaoNome,
                        'numero_convenio' => $numeroConvenio,
                        'ano_referencia' => $this->normalizeInteger($data['ano'] ?? null),
                        'municipio_beneficiario_id' => $municipioBeneficiario?->id,
                        'municipio_beneficiario_nome_informado' => $municipioBeneficiario ? null : $municipioNome,
                        'convenente_nome' => $convenente,
                        'convenente_municipio_id' => $convenenteMunicipio?->id,
                        'convenente_municipio_nome_informado' => $convenenteMunicipio ? null : $convenente,
                        'objeto' => $this->normalizeString($data['objeto'] ?? null),
                        'grupo_despesa' => $this->normalizeString($data['grupo_despesa'] ?? null),
                        'quantidade_parcelas_informada' => $this->normalizeInteger($data['quantidade_parcelas'] ?? null),
                        'data_inicio' => $this->normalizeDate($data['data_inicio'] ?? null),
                        'data_fim' => $this->normalizeDate($data['data_fim'] ?? null),
                        'valor_total_informado' => $this->normalizeDecimal($data['valor_total'] ?? null),
                        'valor_total_calculado' => $this->normalizeDecimal($data['valor_total'] ?? null),
                        'valor_orgao' => $this->normalizeDecimal($data['valor_orgao'] ?? null),
                        'valor_contrapartida' => $this->normalizeDecimal($data['valor_contrapartida'] ?? null),
                        'dados_origem' => [
                            'import_id' => $import->id,
                            'sheet' => 'lista',
                            'row_number' => $row->row_number,
                            'raw_data' => $row->raw_data,
                        ],
                    ];

                    $convenio = Convenio::query()
                        ->withTrashed()
                        ->where('numero_convenio', $numeroConvenio)
                        ->first();

                    if ($convenio) {
                        $convenio->fill($payload)->save();
                        if ($convenio->trashed()) {
                            $convenio->restore();
                        }
                    } else {
                        $convenio = Convenio::query()->create($payload);
                    }

                    $convenioIdByNumero[$numeroConvenio] = $convenio->id;

                    if ($orgaoNome !== null && $orgao === null) {
                        $this->registerPending($import, 'lista', $row->id, $row->row_number, $numeroConvenio, 'orgao_nao_encontrado', ['orgao' => $orgaoNome]);
                        $counters['pendencias']++;
                    }
                    if ($municipioNome !== null && $municipioBeneficiario === null) {
                        $this->registerPending($import, 'lista', $row->id, $row->row_number, $numeroConvenio, 'municipio_beneficiario_nao_encontrado', ['municipio' => $municipioNome]);
                        $counters['pendencias']++;
                    }
                    if ($convenente !== null && $convenenteMunicipio === null) {
                        $this->registerPending($import, 'lista', $row->id, $row->row_number, $numeroConvenio, 'convenente_municipio_nao_encontrado', ['convenente' => $convenente]);
                        $counters['pendencias']++;
                    }

                    $row->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                        'normalized_data' => array_merge($data, ['convenio_id' => $convenio->id]),
                    ]);
                    $counters['processados']++;
                }
            }, 'id');

        ConvenioImportPiRow::query()
            ->where('import_id', $import->id)
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) use ($import, &$counters, &$convenioIdByNumero): void {
                foreach ($rows as $row) {
                    $data = $row->normalized_data ?? [];
                    $numeroConvenio = $this->normalizeString($data['numero_convenio'] ?? null);
                    $planoInterno = $this->normalizeString($data['plano_interno'] ?? null);

                    if ($numeroConvenio === null || $planoInterno === null) {
                        $this->registerPending($import, 'plano_interno', $row->id, $row->row_number, $numeroConvenio, 'pi_invalido', $row->raw_data ?? []);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        $counters['pendencias']++;
                        continue;
                    }

                    $convenioId = $convenioIdByNumero[$numeroConvenio] ?? Convenio::query()->where('numero_convenio', $numeroConvenio)->value('id');
                    if (! $convenioId) {
                        $this->registerPending($import, 'plano_interno', $row->id, $row->row_number, $numeroConvenio, 'convenio_nao_encontrado', $row->raw_data ?? []);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        $counters['pendencias']++;
                        continue;
                    }

                    $this->upsertPlanoInterno($convenioId, $planoInterno, 'plano_interno');

                    $row->update(['status' => 'processed', 'processed_at' => now()]);
                    $counters['processados']++;
                }
            }, 'id');

        ConvenioImportParcelaRow::query()
            ->where('import_id', $import->id)
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) use ($import, &$counters, &$convenioIdByNumero): void {
                foreach ($rows as $row) {
                    $data = $row->normalized_data ?? [];
                    $numeroConvenio = $this->normalizeString($data['numero_convenio'] ?? null);
                    $numeroParcela = $this->normalizeInteger($data['numero_parcela'] ?? null);

                    if ($numeroConvenio === null || $numeroParcela === null) {
                        $this->registerPending($import, 'parcelas', $row->id, $row->row_number, $numeroConvenio, 'parcela_sem_chave', $row->raw_data ?? []);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        $counters['pendencias']++;
                        continue;
                    }

                    $convenioId = $convenioIdByNumero[$numeroConvenio] ?? Convenio::query()->where('numero_convenio', $numeroConvenio)->value('id');
                    if (! $convenioId) {
                        $this->registerPending($import, 'parcelas', $row->id, $row->row_number, $numeroConvenio, 'convenio_nao_encontrado', $row->raw_data ?? []);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        $counters['pendencias']++;
                        continue;
                    }

                    $valorPrevisto = $this->normalizeDecimal($data['valor_previsto'] ?? null);
                    $valorPago = $this->normalizeDecimal($data['valor_pago'] ?? null);
                    $rawStatus = $this->extractRawParcelaStatus($row->raw_data ?? [], $data);
                    $statusClassificacao = NormalizeParcelaStatus::classify($rawStatus);
                    $statusNormalizado = NormalizeParcelaStatus::normalize($rawStatus);
                    $situacao = NormalizeParcelaStatus::toParcelaSituacao($rawStatus);

                    $this->incrementParcelaStatusCounters($counters['parcelas_status'], $statusClassificacao, $rawStatus);

                    Log::debug('Importacao de parcela: status normalizado.', [
                        'import_id' => $import->id,
                        'row_number' => $row->row_number,
                        'numero_convenio' => $numeroConvenio,
                        'numero_parcela' => $numeroParcela,
                        'status_bruto' => $rawStatus,
                        'status_normalizado' => $statusNormalizado,
                        'status_classificacao' => $statusClassificacao,
                        'situacao_final' => $situacao,
                    ]);

                    if ($statusClassificacao === NormalizeParcelaStatus::DESCONHECIDO) {
                        Log::warning('Importacao de parcela com status desconhecido; fallback para em aberto.', [
                            'import_id' => $import->id,
                            'row_number' => $row->row_number,
                            'numero_convenio' => $numeroConvenio,
                            'numero_parcela' => $numeroParcela,
                            'status_bruto' => $rawStatus,
                            'status_normalizado' => $statusNormalizado,
                        ]);

                        $this->registerPending(
                            $import,
                            'parcelas',
                            $row->id,
                            $row->row_number,
                            $numeroConvenio,
                            'status_parcela_desconhecido',
                            [
                                'status_bruto' => $rawStatus,
                                'status_normalizado' => $statusNormalizado,
                                'situacao_fallback' => $situacao,
                            ]
                        );
                        $counters['pendencias']++;
                    }

                    $payload = [
                        'convenio_id' => $convenioId,
                        'convenio_numero_informado' => $numeroConvenio,
                        'numero' => $numeroParcela,
                        'valor_previsto' => $valorPrevisto,
                        'situacao' => $situacao,
                        'data_pagamento' => $this->normalizeDate($data['data_pagamento'] ?? null),
                        'valor_pago' => $valorPago,
                        'observacoes' => $this->normalizeString($data['observacoes'] ?? null),
                        'dados_origem' => [
                            'import_id' => $import->id,
                            'sheet' => 'parcelas',
                            'row_number' => $row->row_number,
                            'raw_data' => $row->raw_data,
                            'status_bruto' => $rawStatus,
                            'status_normalizado' => $statusNormalizado,
                            'status_classificacao' => $statusClassificacao,
                        ],
                    ];

                    $parcela = Parcela::query()
                        ->withTrashed()
                        ->where('convenio_id', $convenioId)
                        ->where('numero', $numeroParcela)
                        ->first();

                    if ($parcela) {
                        $parcela->fill($payload)->save();
                        if ($parcela->trashed()) {
                            $parcela->restore();
                        }
                    } else {
                        Parcela::query()->create($payload);
                    }

                    $row->update(['status' => 'processed', 'processed_at' => now()]);
                    $counters['processados']++;
                }
            }, 'id');

        $totalIssues = $import->listaRows()->whereNotNull('issues')->count()
            + $import->parcelasRows()->whereNotNull('issues')->count()
            + $import->piRows()->whereNotNull('issues')->count()
            + $import->pendingItems()->count();

        $import->update([
            'status' => 'confirmed',
            'confirmado_em' => now(),
            'total_processados' => $counters['processados'],
            'total_pendencias' => $import->pendingItems()->count(),
            'total_issues' => $totalIssues,
            'resumo' => array_merge($import->resumo ?? [], [
                'confirmacao' => [
                    'processados' => $counters['processados'],
                    'pendencias' => $import->pendingItems()->count(),
                ],
                'parcelas_status' => [
                    'total' => $counters['parcelas_status']['total'],
                    'paga' => $counters['parcelas_status']['paga'],
                    'em_aberto' => $counters['parcelas_status']['em_aberto'],
                    'desconhecido' => $counters['parcelas_status']['desconhecido'],
                    'top_20_status_desconhecidos' => $this->topStatusDesconhecidos($counters['parcelas_status']['desconhecidos_top']),
                ],
            ]),
        ]);

        return $import->fresh();
    }

    /**
     * @param  array<string, array<int, string>>  $expectedColumns
     * @return array{sheet_found: bool, rows: array<int, array<string, mixed>>}
     */
    private function parseSheet(Spreadsheet $spreadsheet, string $sheetName, array $expectedColumns): array
    {
        $sheet = $this->findSheet($spreadsheet, $sheetName);
        if (! $sheet) {
            return [
                'sheet_found' => false,
                'rows' => [],
            ];
        }

        $rows = $sheet->toArray(null, true, true, true);
        if ($rows === []) {
            return [
                'sheet_found' => true,
                'rows' => [],
            ];
        }

        $header = array_shift($rows);
        $headerMap = [];
        $rawHeaderNames = [];
        foreach ($header as $column => $value) {
            $rawHeaderNames[$column] = trim((string) $value);
            $headerKey = $this->normalizeHeaderKey($value);
            if ($headerKey !== '') {
                $headerMap[$headerKey] = $column;
            }
        }

        $parsedRows = [];
        $rowNumber = 1;
        foreach ($rows as $row) {
            $rowNumber++;
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rawData = $this->extractRawData($row, $rawHeaderNames);
            $normalizedData = [];
            $issues = [];

            foreach ($expectedColumns as $targetField => $aliases) {
                $value = null;
                foreach ($aliases as $alias) {
                    $headerKey = $this->normalizeHeaderKey($alias);
                    if (isset($headerMap[$headerKey])) {
                        $value = $row[$headerMap[$headerKey]] ?? null;
                        break;
                    }
                }

                $normalizedData[$targetField] = $this->normalizeValue($targetField, $value, $issues);
            }

            if ($sheetName === 'lista' && $this->rowHasValueForAliases($row, $headerMap, ['plano_interno'])) {
                $normalizedData['plano_interno'] = null;
                $normalizedData['ignored_columns'] = array_merge($normalizedData['ignored_columns'] ?? [], [
                    'plano_interno' => true,
                ]);
                $issues[] = [
                    'type' => 'ignored_column',
                    'field' => 'plano_interno',
                    'message' => 'Coluna plano_interno da aba lista foi ignorada (PI vem da aba plano_interno).',
                ];
            }

            if (in_array($sheetName, ['lista', 'parcelas', 'plano_interno'], true) && $normalizedData['numero_convenio'] === null) {
                $issues[] = 'numero_convenio_ausente';
            }
            if ($sheetName === 'parcelas' && $normalizedData['numero_parcela'] === null) {
                $issues[] = 'numero_parcela_ausente';
            }
            if ($sheetName === 'plano_interno' && $normalizedData['plano_interno'] === null) {
                $issues[] = 'plano_interno_ausente';
            }

            $parsedRows[] = [
                'row_number' => $rowNumber,
                'raw_data' => $rawData,
                'normalized_data' => $normalizedData,
                'status' => $issues === [] ? 'parsed' : 'parsed_with_issues',
                'issues' => $issues,
            ];
        }

        return [
            'sheet_found' => true,
            'rows' => $parsedRows,
        ];
    }

    private function findSheet(Spreadsheet $spreadsheet, string $expectedName): mixed
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if ($this->normalizeHeaderKey($sheet->getTitle()) === $this->normalizeHeaderKey($expectedName)) {
                return $sheet;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $rawHeaderNames
     * @return array<string, string>
     */
    private function extractRawData(array $row, array $rawHeaderNames): array
    {
        $rawData = [];
        foreach ($row as $column => $value) {
            $stringValue = trim((string) $value);
            if ($stringValue === '') {
                continue;
            }
            $header = $rawHeaderNames[$column] ?: $column;
            $rawData[$header] = $stringValue;
        }

        return $rawData;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $headerMap
     * @param  array<int, string>  $aliases
     */
    private function rowHasValueForAliases(array $row, array $headerMap, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            $headerKey = $this->normalizeHeaderKey($alias);
            if (! isset($headerMap[$headerKey])) {
                continue;
            }

            $value = $row[$headerMap[$headerKey]] ?? null;
            if ($this->normalizeString($value) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string|array<string, string>>  $issues
     */
    private function normalizeValue(string $field, mixed $value, array &$issues): mixed
    {
        return match ($field) {
            'ano', 'numero_parcela', 'quantidade_parcelas' => $this->normalizeInteger($value),
            'valor_total', 'valor_orgao', 'valor_contrapartida', 'valor_previsto', 'valor_pago' => $this->normalizeDecimal($value),
            'data_inicio', 'data_fim', 'data_pagamento' => $this->normalizeDate($value, $issues, $field),
            default => $this->normalizeString($value),
        };
    }

    private function normalizeHeaderKey(mixed $value): string
    {
        $ascii = Str::of((string) $value)->ascii()->lower()->replace([' ', '-', '.'], '_')->trim()->toString();
        $ascii = preg_replace('/[^a-z0-9_]/', '', $ascii) ?? '';

        return preg_replace('/_+/', '_', $ascii) ?? '';
    }

    private function normalizeString(mixed $value): ?string
    {
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        return $string;
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $stringValue = preg_replace('/[^0-9-]/', '', (string) $value);
        if ($stringValue === null || $stringValue === '' || $stringValue === '-') {
            return null;
        }

        return (int) $stringValue;
    }

    private function normalizeDecimal(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $raw = trim((string) $value);
        $raw = preg_replace('/[^\d,.-]/', '', $raw) ?? '';

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $stringValue = str_replace('.', '', $raw);
            $stringValue = str_replace(',', '.', $stringValue);
        } elseif (str_contains($raw, ',')) {
            $stringValue = str_replace(',', '.', $raw);
        } else {
            $stringValue = $raw;
        }

        return is_numeric($stringValue) ? (float) $stringValue : null;
    }

    /**
     * @param  array<int, string|array<string, string>>|null  $issues
     */
    private function normalizeDate(mixed $value, ?array &$issues = null, string $field = 'data'): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                if ($issues !== null) {
                    $issues[] = $field.'_invalida';
                }

                return null;
            }
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, trim((string) $value))->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            if ($issues !== null) {
                $issues[] = $field.'_invalida';
            }

            return null;
        }
    }

    private function resolveOrgao(?string $valor): ?Orgao
    {
        if ($valor === null) {
            return null;
        }

        return Orgao::query()
            ->where('sigla', $valor)
            ->orWhere('nome', $valor)
            ->first();
    }

    private function resolveMunicipio(?string $valor): ?Municipio
    {
        if ($valor === null) {
            return null;
        }

        return Municipio::query()
            ->where('nome', $valor)
            ->first();
    }

    private function upsertPlanoInterno(int $convenioId, string $planoInterno, string $origem): void
    {
        ConvenioPlanoInterno::query()->updateOrCreate(
            [
                'convenio_id' => $convenioId,
                'plano_interno' => strtoupper(trim($planoInterno)),
            ],
            [
                'origem' => $origem,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function registerPending(
        ConvenioImport $import,
        string $sheet,
        ?int $rowId,
        ?int $rowNumber,
        ?string $referenceKey,
        string $reason,
        array $payload
    ): void {
        ConvenioImportPendingItem::query()->create([
            'import_id' => $import->id,
            'source_sheet' => $sheet,
            'source_row_id' => $rowId,
            'source_row_number' => $rowNumber,
            'reference_key' => $referenceKey,
            'reason' => $reason,
            'payload' => $payload,
        ]);
    }

    /**
     * @param  array<string, mixed>  $rawData
     * @param  array<string, mixed>  $normalizedData
     */
    private function extractRawParcelaStatus(array $rawData, array $normalizedData): ?string
    {
        foreach ($rawData as $key => $value) {
            if ($this->normalizeHeaderKey($key) === 'situacao') {
                return $this->normalizeString($value);
            }
        }

        return $this->normalizeString($normalizedData['situacao'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $counters
     */
    private function incrementParcelaStatusCounters(array &$counters, string $classificacao, ?string $rawStatus): void
    {
        $counters['total']++;

        if ($classificacao === NormalizeParcelaStatus::PAGA) {
            $counters['paga']++;

            return;
        }

        if ($classificacao === NormalizeParcelaStatus::EM_ABERTO) {
            $counters['em_aberto']++;

            return;
        }

        $counters['desconhecido']++;
        $key = $rawStatus !== null && trim($rawStatus) !== '' ? trim($rawStatus) : '(vazio)';
        $counters['desconhecidos_top'][$key] = ($counters['desconhecidos_top'][$key] ?? 0) + 1;
    }

    /**
     * @param  array<string, int>  $unknownCounters
     * @return array<int, array{status_bruto: string, ocorrencias: int}>
     */
    private function topStatusDesconhecidos(array $unknownCounters): array
    {
        arsort($unknownCounters);
        $top = array_slice($unknownCounters, 0, 20, true);

        $result = [];
        foreach ($top as $status => $count) {
            $result[] = [
                'status_bruto' => $status,
                'ocorrencias' => $count,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function countIssueRows(array $rows): int
    {
        return count(array_filter($rows, fn ($row) => ! empty($row['issues'])));
    }
}
