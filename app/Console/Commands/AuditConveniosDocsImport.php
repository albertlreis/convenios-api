<?php

namespace App\Console\Commands;

use App\Models\Convenio;
use App\Models\ConvenioImport;
use App\Models\ConvenioPlanoInterno;
use App\Models\Parcela;
use App\Services\ConvenioImportService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AuditConveniosDocsImport extends Command
{
    protected $signature = 'import:auditar-convenios-docs
        {--path=docs : Diretório base com planilhas XLSX}
        {--file=* : Arquivos específicos a auditar}
        {--commit : Persiste o resultado da importação em vez de executar dry-run}
        {--json : Emite o relatório completo em JSON}';

    protected $description = 'Audita planilhas de convênios com staging + confirmação em modo seguro.';

    public function __construct(private readonly ConvenioImportService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $basePath = $this->resolveBasePath((string) $this->option('path'));
        $files = $this->resolveFiles($basePath, (array) $this->option('file'));

        if ($files === []) {
            $this->error(sprintf('Nenhuma planilha XLSX encontrada em %s.', $basePath));

            return self::FAILURE;
        }

        $report = [
            'mode' => $commit ? 'commit' : 'dry-run',
            'base_path' => $basePath,
            'files' => [],
        ];

        foreach ($files as $filePath) {
            $report['files'][] = $this->auditFile($filePath, $commit);
        }

        $report['totals'] = [
            'files' => count($report['files']),
            'ok' => count(array_filter($report['files'], fn (array $item): bool => ($item['status'] ?? null) === 'ok')),
            'errors' => count(array_filter($report['files'], fn (array $item): bool => ($item['status'] ?? null) !== 'ok')),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderHumanSummary($report);
        }

        return ($report['totals']['errors'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveBasePath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : base_path($path);
    }

    /**
     * @param  array<int, string>  $requestedFiles
     * @return array<int, string>
     */
    private function resolveFiles(string $basePath, array $requestedFiles): array
    {
        if ($requestedFiles !== []) {
            return array_values(array_filter(array_map(function (string $file) use ($basePath): ?string {
                $resolved = str_starts_with($file, '/') ? $file : $basePath.DIRECTORY_SEPARATOR.$file;

                return is_file($resolved) ? $resolved : null;
            }, $requestedFiles)));
        }

        $glob = glob(rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.xlsx');
        if (! is_array($glob)) {
            return [];
        }

        sort($glob);

        return array_values(array_filter($glob, 'is_file'));
    }

    /**
     * @return array<string, mixed>
     */
    private function auditFile(string $filePath, bool $commit): array
    {
        $result = [
            'file' => basename($filePath),
            'path' => $filePath,
            'mode' => $commit ? 'commit' : 'dry-run',
            'status' => 'ok',
        ];

        $storedImportPath = null;

        try {
            $result['structure'] = $this->service->inspectWorkbookPath($filePath, ['lista', 'parcelas', 'plano_interno']);

            DB::beginTransaction();

            $before = $this->snapshotCounts();
            $upload = new UploadedFile(
                $filePath,
                basename($filePath),
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            );

            $import = $this->service->uploadAndParse($upload, ['lista', 'parcelas', 'plano_interno']);
            $storedImportPath = $import->arquivo_path;

            $result['staging'] = $this->buildStagingSummary($import);

            $import = $this->service->confirmImport($import, 500);
            $after = $this->snapshotCounts();

            $result['import_id'] = $import->id;
            $result['database_delta'] = [
                'convenios' => $after['convenios'] - $before['convenios'],
                'parcelas' => $after['parcelas'] - $before['parcelas'],
                'planos_internos' => $after['planos_internos'] - $before['planos_internos'],
                'imports' => $after['imports'] - $before['imports'],
            ];
            $result['confirmacao'] = [
                'status' => $import->status,
                'processados' => (int) $import->total_processados,
                'pendencias' => (int) $import->total_pendencias,
                'issues' => (int) $import->total_issues,
                'resumo' => $import->resumo,
                'pendencias_por_motivo' => $import->pendingItems()
                    ->selectRaw('reason, COUNT(*) as total')
                    ->groupBy('reason')
                    ->orderByDesc('total')
                    ->pluck('total', 'reason')
                    ->map(fn ($count) => (int) $count)
                    ->all(),
                'pendencias_preview' => $import->pendingItems()
                    ->orderBy('source_sheet')
                    ->orderBy('source_row_number')
                    ->limit(10)
                    ->get(['source_sheet', 'source_row_number', 'reference_key', 'reason', 'payload'])
                    ->map(fn ($item) => $item->toArray())
                    ->all(),
            ];

            if ($commit) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (ValidationException $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $result['status'] = 'validation_error';
            $result['errors'] = $this->flattenValidationErrors($exception);
        } catch (\Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $result['status'] = 'error';
            $result['errors'] = [$exception->getMessage()];
        } finally {
            if (! $commit && $storedImportPath) {
                Storage::disk('private')->delete($storedImportPath);
            }
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function snapshotCounts(): array
    {
        return [
            'convenios' => Convenio::withTrashed()->count(),
            'parcelas' => Parcela::withTrashed()->count(),
            'planos_internos' => ConvenioPlanoInterno::count(),
            'imports' => ConvenioImport::count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStagingSummary(ConvenioImport $import): array
    {
        $import->load([
            'listaRows:id,import_id,row_number,normalized_data,status,issues',
            'parcelasRows:id,import_id,row_number,normalized_data,status,issues',
            'piRows:id,import_id,row_number,normalized_data,status,issues',
        ]);

        $listaRows = $import->listaRows;
        $parcelasRows = $import->parcelasRows;
        $piRows = $import->piRows;

        $listaByNumero = [];
        $listaByKey = [];
        $quantidadeInformada = [];
        $valorTotalInformado = [];

        foreach ($listaRows as $row) {
            $data = is_array($row->normalized_data) ? $row->normalized_data : [];
            $numero = trim((string) ($data['numero_convenio'] ?? ''));
            $orgao = trim((string) ($data['orgao'] ?? ''));

            if ($numero === '') {
                continue;
            }

            $listaByNumero[$numero] = ($listaByNumero[$numero] ?? 0) + 1;
            $key = strtoupper($orgao).'|'.$numero;
            $listaByKey[$key] = ($listaByKey[$key] ?? 0) + 1;

            if (isset($data['quantidade_parcelas']) && $data['quantidade_parcelas'] !== null) {
                $quantidadeInformada[$numero] = (int) $data['quantidade_parcelas'];
            }

            if (isset($data['valor_total']) && $data['valor_total'] !== null) {
                $valorTotalInformado[$numero] = (float) $data['valor_total'];
            }
        }

        $parcelasPorConvenio = [];
        $valorParcelasPorConvenio = [];
        $parcelasSemLista = [];
        foreach ($parcelasRows as $row) {
            $data = is_array($row->normalized_data) ? $row->normalized_data : [];
            $numero = trim((string) ($data['numero_convenio'] ?? ''));
            if ($numero === '') {
                continue;
            }

            $parcelasPorConvenio[$numero] = ($parcelasPorConvenio[$numero] ?? 0) + 1;
            $valorParcelasPorConvenio[$numero] = ($valorParcelasPorConvenio[$numero] ?? 0.0) + (float) ($data['valor_previsto'] ?? 0);

            if (! isset($listaByNumero[$numero])) {
                $parcelasSemLista[$numero] = true;
            }
        }

        $piPorConvenio = [];
        $piSemLista = [];
        foreach ($piRows as $row) {
            $data = is_array($row->normalized_data) ? $row->normalized_data : [];
            $numero = trim((string) ($data['numero_convenio'] ?? ''));
            $pi = trim((string) ($data['plano_interno'] ?? ''));
            if ($numero === '' || $pi === '') {
                continue;
            }

            $piPorConvenio[$numero][$pi] = true;
            if (! isset($listaByNumero[$numero])) {
                $piSemLista[$numero] = true;
            }
        }

        $divergenciasQuantidade = [];
        foreach ($quantidadeInformada as $numero => $quantidade) {
            $quantidadeParcelas = $parcelasPorConvenio[$numero] ?? 0;
            if ($quantidade !== $quantidadeParcelas) {
                $divergenciasQuantidade[] = [
                    'numero_convenio' => $numero,
                    'quantidade_informada' => $quantidade,
                    'parcelas_encontradas' => $quantidadeParcelas,
                ];
            }
        }

        $divergenciasValor = [];
        foreach ($valorTotalInformado as $numero => $valorTotal) {
            $valorParcelas = $valorParcelasPorConvenio[$numero] ?? 0.0;
            if (abs($valorTotal - $valorParcelas) > 0.01) {
                $divergenciasValor[] = [
                    'numero_convenio' => $numero,
                    'valor_total' => round($valorTotal, 2),
                    'soma_valor_previsto_parcelas' => round($valorParcelas, 2),
                ];
            }
        }

        return [
            'rows' => [
                'lista' => $listaRows->count(),
                'parcelas' => $parcelasRows->count(),
                'plano_interno' => $piRows->count(),
            ],
            'status_counts' => [
                'lista' => $listaRows->countBy('status')->all(),
                'parcelas' => $parcelasRows->countBy('status')->all(),
                'plano_interno' => $piRows->countBy('status')->all(),
            ],
            'lista_duplicate_numero_convenio' => array_slice($this->filterCountsGreaterThanOne($listaByNumero), 0, 20, true),
            'lista_duplicate_orgao_numero' => array_slice($this->filterCountsGreaterThanOne($listaByKey), 0, 20, true),
            'parcelas_sem_lista' => array_values(array_keys($parcelasSemLista)),
            'pi_sem_lista' => array_values(array_keys($piSemLista)),
            'convenios_com_multiplos_pi' => count(array_filter($piPorConvenio, fn (array $pis): bool => count($pis) > 1)),
            'divergencias_quantidade_parcelas' => array_slice($divergenciasQuantidade, 0, 20),
            'divergencias_valor_total_vs_parcelas' => array_slice($divergenciasValor, 0, 20),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function filterCountsGreaterThanOne(array $counts): array
    {
        $duplicates = array_filter($counts, fn (int $count): bool => $count > 1);
        arsort($duplicates);

        return $duplicates;
    }

    /**
     * @return array<int, string>
     */
    private function flattenValidationErrors(ValidationException $exception): array
    {
        $messages = [];
        foreach ($exception->errors() as $errors) {
            foreach ($errors as $message) {
                $messages[] = (string) $message;
            }
        }

        return $messages !== [] ? $messages : [$exception->getMessage()];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderHumanSummary(array $report): void
    {
        $rows = array_map(function (array $file): array {
            return [
                $file['file'] ?? '-',
                $file['status'] ?? '-',
                (string) data_get($file, 'confirmacao.processados', 0),
                (string) data_get($file, 'confirmacao.pendencias', 0),
                (string) data_get($file, 'confirmacao.issues', 0),
            ];
        }, $report['files'] ?? []);

        $this->table(['Arquivo', 'Status', 'Processados', 'Pendências', 'Issues'], $rows);

        foreach ($report['files'] ?? [] as $file) {
            if (($file['status'] ?? null) !== 'ok') {
                $this->warn(sprintf('%s: %s', $file['file'], implode(' | ', $file['errors'] ?? [])));
                continue;
            }

            $this->line(sprintf(
                '%s: pendências por motivo %s',
                $file['file'],
                json_encode(data_get($file, 'confirmacao.pendencias_por_motivo', []), JSON_UNESCAPED_UNICODE)
            ));
        }
    }
}
