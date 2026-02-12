<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('convenio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orgao_id')->nullable()->constrained('orgao');
            $table->string('numero_convenio')->nullable();
            $table->string('codigo', 32)->nullable();
            $table->foreignId('municipio_beneficiario_id')->nullable()->constrained('municipio');
            $table->string('convenente_nome')->nullable();
            $table->foreignId('convenente_municipio_id')->nullable()->constrained('municipio');
            $table->string('plano_interno', 11)->nullable();
            $table->text('objeto')->nullable();
            $table->string('grupo_despesa')->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->decimal('valor_orgao', 15, 2)->nullable();
            $table->decimal('valor_contrapartida', 15, 2)->nullable();
            $table->decimal('valor_aditivo', 15, 2)->nullable();
            $table->decimal('valor_total_informado', 15, 2)->nullable();
            $table->decimal('valor_total_calculado', 15, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->softDeletes();
            $table->boolean('is_active')->virtualAs('IF(deleted_at IS NULL, 1, NULL)');

            $table->unique(['codigo', 'is_active'], 'convenio_codigo_unique_active');
            $table->index('orgao_id');
            $table->index('municipio_beneficiario_id');
            $table->index('plano_interno');
            $table->index('data_inicio');
            $table->index('data_fim');
            $table->index(['orgao_id', 'municipio_beneficiario_id', 'plano_interno'], 'convenio_orgao_municipio_pi_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convenio');
    }
};
