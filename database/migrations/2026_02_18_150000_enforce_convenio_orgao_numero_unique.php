<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convenio', function (Blueprint $table): void {
            if ($this->hasIndex('convenio', 'convenio_numero_convenio_unique_active')) {
                $table->dropUnique('convenio_numero_convenio_unique_active');
            }
            if (! $this->hasIndex('convenio', 'convenio_orgao_numero_unique_active')) {
                $table->unique(['orgao_id', 'numero_convenio', 'is_active'], 'convenio_orgao_numero_unique_active');
            }
            if (! $this->hasIndex('convenio', 'convenio_orgao_numero_index')) {
                $table->index(['orgao_id', 'numero_convenio'], 'convenio_orgao_numero_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('convenio', function (Blueprint $table): void {
            if ($this->hasIndex('convenio', 'convenio_orgao_numero_unique_active')) {
                $table->dropUnique('convenio_orgao_numero_unique_active');
            }
            if ($this->hasIndex('convenio', 'convenio_orgao_numero_index')) {
                $table->dropIndex('convenio_orgao_numero_index');
            }
            if (! $this->hasIndex('convenio', 'convenio_numero_convenio_unique_active')) {
                $table->unique(['numero_convenio', 'is_active'], 'convenio_numero_convenio_unique_active');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(*) as total FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return ((int) ($result->total ?? 0)) > 0;
    }
};
