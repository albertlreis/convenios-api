<?php

namespace App\Services;

use App\Models\Convenio;
use App\Models\Parcela;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
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
        $openParcelasLimit = $expandOpenParcelas ? max(1, (int) ($config['open_parcelas_limit'] ?? 1)) : 0;

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Convenios');

        $headers = $this->headersForColumns($columns, $openParcelasLimit);
        $sheet->fromArray([$headers], null, 'A1');

        foreach ($items as $index => $convenio) {
            $row = $index + 2;
            $sheet->fromArray(
                [$this->rowForConvenio($convenio, $columns, $openParcelasLimit)],
                null,
                "A{$row}"
            );
        }

        $this->styleSheet($sheet, count($headers));

        if ($context !== []) {
            $filtersSheet = $spreadsheet->createSheet();
            $filtersSheet->setTitle('Filtros');
            $filtersSheet->fromArray([['Filtro', 'Valor']], null, 'A1');
            $row = 2;
            foreach ($context as $label => $value) {
                $filtersSheet->setCellValue("A{$row}", $label);
                $filtersSheet->setCellValue("B{$row}", is_array($value) ? implode(', ', $value) : (string) $value);
                $row++;
            }
            $this->styleSheet($filtersSheet, 2);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'convenios-filtrados.xlsx');
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    private function headersForColumns(array $columns, int $openParcelasLimit): array
    {
        $headers = [];
        foreach ($columns as $column) {
            $headers[] = $this->columnDefinitions()[$column]['label'];
        }

        for ($index = 1; $index <= $openParcelasLimit; $index++) {
            $headers[] = "Parcela em aberto {$index} - número";
            $headers[] = "Parcela em aberto {$index} - valor previsto";
            $headers[] = "Parcela em aberto {$index} - valor pago";
            $headers[] = "Parcela em aberto {$index} - data pagamento";
            $headers[] = "Parcela em aberto {$index} - situação";
            $headers[] = "Parcela em aberto {$index} - observações";
        }

        return $headers;
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
            $parcelasEmAberto = $this->openParcelasForConvenio($convenio)->take($openParcelasLimit)->values();

            for ($index = 0; $index < $openParcelasLimit; $index++) {
                /** @var Parcela|null $parcela */
                $parcela = $parcelasEmAberto->get($index);
                $row[] = $parcela?->numero;
                $row[] = $parcela ? $this->decimal($parcela->valor_previsto) : null;
                $row[] = $parcela ? $this->decimal($parcela->valor_pago) : null;
                $row[] = optional($parcela?->data_pagamento)->format('Y-m-d');
                $row[] = $parcela?->situacao;
                $row[] = $parcela?->observacoes;
            }
        }

        return $row;
    }

    /**
     * @return array<string, array{label: string, resolver: \Closure(Convenio): scalar|null}>
     */
    private function columnDefinitions(): array
    {
        return [
            'orgao' => [
                'label' => 'Órgão',
                'resolver' => fn (Convenio $convenio) => $convenio->orgao?->sigla ?: $convenio->orgao?->nome,
            ],
            'municipio' => [
                'label' => 'Município',
                'resolver' => fn (Convenio $convenio) => $convenio->municipio?->nome,
            ],
            'numero_convenio' => [
                'label' => 'Nº Conv',
                'resolver' => fn (Convenio $convenio) => $convenio->numero_convenio,
            ],
            'objeto' => [
                'label' => 'Objeto',
                'resolver' => fn (Convenio $convenio) => $convenio->objeto,
            ],
            'parcelas_total' => [
                'label' => 'Parcelas',
                'resolver' => fn (Convenio $convenio) => (int) ($convenio->parcelas_total ?? 0),
            ],
            'parcelas_pagas' => [
                'label' => 'Parcelas pagas',
                'resolver' => fn (Convenio $convenio) => (int) ($convenio->parcelas_pagas ?? 0),
            ],
            'valor_total' => [
                'label' => 'Total convênio',
                'resolver' => fn (Convenio $convenio) => $this->decimal($convenio->valor_total_calculado ?? $convenio->valor_total_informado ?? 0),
            ],
            'valor_pago' => [
                'label' => 'Total pago',
                'resolver' => fn (Convenio $convenio) => $this->decimal($convenio->valor_pago_total ?? 0),
            ],
            'valor_em_aberto' => [
                'label' => 'Total aberto',
                'resolver' => fn (Convenio $convenio) => $this->decimal($convenio->valor_em_aberto_total ?? 0),
            ],
            'planos_internos' => [
                'label' => 'PI',
                'resolver' => fn (Convenio $convenio) => $convenio->planosInternos->pluck('plano_interno')->implode(', '),
            ],
            'situacao_financeira' => [
                'label' => 'Situação financeira',
                'resolver' => fn (Convenio $convenio) => ((int) ($convenio->parcelas_em_aberto ?? 0)) > 0 ? 'Com parcelas em aberto' : 'Encerrado',
            ],
            'convenente_nome' => [
                'label' => 'Convenente',
                'resolver' => fn (Convenio $convenio) => $convenio->convenente_nome,
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
            'valor_total',
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

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function styleSheet($sheet, int $columnCount): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($columnCount);

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFEFF3F8');

        for ($index = 1; $index <= $columnCount; $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
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
