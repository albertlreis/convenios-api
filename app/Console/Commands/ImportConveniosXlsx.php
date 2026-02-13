<?php

namespace App\Console\Commands;

use App\Models\Convenio;
use App\Models\ConvenioPlanoInterno;
use App\Models\Municipio;
use App\Models\Orgao;
use App\Models\Parcela;
use App\Support\NormalizeParcelaStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class ImportConveniosXlsx extends Command
{
    protected $signature = 'import:convenios-xlsx {--file=storage/app/imports/Convênios Consolidado.xlsx}';

    protected $description = 'Importa convênios iniciais de um arquivo XLSX';

    public function handle(): int
    {
        $relativePath = (string) $this->option('file');
        $absolutePath = base_path($relativePath);

        if (! is_file($absolutePath)) {
            $this->error("Arquivo não encontrado: {$absolutePath}");

            return self::FAILURE;
        }

        $sheets = Excel::toArray([], $absolutePath);

        $stats = [
            'convenios_criados' => 0,
            'convenios_atualizados' => 0,
            'parcelas_criadas' => 0,
            'parcelas_atualizadas' => 0,
        ];

        DB::transaction(function () use ($sheets, $relativePath, &$stats): void {
            foreach ($sheets as $sheetIndex => $rows) {
                if (count($rows) < 2) {
                    continue;
                }

                $headers = array_map([$this, 'normalizeHeader'], array_map(fn ($value) => (string) $value, $rows[0]));
                $sheetName = 'sheet_'.($sheetIndex + 1);

                foreach (array_slice($rows, 1, null, true) as $rowNumber => $rowValues) {
                    if ($this->rowIsEmpty($rowValues)) {
                        continue;
                    }

                    $row = $this->rowToAssoc($headers, $rowValues);

                    $codigo = $this->firstValue($row, ['codigo', 'convenio_codigo']);
                    $numeroConvenio = $this->firstValue($row, ['numero_convenio', 'n_convenio', 'convenio_numero']);

                    if ($codigo === null && $numeroConvenio === null) {
                        continue;
                    }

                    $orgao = $this->resolveOrgao($row);
                    $municipioBeneficiario = $this->resolveMunicipioBeneficiario($row);
                    $municipioConvenente = $this->resolveMunicipioConvenente($row);

                    $convenio = $this->resolveConvenio($codigo, $numeroConvenio, $orgao?->id);
                    $isNovoConvenio = ! $convenio->exists;

                    $convenio->fill([
                        'orgao_id' => $orgao?->id,
                        'numero_convenio' => $numeroConvenio,
                        'codigo' => $codigo,
                        'municipio_beneficiario_id' => $municipioBeneficiario?->id,
                        'convenente_nome' => $this->firstValue($row, ['convenente_nome', 'convenente']),
                        'convenente_municipio_id' => $municipioConvenente?->id,
                        'objeto' => $this->firstValue($row, ['objeto']),
                        'grupo_despesa' => $this->firstValue($row, ['grupo_despesa']),
                        'data_inicio' => $this->parseDate($this->firstValue($row, ['data_inicio', 'inicio', 'dt_inicio'])),
                        'data_fim' => $this->parseDate($this->firstValue($row, ['data_fim', 'fim', 'dt_fim'])),
                        'valor_orgao' => $this->parseDecimal($this->firstValue($row, ['valor_orgao'])),
                        'valor_contrapartida' => $this->parseDecimal($this->firstValue($row, ['valor_contrapartida'])),
                        'valor_aditivo' => $this->parseDecimal($this->firstValue($row, ['valor_aditivo'])),
                        'valor_total_informado' => $this->parseDecimal($this->firstValue($row, ['valor_total_informado', 'valor_total'])),
                        'valor_total_calculado' => $this->parseDecimal($this->firstValue($row, ['valor_total_calculado'])),
                    ]);

                    $convenio->metadata = array_merge(
                        is_array($convenio->metadata) ? $convenio->metadata : [],
                        [
                            'source_file' => $relativePath,
                            'sheet' => $sheetName,
                            'row' => $rowNumber + 1,
                        ]
                    );

                    $convenio->save();

                    $planoInterno = $this->parsePlanoInterno($this->firstValue($row, ['plano_interno', 'pi']));
                    if ($planoInterno !== null) {
                        ConvenioPlanoInterno::query()->updateOrCreate(
                            [
                                'convenio_id' => $convenio->id,
                                'plano_interno' => strtoupper($planoInterno),
                            ],
                            [
                                'origem' => 'import_command',
                            ]
                        );
                    }

                    $stats[$isNovoConvenio ? 'convenios_criados' : 'convenios_atualizados']++;

                    $numeroParcela = $this->parseInteger($this->firstValue($row, ['parcela_numero', 'numero_parcela', 'parcela']));

                    if ($numeroParcela !== null) {
                        $statusBruto = $this->firstValue($row, ['parcela_situacao', 'situacao']);
                        $statusNormalizado = NormalizeParcelaStatus::normalize($statusBruto);
                        $statusClassificacao = NormalizeParcelaStatus::classify($statusBruto);

                        $parcela = Parcela::query()->firstOrNew([
                            'convenio_id' => $convenio->id,
                            'numero' => $numeroParcela,
                        ]);

                        $isNovaParcela = ! $parcela->exists;

                        $parcela->fill([
                            'valor_previsto' => $this->parseDecimal($this->firstValue($row, ['parcela_valor_previsto', 'valor_previsto'])),
                            'valor_pago' => $this->parseDecimal($this->firstValue($row, ['parcela_valor_pago', 'valor_pago'])),
                            'data_pagamento' => $this->parseDate($this->firstValue($row, ['parcela_data_pagamento', 'data_pagamento'])),
                            'nota_empenho' => $this->firstValue($row, ['nota_empenho', 'numero_ne', 'ne']),
                            'data_ne' => $this->parseDate($this->firstValue($row, ['data_ne', 'data_nota_empenho'])),
                            'valor_empenhado' => $this->parseDecimal($this->firstValue($row, ['valor_empenhado'])),
                            'situacao' => $this->parseSituacaoParcela($statusBruto),
                            'observacoes' => $this->firstValue($row, ['parcela_observacoes', 'observacoes']),
                            'dados_origem' => [
                                'source_file' => $relativePath,
                                'sheet' => $sheetName,
                                'row' => $rowNumber + 1,
                                'raw_data' => $row,
                                'status_bruto' => $statusBruto,
                                'status_normalizado' => $statusNormalizado,
                                'status_classificacao' => $statusClassificacao,
                            ],
                        ]);

                        $parcela->save();

                        $stats[$isNovaParcela ? 'parcelas_criadas' : 'parcelas_atualizadas']++;
                    }
                }
            }
        });

        $this->info('Importação concluída com sucesso.');
        $this->table(['Métrica', 'Quantidade'], [
            ['Convênios criados', $stats['convenios_criados']],
            ['Convênios atualizados', $stats['convenios_atualizados']],
            ['Parcelas criadas', $stats['parcelas_criadas']],
            ['Parcelas atualizadas', $stats['parcelas_atualizadas']],
        ]);

        return self::SUCCESS;
    }

    private function resolveOrgao(array $row): ?Orgao
    {
        $sigla = $this->firstValue($row, ['orgao_sigla', 'sigla_orgao', 'sigla']);

        if ($sigla === null) {
            return null;
        }

        return Orgao::query()->firstOrCreate(
            ['sigla' => mb_strtoupper($sigla)],
            ['nome' => $this->firstValue($row, ['orgao_nome', 'nome_orgao']) ?? mb_strtoupper($sigla)]
        );
    }

    private function resolveMunicipioBeneficiario(array $row): ?Municipio
    {
        return $this->resolveMunicipio(
            $this->firstValue($row, ['municipio_beneficiario_codigo_ibge', 'codigo_ibge', 'ibge']),
            $this->firstValue($row, ['municipio_beneficiario', 'municipio_nome', 'municipio']),
            $this->firstValue($row, ['municipio_beneficiario_uf', 'uf']) ?? 'PA'
        );
    }

    private function resolveMunicipioConvenente(array $row): ?Municipio
    {
        return $this->resolveMunicipio(
            $this->firstValue($row, ['convenente_codigo_ibge']),
            $this->firstValue($row, ['convenente_municipio', 'convenente_nome_municipio']),
            $this->firstValue($row, ['convenente_uf', 'uf']) ?? 'PA'
        );
    }

    private function resolveMunicipio(?string $codigoIbge, ?string $nomeMunicipio, string $uf): ?Municipio
    {
        if ($codigoIbge !== null) {
            $codigoIbge = preg_replace('/\D/', '', $codigoIbge) ?? '';
            $codigoIbge = str_pad($codigoIbge, 7, '0', STR_PAD_LEFT);

            $municipio = Municipio::query()
                ->where('codigo_ibge', $codigoIbge)
                ->where('uf', strtoupper($uf))
                ->first();

            if ($municipio) {
                return $municipio;
            }
        }

        if ($nomeMunicipio === null) {
            return null;
        }

        return Municipio::query()
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($nomeMunicipio)])
            ->where('uf', strtoupper($uf))
            ->first();
    }

    private function resolveConvenio(?string $codigo, ?string $numeroConvenio, ?int $orgaoId): Convenio
    {
        if ($codigo !== null) {
            $convenio = Convenio::query()->where('codigo', $codigo)->first();
            if ($convenio) {
                return $convenio;
            }
        }

        if ($numeroConvenio !== null) {
            $query = Convenio::query()->where('numero_convenio', $numeroConvenio);

            if ($orgaoId !== null) {
                $query->where('orgao_id', $orgaoId);
            }

            $convenio = $query->first();
            if ($convenio) {
                return $convenio;
            }
        }

        return new Convenio;
    }

    private function rowToAssoc(array $headers, array $values): array
    {
        $assoc = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $assoc[$header] = $values[$index] ?? null;
        }

        return $assoc;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(string $header): string
    {
        return (string) Str::of($header)
            ->replaceMatches('/[^\pL\pN]+/u', '_')
            ->lower()
            ->ascii()
            ->trim('_');
    }

    private function firstValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];

            if ($value === null) {
                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDecimal(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(['.', ','], ['', '.'], preg_replace('/[^\d,.-]/', '', $value) ?? '');

        if (! is_numeric($normalized)) {
            return null;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    private function parseInteger(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function parsePlanoInterno(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function parseSituacaoParcela(?string $value): string
    {
        return NormalizeParcelaStatus::toParcelaSituacao($value);
    }
}
