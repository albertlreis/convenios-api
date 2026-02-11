<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('municipio', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->unsignedBigInteger('regiao_id')->nullable();
            $table->string('nome', 120);
            $table->char('uf', 2)->default('PA');
            $table->char('codigo_ibge', 7)->nullable();
            $table->integer('codigo_tse')->nullable();
            $table->integer('codigo_sigplan')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['nome', 'uf'], 'uq_municipio_nome_uf');
            $table->unique(['codigo_ibge', 'uf'], 'uq_municipio_ibge_uf');
            $table->unique(['codigo_tse', 'uf'], 'uq_municipio_tse_uf');
            $table->index('nome', 'idx_municipio_nome');
            $table->index('codigo_ibge', 'idx_municipio_ibge');
            $table->index('regiao_id', 'idx_municipio_regiao');
            $table->foreign('regiao_id', 'fk_municipio_regiao')->references('id')->on('regiao_integracao')->restrictOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `municipio` MODIFY `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipio');
    }
};
