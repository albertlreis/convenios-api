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
        Schema::create('municipio', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->foreignId('regiao_id')->nullable()->constrained('regiao_integracao');
            $table->string('nome');
            $table->string('uf', 2)->default('PA');
            $table->char('codigo_ibge', 7)->nullable();
            $table->unsignedInteger('codigo_tse')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_active')->virtualAs('IF(deleted_at IS NULL, 1, NULL)');

            $table->unique(['nome', 'uf', 'is_active'], 'municipio_nome_uf_unique_active');
            $table->unique(['codigo_ibge', 'uf', 'is_active'], 'municipio_ibge_uf_unique_active');
            $table->unique(['legacy_id', 'uf', 'is_active'], 'municipio_legacy_uf_unique_active');
            $table->unique(['codigo_tse', 'uf', 'is_active'], 'municipio_tse_uf_unique_active');

            $table->index('regiao_id');
            $table->index('codigo_ibge');
            $table->index('codigo_tse');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipio');
    }
};
