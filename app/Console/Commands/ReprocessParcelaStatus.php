<?php

namespace App\Console\Commands;

use App\Models\ConvenioImportParcelaRow;
use App\Models\Parcela;
use App\Support\NormalizeParcelaStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ReprocessParcelaStatus extends Command
{
    protected $signature = 'parcelas:reprocessar-status
        {--import-id=* : IDs de importacao de convenio}
        {--from= : Data inicial (YYYY-MM-DD) para filtrar parcelas por created_at}
        {--to= : Data final (YYYY-MM-DD) para filtrar parcelas por created_at}
        {--chunk=500 : Tamanho do lote}
        {--apply : Aplica alteracoes (sem essa flag, executa em dry-run)}';

    protected $description = 'Reprocessa o status das parcelas importadas usando o status bruto da origem.';

    /** @var array<string, array<string, mixed>|null> */
    private array $stagingCache = [];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $chunkSize = max((int) $this->option('chunk'), 1);
        $importIds = array_values(array_filter(array_map('intval', (array) $this->option('import-id'))));
        $fromDate = $this->parseDateOption((string) $this->option('from'));
        $toDate = $this->parseDateOption((string) $this->option('to'));

        $query = Parcela::query()
            ->withTrashed()
            ->whereNotNull('dados_origem')
            ->orderBy('id');

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $stats = [
            'avaliadas' => 0,
            'com_fonte_status' => 0,
            'sem_fonte_status' => 0,
            'pagas' => 0,
            'em_aberto' => 0,
            'desconhecidas' => 0,
            'alteradas' => 0,
            'inalteradas' => 0,
            'ignorada_filtro_import' => 0,
            'desconhecidos_top' => [],
        ];

        $query->chunkById($chunkSize, function ($parcelas) use ($apply, $importIds, &$stats): void {
            foreach ($parcelas as $parcela) {
                $stats['avaliadas']++;

                $dadosOrigem = is_array($parcela->dados_origem) ? $parcela->dados_origem : [];
                $importId = isset($dadosOrigem['import_id']) ? (int) $dadosOrigem['import_id'] : null;

                if ($importIds !== [] && ($importId === null || ! in_array($importId, $importIds, true))) {
                    $stats['ignorada_filtro_import']++;
                    continue;
                }

                $rawStatus = $this->extractRawStatus($dadosOrigem);
                if ($rawStatus === null) {
                    $rawStatus = $this->extractStatusFromStaging($importId, isset($dadosOrigem['row_number']) ? (int) $dadosOrigem['row_number'] : null);
                }

                if ($rawStatus === null) {
                    $stats['sem_fonte_status']++;
                    continue;
                }

                $stats['com_fonte_status']++;

                $classificacao = NormalizeParcelaStatus::classify($rawStatus);
                $situacaoRecalculada = NormalizeParcelaStatus::toParcelaSituacao($rawStatus);
                $statusNormalizado = NormalizeParcelaStatus::normalize($rawStatus);

                if ($classificacao === NormalizeParcelaStatus::PAGA) {
                    $stats['pagas']++;
                } elseif ($classificacao === NormalizeParcelaStatus::EM_ABERTO) {
                    $stats['em_aberto']++;
                } else {
                    $stats['desconhecidas']++;
                    $key = trim($rawStatus) !== '' ? trim($rawStatus) : '(vazio)';
                    $stats['desconhecidos_top'][$key] = ($stats['desconhecidos_top'][$key] ?? 0) + 1;
                }

                if ($parcela->situacao === $situacaoRecalculada) {
                    $stats['inalteradas']++;
                    continue;
                }

                $stats['alteradas']++;
                if (! $apply) {
                    continue;
                }

                $dadosOrigem['status_bruto'] = $rawStatus;
                $dadosOrigem['status_normalizado'] = $statusNormalizado;
                $dadosOrigem['status_classificacao'] = $classificacao;

                $parcela->situacao = $situacaoRecalculada;
                $parcela->dados_origem = $dadosOrigem;
                $parcela->save();
            }
        }, 'id');

        $this->line($apply ? 'Modo APPLY (alteracoes persistidas).' : 'Modo DRY-RUN (nenhuma alteracao foi persistida).');
        $this->table(['Metrica', 'Quantidade'], [
            ['Parcelas avaliadas', $stats['avaliadas']],
            ['Ignoradas por filtro de import_id', $stats['ignorada_filtro_import']],
            ['Com fonte de status', $stats['com_fonte_status']],
            ['Sem fonte de status', $stats['sem_fonte_status']],
            ['Classificadas como pagas', $stats['pagas']],
            ['Classificadas como em aberto', $stats['em_aberto']],
            ['Classificadas como desconhecidas', $stats['desconhecidas']],
            ['Parcelas alteradas', $stats['alteradas']],
            ['Parcelas inalteradas', $stats['inalteradas']],
        ]);

        $topUnknown = $this->topUnknownStatuses($stats['desconhecidos_top']);
        if ($topUnknown !== []) {
            $this->table(['Status bruto desconhecido', 'Ocorrencias'], $topUnknown);
        }

        return self::SUCCESS;
    }

    private function parseDateOption(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    /**
     * @param  array<string, mixed>  $dadosOrigem
     */
    private function extractRawStatus(array $dadosOrigem): ?string
    {
        $statusBruto = Arr::get($dadosOrigem, 'status_bruto');
        if (is_string($statusBruto) && trim($statusBruto) !== '') {
            return trim($statusBruto);
        }

        $rawData = Arr::get($dadosOrigem, 'raw_data');
        if (is_array($rawData)) {
            foreach ($rawData as $key => $value) {
                if ($this->normalizeHeaderKey((string) $key) !== 'situacao') {
                    continue;
                }

                $stringValue = trim((string) $value);
                return $stringValue !== '' ? $stringValue : null;
            }
        }

        $fromNormalizedData = Arr::get($dadosOrigem, 'normalized_data.situacao');
        if (is_string($fromNormalizedData) && trim($fromNormalizedData) !== '') {
            return trim($fromNormalizedData);
        }

        return null;
    }

    private function extractStatusFromStaging(?int $importId, ?int $rowNumber): ?string
    {
        if (! $importId || ! $rowNumber) {
            return null;
        }

        $cacheKey = "{$importId}:{$rowNumber}";
        if (! array_key_exists($cacheKey, $this->stagingCache)) {
            $this->stagingCache[$cacheKey] = ConvenioImportParcelaRow::query()
                ->where('import_id', $importId)
                ->where('row_number', $rowNumber)
                ->first(['raw_data', 'normalized_data'])
                ?->toArray();
        }

        $row = $this->stagingCache[$cacheKey];
        if (! is_array($row)) {
            return null;
        }

        $fromRaw = $this->extractRawStatus($row);
        if ($fromRaw !== null) {
            return $fromRaw;
        }

        return null;
    }

    private function normalizeHeaderKey(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replace([' ', '-', '.'], '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();
    }

    /**
     * @param  array<string, int>  $unknownCounters
     * @return array<int, array{string, int}>
     */
    private function topUnknownStatuses(array $unknownCounters): array
    {
        arsort($unknownCounters);

        $rows = [];
        foreach (array_slice($unknownCounters, 0, 20, true) as $status => $count) {
            $rows[] = [$status, $count];
        }

        return $rows;
    }
}
