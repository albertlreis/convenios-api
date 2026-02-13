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
            if (! Schema::hasColumn('convenio', 'municipio_id')) {
                $table->foreignId('municipio_id')->nullable()->after('numero_convenio');
            }
        });

        DB::statement('UPDATE convenio SET municipio_id = COALESCE(municipio_beneficiario_id, convenente_municipio_id) WHERE municipio_id IS NULL');

        Schema::table('convenio', function (Blueprint $table): void {
            if (Schema::hasColumn('convenio', 'municipio_beneficiario_id')) {
                $table->dropForeign(['municipio_beneficiario_id']);
            }
            if (Schema::hasColumn('convenio', 'convenente_municipio_id')) {
                $table->dropForeign(['convenente_municipio_id']);
            }

            if (Schema::hasColumn('convenio', 'municipio_id')) {
                $table->foreign('municipio_id')->references('id')->on('municipio');
                $table->index('municipio_id');
            }

            $this->dropIndexIfExists('convenio', 'convenio_codigo_unique_active');
            $this->dropIndexIfExists('convenio', 'convenio_orgao_municipio_pi_index');
            $this->dropIndexIfExists('convenio', 'convenio_orgao_municipio_index');

            $dropColumns = array_values(array_filter([
                Schema::hasColumn('convenio', 'codigo') ? 'codigo' : null,
                Schema::hasColumn('convenio', 'municipio_beneficiario_id') ? 'municipio_beneficiario_id' : null,
                Schema::hasColumn('convenio', 'municipio_beneficiario_nome_informado') ? 'municipio_beneficiario_nome_informado' : null,
                Schema::hasColumn('convenio', 'convenente_municipio_id') ? 'convenente_municipio_id' : null,
                Schema::hasColumn('convenio', 'convenente_municipio_nome_informado') ? 'convenente_municipio_nome_informado' : null,
                Schema::hasColumn('convenio', 'quantidade_parcelas_informada') ? 'quantidade_parcelas_informada' : null,
                Schema::hasColumn('convenio', 'metadata') ? 'metadata' : null,
                Schema::hasColumn('convenio', 'orgao_nome_informado') ? 'orgao_nome_informado' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('convenio', function (Blueprint $table): void {
            if (Schema::hasColumn('convenio', 'numero_convenio')) {
                $table->unique(['numero_convenio', 'is_active'], 'convenio_numero_convenio_unique_active');
            }

            if (Schema::hasColumn('convenio', 'orgao_id') && Schema::hasColumn('convenio', 'municipio_id') && Schema::hasColumn('convenio', 'plano_interno')) {
                $table->index(['orgao_id', 'municipio_id', 'plano_interno'], 'convenio_orgao_municipio_pi_index_v2');
            }
        });

        Schema::table('parcela', function (Blueprint $table): void {
            if (Schema::hasColumn('parcela', 'convenio_numero_informado')) {
                $table->dropColumn('convenio_numero_informado');
            }
        });

        Schema::table('convenio_plano_interno', function (Blueprint $table): void {
            if (Schema::hasColumn('convenio_plano_interno', 'origem')) {
                $table->dropColumn('origem');
            }
        });

        Schema::table('partido', function (Blueprint $table): void {
            if (Schema::hasColumn('partido', 'legacy_id')) {
                $table->dropColumn('legacy_id');
            }
        });

        Schema::table('municipio', function (Blueprint $table): void {
            if (Schema::hasColumn('municipio', 'legacy_id')) {
                $table->dropColumn('legacy_id');
            }
        });

        Schema::table('mandato_prefeito', function (Blueprint $table): void {
            if (Schema::hasColumn('mandato_prefeito', 'legacy_id')) {
                $table->dropColumn('legacy_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('convenio', function (Blueprint $table): void {
            $this->dropIndexIfExists('convenio', 'convenio_numero_convenio_unique_active');
            $this->dropIndexIfExists('convenio', 'convenio_orgao_municipio_pi_index_v2');
            $this->dropIndexIfExists('convenio', 'convenio_municipio_id_index');

            if (Schema::hasColumn('convenio', 'municipio_id')) {
                $table->dropForeign(['municipio_id']);
                $table->dropColumn('municipio_id');
            }

            if (! Schema::hasColumn('convenio', 'codigo')) {
                $table->string('codigo', 32)->nullable()->after('numero_convenio');
            }
            if (! Schema::hasColumn('convenio', 'municipio_beneficiario_id')) {
                $table->foreignId('municipio_beneficiario_id')->nullable()->after('codigo')->constrained('municipio');
            }
            if (! Schema::hasColumn('convenio', 'municipio_beneficiario_nome_informado')) {
                $table->string('municipio_beneficiario_nome_informado', 255)->nullable()->after('municipio_beneficiario_id');
            }
            if (! Schema::hasColumn('convenio', 'convenente_municipio_id')) {
                $table->foreignId('convenente_municipio_id')->nullable()->after('convenente_nome')->constrained('municipio');
            }
            if (! Schema::hasColumn('convenio', 'convenente_municipio_nome_informado')) {
                $table->string('convenente_municipio_nome_informado', 255)->nullable()->after('convenente_municipio_id');
            }
            if (! Schema::hasColumn('convenio', 'quantidade_parcelas_informada')) {
                $table->unsignedInteger('quantidade_parcelas_informada')->nullable()->after('grupo_despesa');
            }
            if (! Schema::hasColumn('convenio', 'metadata')) {
                $table->json('metadata')->nullable()->after('valor_total_calculado');
            }
            if (! Schema::hasColumn('convenio', 'orgao_nome_informado')) {
                $table->string('orgao_nome_informado', 255)->nullable()->after('orgao_id');
            }
        });

        Schema::table('parcela', function (Blueprint $table): void {
            if (! Schema::hasColumn('parcela', 'convenio_numero_informado')) {
                $table->string('convenio_numero_informado', 255)->nullable()->after('convenio_id');
            }
        });

        Schema::table('convenio_plano_interno', function (Blueprint $table): void {
            if (! Schema::hasColumn('convenio_plano_interno', 'origem')) {
                $table->string('origem', 30)->nullable()->after('plano_interno');
            }
        });

        Schema::table('partido', function (Blueprint $table): void {
            if (! Schema::hasColumn('partido', 'legacy_id')) {
                $table->unsignedBigInteger('legacy_id')->nullable()->after('id');
            }
        });

        Schema::table('municipio', function (Blueprint $table): void {
            if (! Schema::hasColumn('municipio', 'legacy_id')) {
                $table->unsignedBigInteger('legacy_id')->nullable()->after('id');
            }
        });

        Schema::table('mandato_prefeito', function (Blueprint $table): void {
            if (! Schema::hasColumn('mandato_prefeito', 'legacy_id')) {
                $table->unsignedBigInteger('legacy_id')->nullable()->after('id');
            }
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $database = DB::getDatabaseName();
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();

        if ($exists) {
            DB::statement(sprintf('DROP INDEX `%s` ON `%s`', $indexName, $table));
        }
    }
};
