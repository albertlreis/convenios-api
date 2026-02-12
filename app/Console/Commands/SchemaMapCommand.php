<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SchemaMapCommand extends Command
{
    protected $signature = 'schema:map
        {--output-json=docs/schema-map.json : Caminho do arquivo JSON}
        {--output-md=docs/schema-map.md : Caminho do arquivo Markdown}';

    protected $description = 'Gera um mapa do schema atual a partir do information_schema';

    public function handle(): int
    {
        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();

        if (! is_string($databaseName) || $databaseName === '') {
            $this->error('Nao foi possivel identificar o nome do banco de dados da conexao atual.');

            return self::FAILURE;
        }

        $tables = array_values(array_filter(array_map(
            static function (object $row): string {
                $values = array_values((array) $row);

                return (string) ($values[0] ?? '');
            },
            $connection->select(
                <<<'SQL'
                SELECT table_name
                FROM information_schema.tables
                WHERE table_schema = ?
                  AND table_type = 'BASE TABLE'
                ORDER BY table_name
                SQL,
                [$databaseName]
            )
        )));

        $schemaMap = [
            'connection' => $connection->getName(),
            'database' => $databaseName,
            'generated_at' => now()->toIso8601String(),
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $schemaMap['tables'][$table] = [
                'columns' => $this->columnsForTable($databaseName, $table),
                'indexes' => $this->indexesForTable($databaseName, $table),
                'foreign_keys' => $this->foreignKeysForTable($databaseName, $table),
            ];
        }

        $jsonPath = base_path((string) $this->option('output-json'));
        $mdPath = base_path((string) $this->option('output-md'));

        File::ensureDirectoryExists(dirname($jsonPath));
        File::ensureDirectoryExists(dirname($mdPath));

        File::put($jsonPath, json_encode($schemaMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
        File::put($mdPath, $this->toMarkdown($schemaMap));

        $this->info("Schema map JSON gerado em: {$jsonPath}");
        $this->info("Schema map Markdown gerado em: {$mdPath}");

        return self::SUCCESS;
    }

    private function columnsForTable(string $databaseName, string $table): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT
                column_name,
                column_type,
                data_type,
                is_nullable,
                column_default,
                extra
            FROM information_schema.columns
            WHERE table_schema = ?
              AND table_name = ?
            ORDER BY ordinal_position
            SQL,
            [$databaseName, $table]
        );

        return array_map(function (object $row): array {
            return [
                'name' => (string) $this->value($row, 'column_name'),
                'column_type' => (string) $this->value($row, 'column_type'),
                'data_type' => (string) $this->value($row, 'data_type'),
                'nullable' => (string) $this->value($row, 'is_nullable') === 'YES',
                'default' => $this->value($row, 'column_default'),
                'extra' => (string) $this->value($row, 'extra'),
            ];
        }, $rows);
    }

    private function indexesForTable(string $databaseName, string $table): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT
                index_name,
                non_unique,
                seq_in_index,
                column_name,
                index_type
            FROM information_schema.statistics
            WHERE table_schema = ?
              AND table_name = ?
            ORDER BY index_name, seq_in_index
            SQL,
            [$databaseName, $table]
        );

        $indexes = [];
        foreach ($rows as $row) {
            $name = (string) $this->value($row, 'index_name');

            if (! isset($indexes[$name])) {
                $indexes[$name] = [
                    'name' => $name,
                    'unique' => (int) $this->value($row, 'non_unique') === 0,
                    'type' => $this->value($row, 'index_type'),
                    'columns' => [],
                ];
            }

            $indexes[$name]['columns'][] = $this->value($row, 'column_name');
        }

        return array_values($indexes);
    }

    private function foreignKeysForTable(string $databaseName, string $table): array
    {
        $rows = DB::select(
            <<<'SQL'
            SELECT
                k.constraint_name,
                k.column_name,
                k.referenced_table_name,
                k.referenced_column_name,
                r.update_rule,
                r.delete_rule
            FROM information_schema.key_column_usage k
            JOIN information_schema.referential_constraints r
              ON r.constraint_schema = k.constraint_schema
             AND r.constraint_name = k.constraint_name
            WHERE k.table_schema = ?
              AND k.table_name = ?
              AND k.referenced_table_name IS NOT NULL
            ORDER BY k.constraint_name, k.ordinal_position
            SQL,
            [$databaseName, $table]
        );

        $foreignKeys = [];
        foreach ($rows as $row) {
            $name = (string) $this->value($row, 'constraint_name');

            if (! isset($foreignKeys[$name])) {
                $foreignKeys[$name] = [
                    'name' => $name,
                    'columns' => [],
                    'referenced_table' => $this->value($row, 'referenced_table_name'),
                    'referenced_columns' => [],
                    'on_update' => $this->value($row, 'update_rule'),
                    'on_delete' => $this->value($row, 'delete_rule'),
                ];
            }

            $foreignKeys[$name]['columns'][] = $this->value($row, 'column_name');
            $foreignKeys[$name]['referenced_columns'][] = $this->value($row, 'referenced_column_name');
        }

        return array_values($foreignKeys);
    }

    private function toMarkdown(array $schemaMap): string
    {
        $lines = [];
        $lines[] = '# Schema Map';
        $lines[] = '';
        $lines[] = '- Connection: `'.$schemaMap['connection'].'`';
        $lines[] = '- Database: `'.$schemaMap['database'].'`';
        $lines[] = '- Generated at: `'.$schemaMap['generated_at'].'`';
        $lines[] = '';

        foreach ($schemaMap['tables'] as $table => $tableData) {
            $lines[] = '## `'.$table.'`';
            $lines[] = '';
            $lines[] = '### Columns';
            $lines[] = '';
            $lines[] = '| Column | Type | Nullable | Default | Extra |';
            $lines[] = '| --- | --- | --- | --- | --- |';

            foreach ($tableData['columns'] as $column) {
                $lines[] = sprintf(
                    '| `%s` | `%s` | `%s` | `%s` | `%s` |',
                    $column['name'],
                    $column['column_type'],
                    $column['nullable'] ? 'YES' : 'NO',
                    $column['default'] === null ? 'NULL' : str_replace('|', '\|', (string) $column['default']),
                    $column['extra'] === '' ? '-' : str_replace('|', '\|', (string) $column['extra']),
                );
            }

            $lines[] = '';
            $lines[] = '### Indexes';
            $lines[] = '';

            if (count($tableData['indexes']) === 0) {
                $lines[] = '- _none_';
            } else {
                foreach ($tableData['indexes'] as $index) {
                    $lines[] = sprintf(
                        '- `%s` (%s, %s): `%s`',
                        $index['name'],
                        $index['unique'] ? 'UNIQUE' : 'NON-UNIQUE',
                        $index['type'],
                        implode('`, `', $index['columns']),
                    );
                }
            }

            $lines[] = '';
            $lines[] = '### Foreign Keys';
            $lines[] = '';

            if (count($tableData['foreign_keys']) === 0) {
                $lines[] = '- _none_';
            } else {
                foreach ($tableData['foreign_keys'] as $foreignKey) {
                    $lines[] = sprintf(
                        '- `%s`: (`%s`) -> `%s`(`%s`) [ON UPDATE %s, ON DELETE %s]',
                        $foreignKey['name'],
                        implode('`, `', $foreignKey['columns']),
                        $foreignKey['referenced_table'],
                        implode('`, `', $foreignKey['referenced_columns']),
                        $foreignKey['on_update'],
                        $foreignKey['on_delete'],
                    );
                }
            }

            $lines[] = '';
        }

        return implode(PHP_EOL, $lines).PHP_EOL;
    }

    private function value(object $row, string $key): mixed
    {
        $needle = strtolower($key);

        foreach ((array) $row as $candidate => $value) {
            if (strtolower((string) $candidate) === $needle) {
                return $value;
            }
        }

        return null;
    }
}
