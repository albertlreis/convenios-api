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
        Schema::create('demografia_municipio', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->unsignedBigInteger('municipio_id');
            $table->smallInteger('ano_ref');
            $table->integer('populacao');
            $table->integer('eleitores')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['municipio_id', 'ano_ref'], 'uq_demografia_municipio_ano');
            $table->index('ano_ref', 'idx_demografia_ano');
            $table->foreign('municipio_id', 'fk_demografia_municipio')->references('id')->on('municipio')->restrictOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demografia_municipio');
    }
};
