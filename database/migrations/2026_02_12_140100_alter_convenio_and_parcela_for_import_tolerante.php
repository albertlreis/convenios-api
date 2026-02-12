<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convenio', function (Blueprint $table) {
            $table->string('orgao_nome_informado', 255)->nullable()->after('orgao_id');
            $table->string('municipio_beneficiario_nome_informado', 255)->nullable()->after('municipio_beneficiario_id');
            $table->string('convenente_municipio_nome_informado', 255)->nullable()->after('convenente_municipio_id');
            $table->unsignedSmallInteger('ano_referencia')->nullable()->after('numero_convenio');
            $table->unsignedInteger('quantidade_parcelas_informada')->nullable()->after('grupo_despesa');
            $table->json('dados_origem')->nullable()->after('metadata');
        });

        DB::table('convenio')
            ->select(['id', 'plano_interno'])
            ->whereNotNull('plano_interno')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                $payload = [];
                $now = now();

                foreach ($rows as $row) {
                    $payload[] = [
                        'convenio_id' => $row->id,
                        'plano_interno' => trim((string) $row->plano_interno),
                        'origem' => 'legacy_convenio',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($payload !== []) {
                    DB::table('convenio_plano_interno')->insertOrIgnore($payload);
                }
            }, 'id');

        Schema::table('convenio', function (Blueprint $table) {
            $table->dropIndex('convenio_plano_interno_index');
            $table->dropIndex('convenio_orgao_municipio_pi_index');
            $table->dropColumn('plano_interno');
            $table->index(['orgao_id', 'municipio_beneficiario_id'], 'convenio_orgao_municipio_index');
        });

        Schema::table('parcela', function (Blueprint $table) {
            $table->string('convenio_numero_informado', 255)->nullable()->after('convenio_id');
            $table->json('dados_origem')->nullable()->after('observacoes');
        });
    }

    public function down(): void
    {
        Schema::table('convenio', function (Blueprint $table) {
            $table->string('plano_interno', 11)->nullable()->after('convenente_municipio_id');
        });

        DB::statement(
            "UPDATE convenio c
             LEFT JOIN (
                 SELECT convenio_id, MIN(plano_interno) AS plano_interno
                 FROM convenio_plano_interno
                 GROUP BY convenio_id
             ) cp ON cp.convenio_id = c.id
             SET c.plano_interno = cp.plano_interno"
        );

        Schema::table('convenio', function (Blueprint $table) {
            $table->dropIndex('convenio_orgao_municipio_index');
            $table->dropColumn([
                'orgao_nome_informado',
                'municipio_beneficiario_nome_informado',
                'convenente_municipio_nome_informado',
                'ano_referencia',
                'quantidade_parcelas_informada',
                'dados_origem',
            ]);
            $table->index('plano_interno');
            $table->index(['orgao_id', 'municipio_beneficiario_id', 'plano_interno'], 'convenio_orgao_municipio_pi_index');
        });

        Schema::table('parcela', function (Blueprint $table) {
            $table->dropColumn(['convenio_numero_informado', 'dados_origem']);
        });
    }
};

