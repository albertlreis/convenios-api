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
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipio');
            $table->foreignId('prefeito_id')->constrained('prefeito');
            $table->foreignId('partido_id')->nullable()->constrained('partido');
            $table->foreignId('eleicao_id')->nullable()->constrained('eleicao');
            $table->date('inicio');
            $table->date('fim');
            $table->unsignedTinyInteger('mandato_consecutivo')->nullable();
            $table->boolean('reeleito')->nullable();
            $table->enum('situacao', ['EM_EXERCICIO', 'AFASTADO', 'CASSADO', 'INTERINO', 'ENCERRADO']);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['municipio_id', 'inicio', 'fim']);
            $table->index(['municipio_id', 'inicio', 'fim', 'situacao'], 'mandato_prefeito_municipio_periodo_situacao_index');
            $table->index(['prefeito_id', 'inicio', 'fim']);
            $table->index('partido_id');
            $table->index('eleicao_id');
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
