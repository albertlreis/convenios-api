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
use App\Support\PtBrNumberParser;
use App\Support\TextNormalizer;
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
                'municipio_ibge' => ['municipio_ibge', 'codigo_ibge'],
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
            ],

            // ✅ IMPORTANTE: parcelas NÃO tem coluna orgao; ela existe apenas na aba lista.
            'parcelas' => [
                'numero_convenio' => ['numero_convenio'],
                'numero_parcela' => ['numero_parcela'],
                'valor_previsto' => ['valor_previsto'],
                'situacao' => ['situacao'],
                'data_pagamento' => ['data_pagamento'],
                'valor_pago' => ['valor_pago'],
                'observacoes' => ['observacoes'],
            ],

            // ✅ PI também não depende de orgao; vínculo é numero_convenio.
            'plano_interno' => [
                'orgao' => ['orgao'],
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

        $totalIssues = $this->countIssueRows($parsedLista['rows'])
            + $this->countIssueRows($parsedParcelas['rows'])
            + $this->countIssueRows($parsedPi['rows']);

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

        // ✅ Cache principal: a “chave de ligação” entre abas é numero_convenio
        $convenioIdByNumero = [];      // [numero_convenio => convenio_id]
        $orgaoIdByNumero = [];         // [numero_convenio => orgao_id] (para detectar conflito)
        $numeroConvenioAmbiguo = [];   // [numero_convenio => true] quando houver conflito

        ConvenioImportListaRow::query()
            ->where('import_id', $import->id)
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) use ($import, &$counters, &$convenioIdByNumero, &$orgaoIdByNumero, &$numeroConvenioAmbiguo): void {
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
                    $municipioIbge = $this->normalizeString($data['municipio_ibge'] ?? null);
                    $convenente = $this->normalizeString($data['convenente'] ?? null);

                    $orgao = $this->resolveOrgao($orgaoNome);
                    $municipio = $this->resolveMunicipio($municipioNome, $municipioIbge);

                    if ($orgao === null) {
                        $this->registerPending($import, 'lista', $row->id, $row->row_number, $numeroConvenio, 'orgao_nao_encontrado', [
                            'orgao' => $orgaoNome,
                        ]);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        $counters['pendencias']++;
                        continue;
                    }

                    // ✅ Detecta conflito: mesmo numero_convenio com orgao diferente na aba lista
                    $existingOrgaoId = $orgaoIdByNumero[$numeroConvenio] ?? null;
                    if ($existingOrgaoId !== null && $existingOrgaoId !== $orgao->id) {
                        $numeroConvenioAmbiguo[$numeroConvenio] = true;
                        unset($convenioIdByNumero[$numeroConvenio]);

                        $this->registerPending(
                            $import,
                            'lista',
                            $row->id,
                            $row->row_number,
                            $numeroConvenio,
                            'numero_convenio_ambiguo_em_lista',
                            [
                                'orgao_existente_id' => $existingOrgaoId,
                                'orgao_novo_id' => $orgao->id,
                                'orgao_novo' => $orgaoNome,
                                'message' => 'Mesmo numero_convenio apareceu com orgao diferente na aba lista; abas parcelas/PI ficam ambiguas.',
                            ]
                        );

                        // ainda assim processa/atualiza o convênio por (orgao_id + numero_convenio)
                        // mas marca a ambiguidade para impedir vínculo automático nas outras abas.
                    } else {
                        $orgaoIdByNumero[$numeroConvenio] = $orgao->id;
                    }

                    $payload = [
                        'orgao_id' => $orgao->id,
                        'numero_convenio' => $numeroConvenio,
                        'ano_referencia' => $this->normalizeInteger($data['ano'] ?? null),
                        'municipio_id' => $municipio?->id,
                        'convenente_nome' => $convenente,
                        'objeto' => $this->normalizeString($data['objeto'] ?? null),
                        'grupo_despesa' => $this->normalizeString($data['grupo_despesa'] ?? null),
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
                        ->where('orgao_id', $orgao->id)
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

                    // ✅ Cache por numero_convenio (somente se não estiver ambiguo)
                    if (! isset($numeroConvenioAmbiguo[$numeroConvenio])) {
                        $convenioIdByNumero[$numeroConvenio] = $convenio->id;
                    }

                    if (($municipioNome !== null || $municipioIbge !== null) && $municipio === null) {
                        $this->registerPending($import, 'lista', $row->id, $row->row_number, $numeroConvenio, 'municipio_nao_encontrado', [
                            'municipio' => $municipioNome,
                            'municipio_ibge' => $municipioIbge,
                        ]);
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
            ->chunkById($batchSize, function ($rows) use ($import, &$counters, &$convenioIdByNumero, &$numeroConvenioAmbiguo): void {
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

                    // ✅ Resolve convenio_id só por numero_convenio
                    [$convenioId, $reason] = $this->resolveConvenioIdByNumero($numeroConvenio, $convenioIdByNumero, $numeroConvenioAmbiguo);

                    if (! $convenioId) {
                        $this->registerPending(
                            $import,
                            'plano_interno',
                            $row->id,
                            $row->row_number,
                            $numeroConvenio,
                            $reason ?? 'convenio_nao_encontrado',
                            $row->raw_data ?? []
                        );
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        $counters['pendencias']++;
                        continue;
                    }

                    $this->upsertPlanoInterno($convenioId, $planoInterno);

                    $row->update(['status' => 'processed', 'processed_at' => now()]);
                    $counters['processados']++;
                }
            }, 'id');

        ConvenioImportParcelaRow::query()
            ->where('import_id', $import->id)
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) use ($import, &$counters, &$convenioIdByNumero, &$numeroConvenioAmbiguo): void {
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

                    // ✅ Resolve convenio_id só por numero_convenio
                    [$convenioId, $reason] = $this->resolveConvenioIdByNumero($numeroConvenio, $convenioIdByNumero, $numeroConvenioAmbiguo);

                    if (! $convenioId) {
                        $this->registerPending(
                            $import,
                            'parcelas',
                            $row->id,
                            $row->row_number,
                            $numeroConvenio,
                            $reason ?? 'convenio_nao_encontrado',
                            $row->raw_data ?? []
                        );
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

        $totalIssues = $import->listaRows()->whereJsonLength('issues', '>', 0)->count()
            + $import->parcelasRows()->whereJsonLength('issues', '>', 0)->count()
            + $import->piRows()->whereJsonLength('issues', '>', 0)->count()
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

    public function confirmPlanoInternoPorOrgao(ConvenioImport $import, bool $sync = true, int $batchSize = 500): ConvenioImport
    {
        $import->update(['status' => 'processing']);
        $import->pendingItems()->delete();

        $validRowsByKey = [];
        $desiredPisByKey = [];
        $validSiglas = [];
        $orgaosNaoEncontrados = [];
        $processados = 0;
        $piUpsertRowsTotal = 0;
        $piRemovidosTotal = 0;

        ConvenioImportPiRow::query()
            ->where('import_id', $import->id)
            ->orderBy('id')
            ->chunkById($batchSize, function ($rows) use (
                $import,
                &$validRowsByKey,
                &$desiredPisByKey,
                &$validSiglas,
                &$orgaosNaoEncontrados
            ): void {
                foreach ($rows as $row) {
                    $data = $row->normalized_data ?? [];
                    $orgaoSigla = $this->normalizeUpperString($data['orgao'] ?? null);
                    $numeroConvenio = $this->normalizeString($data['numero_convenio'] ?? null);
                    $planoInternoRaw = $this->normalizeString($data['plano_interno'] ?? null);

                    if ($orgaoSigla === null) {
                        $this->registerPending($import, 'plano_interno', $row->id, $row->row_number, $numeroConvenio, 'orgao_ausente', $row->raw_data ?? []);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        continue;
                    }

                    if ($numeroConvenio === null) {
                        $this->registerPending($import, 'plano_interno', $row->id, $row->row_number, null, 'numero_convenio_ausente', $row->raw_data ?? []);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        continue;
                    }

                    if ($planoInternoRaw === null) {
                        $this->registerPending($import, 'plano_interno', $row->id, $row->row_number, $numeroConvenio, 'plano_interno_ausente', $row->raw_data ?? []);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        continue;
                    }

                    $pis = $this->explodePlanoInternoValues($planoInternoRaw);
                    if ($pis === []) {
                        $this->registerPending($import, 'plano_interno', $row->id, $row->row_number, $numeroConvenio, 'plano_interno_ausente', $row->raw_data ?? []);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        continue;
                    }

                    $invalidPi = collect($pis)->first(fn (string $pi) => mb_strlen($pi) > 32);
                    if ($invalidPi !== null) {
                        $this->registerPending($import, 'plano_interno', $row->id, $row->row_number, $numeroConvenio, 'plano_interno_tamanho_invalido', [
                            'orgao' => $orgaoSigla,
                            'numero_convenio' => $numeroConvenio,
                            'plano_interno' => $invalidPi,
                        ]);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        continue;
                    }

                    $orgao = $this->resolveOrgao($orgaoSigla);
                    if ($orgao === null) {
                        $orgaosNaoEncontrados[$orgaoSigla] = true;
                        $this->registerPending($import, 'plano_interno', $row->id, $row->row_number, $numeroConvenio, 'orgao_nao_encontrado', [
                            'orgao' => $orgaoSigla,
                            'numero_convenio' => $numeroConvenio,
                            'plano_interno' => $planoInternoRaw,
                        ]);
                        $row->update(['status' => 'pending', 'processed_at' => now()]);
                        continue;
                    }

                    $validSigla = strtoupper(trim((string) $orgao->sigla));
                    $validSiglas[$validSigla] = true;
                    $key = $this->makeConvenioKey((int) $orgao->id, $numeroConvenio);

                    foreach ($pis as $pi) {
                        $desiredPisByKey[$key][$pi] = true;
                    }

                    $validRowsByKey[$key][] = [
                        'row_id' => (int) $row->id,
                        'row_number' => (int) $row->row_number,
                        'orgao' => $validSigla,
                        'numero_convenio' => $numeroConvenio,
                        'plano_interno' => $planoInternoRaw,
                        'pis' => $pis,
                    ];
                }
            }, 'id');

        $multipleOrgaos = count($validSiglas) > 1;
        if ($multipleOrgaos) {
            $this->registerPending($import, 'plano_interno', null, null, null, 'orgao_multiplo_na_planilha', [
                'siglas' => array_values(array_keys($validSiglas)),
            ]);
        }

        $conveniosNaoEncontradosMap = [];
        $conveniosNaoEncontradosPorOrgao = [];
        $conveniosExcluidos = [];
        $conveniosDuplicadosExcluidos = [];
        $conveniosResolvidos = [];
        $pendenciasExtras = 0;

        foreach ($desiredPisByKey as $key => $desiredPisSet) {
            [$orgaoId, $numeroConvenio] = $this->splitConvenioKey($key);
            $rowsMeta = $validRowsByKey[$key] ?? [];
            $firstMeta = $rowsMeta[0] ?? null;
            $orgaoSigla = $firstMeta['orgao'] ?? null;

            $activeConvenio = Convenio::query()
                ->whereNull('deleted_at')
                ->where('orgao_id', $orgaoId)
                ->where('numero_convenio', $numeroConvenio)
                ->first();

            if (! $activeConvenio) {
                $withTrashed = Convenio::query()
                    ->withTrashed()
                    ->where('orgao_id', $orgaoId)
                    ->where('numero_convenio', $numeroConvenio)
                    ->limit(2)
                    ->get();

                if ($withTrashed->count() === 0) {
                    $uniqueKey = ($orgaoSigla ?? 'SEM_ORGAO').'|'.$numeroConvenio;
                    $conveniosNaoEncontradosMap[$uniqueKey] = $numeroConvenio;
                    if ($orgaoSigla !== null) {
                        $conveniosNaoEncontradosPorOrgao[$orgaoSigla][$numeroConvenio] = true;
                    }

                    foreach ($rowsMeta as $meta) {
                        $this->registerPending(
                            $import,
                            'plano_interno',
                            (int) $meta['row_id'],
                            (int) $meta['row_number'],
                            (string) $meta['numero_convenio'],
                            'convenio_nao_encontrado',
                            [
                                'orgao' => $meta['orgao'],
                                'numero_convenio' => $meta['numero_convenio'],
                                'plano_interno' => $meta['plano_interno'],
                            ]
                        );
                        ConvenioImportPiRow::query()->where('id', $meta['row_id'])->update([
                            'status' => 'pending',
                            'processed_at' => now(),
                        ]);
                        $pendenciasExtras++;
                    }

                    continue;
                }

                if ($withTrashed->count() === 1 && $withTrashed->first()?->deleted_at !== null) {
                    $conveniosExcluidos[$key] = true;
                    foreach ($rowsMeta as $meta) {
                        $this->registerPending(
                            $import,
                            'plano_interno',
                            (int) $meta['row_id'],
                            (int) $meta['row_number'],
                            (string) $meta['numero_convenio'],
                            'convenio_excluido',
                            [
                                'orgao' => $meta['orgao'],
                                'numero_convenio' => $meta['numero_convenio'],
                                'plano_interno' => $meta['plano_interno'],
                            ]
                        );
                        ConvenioImportPiRow::query()->where('id', $meta['row_id'])->update([
                            'status' => 'pending',
                            'processed_at' => now(),
                        ]);
                        $pendenciasExtras++;
                    }
                    continue;
                }

                $conveniosDuplicadosExcluidos[$key] = true;
                foreach ($rowsMeta as $meta) {
                    $this->registerPending(
                        $import,
                        'plano_interno',
                        (int) $meta['row_id'],
                        (int) $meta['row_number'],
                        (string) $meta['numero_convenio'],
                        'convenio_duplicado_excluido',
                        [
                            'orgao' => $meta['orgao'],
                            'numero_convenio' => $meta['numero_convenio'],
                            'plano_interno' => $meta['plano_interno'],
                        ]
                    );
                    ConvenioImportPiRow::query()->where('id', $meta['row_id'])->update([
                        'status' => 'pending',
                        'processed_at' => now(),
                    ]);
                    $pendenciasExtras++;
                }
                continue;
            }

            $desiredPis = array_values(array_keys($desiredPisSet));
            if ($desiredPis !== []) {
                $rows = array_map(
                    fn (string $pi): array => [
                        'convenio_id' => $activeConvenio->id,
                        'plano_interno' => $pi,
                    ],
                    $desiredPis
                );
                ConvenioPlanoInterno::query()->upsert($rows, ['convenio_id', 'plano_interno'], []);
                $piUpsertRowsTotal += count($rows);
            }

            if ($sync && ! $multipleOrgaos) {
                $deleted = ConvenioPlanoInterno::query()
                    ->where('convenio_id', $activeConvenio->id)
                    ->when($desiredPis !== [], fn ($query) => $query->whereNotIn('plano_interno', $desiredPis))
                    ->delete();
                $piRemovidosTotal += $deleted;
            }

            foreach ($rowsMeta as $meta) {
                ConvenioImportPiRow::query()->where('id', $meta['row_id'])->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);
                $processados++;
            }

            $conveniosResolvidos[$key] = true;
        }

        $totalPendencias = $import->pendingItems()->count();
        $conveniosNaoEncontradosLista = array_values(array_unique(array_values($conveniosNaoEncontradosMap)));
        sort($conveniosNaoEncontradosLista);

        $conveniosNaoEncontradosPorOrgaoResumo = [];
        foreach ($conveniosNaoEncontradosPorOrgao as $sigla => $numeros) {
            $conveniosNaoEncontradosPorOrgaoResumo[$sigla] = count($numeros);
        }

        $resumoFinal = array_merge($import->resumo ?? [], [
            'tipo' => 'plano_interno_por_orgao',
            'convenios_nao_encontrados_total' => count($conveniosNaoEncontradosMap),
            'convenios_nao_encontrados_lista' => array_slice($conveniosNaoEncontradosLista, 0, 50),
            'convenios_nao_encontrados_por_orgao' => $conveniosNaoEncontradosPorOrgaoResumo,
            'confirmacao_pi_por_orgao' => [
                'sync_solicitado' => $sync,
                'sync_executado' => $sync && ! $multipleOrgaos,
                'sync_ignorado_motivo' => $sync && $multipleOrgaos ? 'orgao_multiplo_na_planilha' : null,
                'linhas_processadas' => $processados,
                'pendencias' => $totalPendencias,
                'convenios_tocados_total' => count($desiredPisByKey),
                'convenios_resolvidos_total' => count($conveniosResolvidos),
                'pi_upsert_rows_total' => $piUpsertRowsTotal,
                'pi_removidos_total' => $piRemovidosTotal,
                'orgaos_nao_encontrados_total' => count($orgaosNaoEncontrados),
                'orgaos_validos_na_planilha' => array_values(array_keys($validSiglas)),
                'convenios_nao_encontrados_total' => count($conveniosNaoEncontradosMap),
                'convenios_nao_encontrados_lista' => array_slice($conveniosNaoEncontradosLista, 0, 50),
                'convenios_nao_encontrados_por_orgao' => $conveniosNaoEncontradosPorOrgaoResumo,
                'convenios_excluidos_total' => count($conveniosExcluidos),
                'convenios_duplicados_excluidos_total' => count($conveniosDuplicadosExcluidos),
                'pendencias_resolucao_convenio_total' => $pendenciasExtras,
            ],
        ]);

        Log::info('Importacao PI por orgao confirmada.', [
            'import_id' => $import->id,
            'resumo' => $resumoFinal['confirmacao_pi_por_orgao'] ?? [],
        ]);

        $import->update([
            'status' => 'confirmed',
            'confirmado_em' => now(),
            'total_processados' => $processados,
            'total_pendencias' => $totalPendencias,
            'total_issues' => $import->piRows()->whereJsonLength('issues', '>', 0)->count() + $totalPendencias,
            'resumo' => $resumoFinal,
        ]);

        return $import->fresh();
    }

    /**
     * ✅ Resolve o convenio_id somente pelo numero_convenio.
     * - Prioriza cache construído a partir da aba lista
     * - Faz fallback no banco
     * - Detecta ambiguidade (mais de 1 convênio com mesmo numero_convenio)
     *
     * @param  array<string, int>  $cache
     * @param  array<string, bool> $ambiguous
     * @return array{0: int|null, 1: string|null}
     */
    private function resolveConvenioIdByNumero(string $numeroConvenio, array &$cache, array &$ambiguous): array
    {
        if (isset($ambiguous[$numeroConvenio])) {
            return [null, 'numero_convenio_ambiguo'];
        }

        if (isset($cache[$numeroConvenio]) && $cache[$numeroConvenio] > 0) {
            return [$cache[$numeroConvenio], null];
        }

        $ids = Convenio::query()
            ->where('numero_convenio', $numeroConvenio)
            ->limit(2)
            ->pluck('id');

        if ($ids->count() === 1) {
            $id = (int) $ids->first();
            $cache[$numeroConvenio] = $id;

            return [$id, null];
        }

        if ($ids->count() === 0) {
            return [null, 'convenio_nao_encontrado'];
        }

        $ambiguous[$numeroConvenio] = true;

        return [null, 'numero_convenio_ambiguo'];
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
            if ($sheetName === 'parcelas' && ($normalizedData['numero_parcela'] ?? null) === null) {
                $issues[] = 'numero_parcela_ausente';
            }
            if ($sheetName === 'plano_interno' && ($normalizedData['plano_interno'] ?? null) === null) {
                $issues[] = 'plano_interno_ausente';
            }

            $issuesValue = $issues === [] ? null : $issues;

            $parsedRows[] = [
                'row_number' => $rowNumber,
                'raw_data' => $rawData,
                'normalized_data' => $normalizedData,
                'status' => $issuesValue === null ? 'parsed' : 'parsed_with_issues',
                'issues' => $issuesValue,
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

    private function normalizeUpperString(mixed $value): ?string
    {
        $string = $this->normalizeString($value);

        return $string !== null ? strtoupper($string) : null;
    }

    /**
     * @return array<int, string>
     */
    private function explodePlanoInternoValues(string $value): array
    {
        $parts = preg_split('/[;,|]+/', $value) ?: [];
        $normalized = [];
        foreach ($parts as $part) {
            $pi = $this->normalizeUpperString($part);
            if ($pi === null) {
                continue;
            }
            $normalized[$pi] = true;
        }

        return array_values(array_keys($normalized));
    }

    private function makeConvenioKey(int $orgaoId, string $numeroConvenio): string
    {
        return $orgaoId.'|'.$numeroConvenio;
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function splitConvenioKey(string $key): array
    {
        [$orgaoId, $numeroConvenio] = explode('|', $key, 2);

        return [(int) $orgaoId, $numeroConvenio];
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

    private function normalizeDecimal(mixed $value): ?string
    {
        return PtBrNumberParser::parseDecimal($value);
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

        $rawDate = trim((string) $value);
        $twoDigitYear = preg_match('/^\d{1,2}[\/\.-]\d{1,2}[\/\.-]\d{2}$/', $rawDate) === 1;
        $formats = $twoDigitYear
            ? ['d/m/y', 'd-m-y', 'd.m.y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y']
            : ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'd.m.y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $rawDate);
                if (str_contains($format, 'y') && ! str_contains($format, 'Y')) {
                    $year = (int) $date->format('Y');
                    if ($year < 100) {
                        $date->setYear($year + 2000);
                    }
                }

                return $date->format('Y-m-d');
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

        $sigla = strtoupper(trim($valor));
        if ($sigla === '') {
            return null;
        }

        return Orgao::query()->whereRaw('UPPER(sigla) = ?', [$sigla])->first();
    }

    private function resolveMunicipio(?string $nome, ?string $codigoIbge): ?Municipio
    {
        if ($codigoIbge !== null) {
            $codigo = preg_replace('/\D/', '', $codigoIbge);
            if ($codigo !== '') {
                $porIbge = Municipio::query()->where('codigo_ibge', $codigo)->first();
                if ($porIbge !== null) {
                    return $porIbge;
                }
            }
        }

        if ($nome === null) {
            return null;
        }

        $byNomeExato = Municipio::query()->whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)])->first();
        if ($byNomeExato !== null) {
            return $byNomeExato;
        }

        $normalized = TextNormalizer::normalizeForMatch($nome);
        if ($normalized === null) {
            return null;
        }

        $matches = Municipio::query()
            ->get(['id', 'nome'])
            ->filter(function (Municipio $municipio) use ($normalized): bool {
                return TextNormalizer::normalizeForMatch($municipio->nome) === $normalized;
            })
            ->values();

        if ($matches->count() !== 1) {
            return null;
        }

        return $matches->first();
    }

    private function upsertPlanoInterno(int $convenioId, string $planoInterno): void
    {
        ConvenioPlanoInterno::query()->updateOrCreate(
            [
                'convenio_id' => $convenioId,
                'plano_interno' => strtoupper(trim($planoInterno)),
            ],
            []
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
