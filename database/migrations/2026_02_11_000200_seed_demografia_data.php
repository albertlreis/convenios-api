<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statement = $this->parseFirstInsertStatement(database_path('seeders/data/03_demografia_pa_2026.sql'));

        foreach (array_chunk($statement['rows'], 500) as $chunk) {
            DB::table($statement['table'])->upsert($chunk, ['municipio_id', 'ano_ref'], ['populacao', 'eleitores']);
        }
    }

    public function down(): void
    {
        $statement = $this->parseFirstInsertStatement(database_path('seeders/data/03_demografia_pa_2026.sql'));

        $pairs = [];
        foreach ($statement['rows'] as $row) {
            $pairs[] = [
                'municipio_id' => $row['municipio_id'],
                'ano_ref' => $row['ano_ref'],
            ];
        }

        foreach (array_chunk($pairs, 500) as $chunk) {
            DB::table('demografia_municipio')
                ->where(function ($query) use ($chunk) {
                    foreach ($chunk as $pair) {
                        $query->orWhere(function ($sub) use ($pair) {
                            $sub->where('municipio_id', $pair['municipio_id'])
                                ->where('ano_ref', $pair['ano_ref']);
                        });
                    }
                })
                ->delete();
        }
    }

    private function parseFirstInsertStatement(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException("Unable to read SQL file: {$path}");
        }

        if (! preg_match('/INSERT INTO `([^`]+)` \(([^)]+)\)\s+VALUES\s*(.+?)(?:\s+ON DUPLICATE KEY UPDATE.+?)?;/is', $sql, $match)) {
            throw new RuntimeException("No INSERT statement found in SQL file: {$path}");
        }

        $columns = array_map(
            static function (string $column) {
                return trim($column, " `\t\n\r\0\x0B");
            },
            explode(',', $match[2])
        );

        $rows = [];
        foreach ($this->extractTuples($match[3]) as $tuple) {
            $values = $this->splitTupleValues($tuple);
            $rows[] = array_combine($columns, $values);
        }

        return [
            'table' => $match[1],
            'rows' => $rows,
        ];
    }

    private function extractTuples(string $valuesBlock): array
    {
        $tuples = [];
        $inString = false;
        $escape = false;
        $depth = 0;
        $buffer = '';

        $length = strlen($valuesBlock);
        for ($i = 0; $i < $length; $i++) {
            $char = $valuesBlock[$i];

            if ($escape) {
                if ($depth > 0) {
                    $buffer .= $char;
                }
                $escape = false;
                continue;
            }

            if ($char === '\\' && $inString) {
                if ($depth > 0) {
                    $buffer .= $char;
                }
                $escape = true;
                continue;
            }

            if ($char === "'") {
                $inString = ! $inString;
                if ($depth > 0) {
                    $buffer .= $char;
                }
                continue;
            }

            if (! $inString && $char === '(') {
                $depth++;
                if ($depth === 1) {
                    $buffer = '';
                    continue;
                }
            }

            if (! $inString && $char === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = $buffer;
                    $buffer = '';
                    continue;
                }
            }

            if ($depth > 0) {
                $buffer .= $char;
            }
        }

        return $tuples;
    }

    private function splitTupleValues(string $tuple): array
    {
        $values = [];
        $inString = false;
        $escape = false;
        $buffer = '';

        $length = strlen($tuple);
        for ($i = 0; $i < $length; $i++) {
            $char = $tuple[$i];

            if ($escape) {
                $buffer .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\' && $inString) {
                $buffer .= $char;
                $escape = true;
                continue;
            }

            if ($char === "'") {
                $inString = ! $inString;
                $buffer .= $char;
                continue;
            }

            if (! $inString && $char === ',') {
                $values[] = $this->normalizeSqlValue($buffer);
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $values[] = $this->normalizeSqlValue($buffer);

        return $values;
    }

    private function normalizeSqlValue(string $value)
    {
        $value = trim($value);
        if (strtoupper($value) === 'NULL') {
            return null;
        }

        if ($this->startsWith($value, "'") && $this->endsWith($value, "'")) {
            $inner = substr($value, 1, -1);
            $inner = str_replace(["\\'", "\\\\"], ["'", "\\"], $inner);
            return $inner;
        }

        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        return $value;
    }

    private function startsWith(string $value, string $prefix): bool
    {
        return substr($value, 0, strlen($prefix)) === $prefix;
    }

    private function endsWith(string $value, string $suffix): bool
    {
        if ($suffix === '') {
            return true;
        }

        return substr($value, -strlen($suffix)) === $suffix;
    }
};
