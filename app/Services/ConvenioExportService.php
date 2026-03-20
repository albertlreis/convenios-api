<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\Parcela;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ConvenioExportService
{
    /**
     * @param  Collection<int, Convenio>|array<int, Convenio>  $convenios
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $config
     */
    public function downloadConveniosList(Collection|array $convenios, array $context = [], array $config = []): BinaryFileResponse
    {
        $items = $convenios instanceof Collection ? $convenios->values() : collect($convenios)->values();
        $columns = $this->normalizeColumns($config['columns'] ?? []);
        $expandOpenParcelas = (bool) ($config['expand_open_parcelas'] ?? false);
        $openParcelasLimit = $expandOpenParcelas ? max(1, (int) ($config['open_parcelas_limit'] ?? 10)) : 0;

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Convenios');

        $columnStyles = $this->columnStylesForSelection($columns, $openParcelasLimit);
        $headers = array_column($columnStyles, 'label');
        $sheet->fromArray([$headers], null, 'A1');

        foreach ($items as $index => $convenio) {
            $row = $index + 2;
            $sheet->fromArray(
                [$this->rowForConvenio($convenio, $columns, $openParcelasLimit)],
                null,
                "A{$row}"
            );
        }

        $this->styleSheet($sheet, $columnStyles);

        return $this->downloadSpreadsheet($spreadsheet, 'convenios-filtrados.xlsx');
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function columnStylesForSelection(array $columns, int $openParcelasLimit): array
    {
        $columnStyles = [];
        $definitions = $this->columnDefinitions();

        foreach ($columns as $column) {
            $columnStyles[] = [
                'label' => $definitions[$column]['label'],
                'format' => $definitions[$column]['format'] ?? null,
                'width' => $definitions[$column]['width'] ?? null,
                'wrap' => $definitions[$column]['wrap'] ?? false,
            ];
        }

        for ($index = 1; $index <= $openParcelasLimit; $index++) {
            $columnStyles[] = [
                'label' => sprintf('Parcela %02d', $index),
                'format' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
                'width' => 16,
                'wrap' => false,
            ];
        }

        return $columnStyles;
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, scalar|null>
     */
    private function rowForConvenio(Convenio $convenio, array $columns, int $openParcelasLimit): array
    {
        $definitions = $this->columnDefinitions();
        $row = [];

        foreach ($columns as $column) {
            $row[] = $definitions[$column]['resolver']($convenio);
        }

        if ($openParcelasLimit > 0) {
            $parcelasEmAberto = $this->openParcelasForConvenio($convenio)
                ->filter(fn (Parcela $parcela) => $parcela->numero !== null && (int) $parcela->numero > 0)
                ->keyBy(fn (Parcela $parcela) => (int) $parcela->numero);

            for ($index = 1; $index <= $openParcelasLimit; $index++) {
                /** @var Parcela|null $parcela */
                $parcela = $parcelasEmAberto->get($index);
                $row[] = $parcela ? $this->parcelaOpenValue($parcela) : 0;
            }
        }

        return $row;
    }

    /**
     * @return array<string, array{
     *     label: string,
     *     resolver: \Closure(Convenio): scalar|null,
     *     format?: string|null,
     *     width?: float|int|null,
     *     wrap?: bool
     * }>
     */
    private function columnDefinitions(): array
    {
        return [
            'orgao' => [
                'label' => 'Órgão',
                'resolver' => fn (Convenio $convenio) => $convenio->orgao?->sigla ?: $convenio->orgao?->nome,
                'width' => 18,
            ],
            'municipio' => [
                'label' => 'Município',
                'resolver' => fn (Convenio $convenio) => $convenio->municipio?->nome,
                'width' => 24,
            ],
            'numero_convenio' => [
                'label' => 'Nº Conv',
                'resolver' => fn (Convenio $convenio) => $convenio->numero_convenio,
                'width' => 16,
            ],
            'objeto' => [
                'label' => 'Objeto',
                'resolver' => fn (Convenio $convenio) => $convenio->objeto,
                'width' => 60,
                'wrap' => true,
            ],
            'parcelas_total' => [
                'label' => 'Parcelas',
                'resolver' => fn (Convenio $convenio) => (int) ($convenio->parcelas_total ?? 0),
                'format' => '0',
                'width' => 12,
            ],
            'parcelas_pagas' => [
                'label' => 'Parcelas pagas',
                'resolver' => fn (Convenio $convenio) => (int) ($convenio->parcelas_pagas ?? 0),
                'format' => '0',
                'width' => 14,
            ],
            'valor_orgao' => [
                'label' => 'Valor total',
                'resolver' => fn (Convenio $convenio) => $this->valorOrgao($convenio),
                'format' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
                'width' => 16,
            ],
            'valor_contrapartida' => [
                'label' => 'Contrapartida',
                'resolver' => fn (Convenio $convenio) => $this->toFloat($convenio->valor_contrapartida ?? 0),
                'format' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
                'width' => 16,
            ],
            'valor_total' => [
                'label' => 'Valor global',
                'resolver' => fn (Convenio $convenio) => $this->valorGlobal($convenio),
                'format' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
                'width' => 16,
            ],
            'valor_pago' => [
                'label' => 'Total pago',
                'resolver' => fn (Convenio $convenio) => $this->toFloat($convenio->valor_pago_total ?? 0),
                'format' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
                'width' => 16,
            ],
            'valor_em_aberto' => [
                'label' => 'Total aberto',
                'resolver' => fn (Convenio $convenio) => $this->toFloat($convenio->valor_em_aberto_total ?? 0),
                'format' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
                'width' => 16,
            ],
            'planos_internos' => [
                'label' => 'PI',
                'resolver' => fn (Convenio $convenio) => $convenio->planosInternos->pluck('plano_interno')->implode(', '),
                'width' => 24,
            ],
            'situacao_financeira' => [
                'label' => 'Situação financeira',
                'resolver' => fn (Convenio $convenio) => ((int) ($convenio->parcelas_em_aberto ?? 0)) > 0 ? 'Com parcelas em aberto' : 'Encerrado',
                'width' => 20,
            ],
            'convenente_nome' => [
                'label' => 'Convenente',
                'resolver' => fn (Convenio $convenio) => $convenio->convenente_nome,
                'width' => 28,
            ],
        ];
    }

    /**
     * @param  array<int, string>|mixed  $columns
     * @return array<int, string>
     */
    private function normalizeColumns(mixed $columns): array
    {
        $availableColumns = array_keys($this->columnDefinitions());
        $requested = is_array($columns) ? $columns : [];

        $normalized = collect($requested)
            ->map(fn ($column) => trim((string) $column))
            ->filter(fn ($column) => $column !== '' && in_array($column, $availableColumns, true))
            ->unique()
            ->values()
            ->all();

        if ($normalized !== []) {
            return $normalized;
        }

        return [
            'orgao',
            'municipio',
            'numero_convenio',
            'objeto',
            'parcelas_total',
            'parcelas_pagas',
            'valor_orgao',
            'valor_pago',
            'valor_em_aberto',
        ];
    }

    /**
     * @return Collection<int, Parcela>
     */
    private function openParcelasForConvenio(Convenio $convenio): Collection
    {
        if ($convenio->relationLoaded('parcelas')) {
            return $convenio->parcelas
                ->filter(fn (Parcela $parcela) => $parcela->situacao === 'PREVISTA')
                ->sortBy('numero')
                ->values();
        }

        return $convenio->parcelas()
            ->emAberto()
            ->orderBy('numero')
            ->get();
    }

    private function toFloat(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function valorOrgao(Convenio $convenio): float
    {
        return $this->toFloat(
            $convenio->valor_total_calculado
            ?? $convenio->valor_total_informado
            ?? $convenio->valor_orgao
            ?? 0
        );
    }

    private function valorGlobal(Convenio $convenio): float
    {
        return $this->toFloat(
            $this->valorOrgao($convenio) + $this->toFloat($convenio->valor_contrapartida ?? 0)
        );
    }

    private function parcelaOpenValue(Parcela $parcela): float
    {
        $valorPrevisto = (float) ($parcela->valor_previsto ?? 0);
        $valorPago = (float) ($parcela->valor_pago ?? 0);

        return $this->toFloat(max(0, $valorPrevisto - $valorPago));
    }

    /**
     * @param  array<int, array{label: string, format?: string|null, width?: float|int|null, wrap?: bool}>  $columnStyles
     */
    private function styleSheet($sheet, array $columnStyles): void
    {
        $columnCount = count($columnStyles);
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);
        $highestRow = $sheet->getHighestRow();

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFEFF3F8');
        $sheet->getStyle("A1:{$lastColumn}{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $hasWrappedColumn = false;

        foreach ($columnStyles as $index => $columnStyle) {
            $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
            $dimension = $sheet->getColumnDimension($columnLetter);

            if (! empty($columnStyle['width'])) {
                $dimension->setAutoSize(false);
                $dimension->setWidth((float) $columnStyle['width']);
            } else {
                $dimension->setAutoSize(true);
            }

            if ($highestRow < 2) {
                continue;
            }

            if (! empty($columnStyle['format'])) {
                $sheet->getStyle("{$columnLetter}2:{$columnLetter}{$highestRow}")
                    ->getNumberFormat()
                    ->setFormatCode($columnStyle['format']);
            }

            if (! empty($columnStyle['wrap'])) {
                $sheet->getStyle("{$columnLetter}2:{$columnLetter}{$highestRow}")
                    ->getAlignment()
                    ->setWrapText(true);
                $hasWrappedColumn = true;
            }
        }

        if ($hasWrappedColumn && $highestRow >= 2) {
            for ($row = 2; $row <= $highestRow; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(-1);
            }
        }
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $fileName): BinaryFileResponse
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'convenios_xlsx_');
        if ($temporaryPath === false) {
            throw new \RuntimeException('Não foi possível criar arquivo temporário para exportação.');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($temporaryPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->download(
            $temporaryPath,
            $fileName,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        )->deleteFileAfterSend(true);
    }
}
