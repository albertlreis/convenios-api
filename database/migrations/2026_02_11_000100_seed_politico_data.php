<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $statements = $this->parseInsertStatements(database_path('seeders/data/02_seeds_politico.sql'));

        $uniqueBy = [
            'regiao_integracao' => ['id'],
            'municipio' => ['id'],
            'partido' => ['id'],
            'prefeito' => ['id'],
            'mandato_prefeito' => ['id'],
        ];

        foreach ($statements as $statement) {
            $table = $statement['table'];
            $columns = $statement['columns'];
            $rows = $statement['rows'];

            $updateColumns = array_values(array_diff($columns, $uniqueBy[$table]));

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($table)->upsert($chunk, $uniqueBy[$table], $updateColumns);
            }
        }
    }

    public function down(): void
    {
        $statements = $this->parseInsertStatements(database_path('seeders/data/02_seeds_politico.sql'));

        $idsByTable = [];
        foreach ($statements as $statement) {
            $idsByTable[$statement['table']] = array_values(array_filter(array_map(
                static function (array $row) {
                    return isset($row['id']) ? $row['id'] : null;
                },
                $statement['rows']
            )));
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        foreach (['mandato_prefeito', 'prefeito', 'partido', 'municipio', 'regiao_integracao'] as $table) {
            if (! isset($idsByTable[$table])) {
                continue;
            }

            foreach (array_chunk($idsByTable[$table], 500) as $chunk) {
                DB::table($table)->whereIn('id', $chunk)->delete();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function parseInsertStatements(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException("Unable to read SQL file: {$path}");
        }

        preg_match_all('/INSERT INTO `([^`]+)` \(([^)]+)\)\s+VALUES\s*(.+?)(?:\s+ON DUPLICATE KEY UPDATE.+?)?;/is', $sql, $matches, PREG_SET_ORDER);

        $statements = [];
        foreach ($matches as $match) {
            $table = $match[1];
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

            $statements[] = [
                'table' => $table,
                'columns' => $columns,
                'rows' => $rows,
            ];
        }

        return $statements;
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
