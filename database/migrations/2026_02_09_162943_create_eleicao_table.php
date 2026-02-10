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
        Schema::create('eleicao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipio');
            $table->unsignedSmallInteger('ano_eleicao');
            $table->date('dt_eleicao');
            $table->unsignedTinyInteger('turno')->default(1);
            $table->string('cd_eleicao')->nullable();
            $table->string('cargo')->default('PREFEITO');
            $table->string('tipo')->default('ORDINARIA');
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_active')->virtualAs('IF(deleted_at IS NULL, 1, NULL)');

            $table->unique(
                ['municipio_id', 'ano_eleicao', 'dt_eleicao', 'turno', 'cargo', 'is_active'],
                'eleicao_municipio_ano_data_turno_cargo_unique_active'
            );

            $table->index('ano_eleicao');
            $table->index(['municipio_id', 'ano_eleicao']);
            $table->index(['municipio_id', 'dt_eleicao']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eleicao');
    }
};
