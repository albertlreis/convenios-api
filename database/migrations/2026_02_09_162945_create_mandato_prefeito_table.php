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
        Schema::create('mandato_prefeito', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->unsignedBigInteger('municipio_id');
            $table->unsignedBigInteger('prefeito_id');
            $table->unsignedBigInteger('partido_id')->nullable();
            $table->smallInteger('ano_eleicao');
            $table->integer('cd_eleicao');
            $table->date('dt_eleicao');
            $table->tinyInteger('nr_turno');
            $table->integer('nr_candidato')->nullable();
            $table->date('mandato_inicio');
            $table->date('mandato_fim');
            $table->tinyInteger('mandato_consecutivo')->nullable();
            $table->boolean('reeleito')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['municipio_id', 'ano_eleicao', 'dt_eleicao', 'nr_turno'], 'uq_mandato_eleicao');
            $table->index(['municipio_id', 'mandato_inicio', 'mandato_fim'], 'idx_mandato_municipio');
            $table->index(['prefeito_id', 'mandato_inicio'], 'idx_mandato_prefeito');
            $table->index('partido_id', 'fk_mandato_partido');
            $table->foreign('municipio_id', 'fk_mandato_municipio')->references('id')->on('municipio')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('partido_id', 'fk_mandato_partido')->references('id')->on('partido')->restrictOnUpdate()->restrictOnDelete();
            $table->foreign('prefeito_id', 'fk_mandato_prefeito')->references('id')->on('prefeito')->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mandato_prefeito');
    }
};
