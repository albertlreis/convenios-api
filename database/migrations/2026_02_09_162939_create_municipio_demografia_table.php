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
        Schema::create('demografia_municipio', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->id();
            $table->unsignedBigInteger('municipio_id');
            $table->unsignedSmallInteger('ano_ref');
            $table->integer('populacao');
            $table->integer('eleitores')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['municipio_id', 'ano_ref'], 'uq_demografia_municipio_ano');
            $table->index('ano_ref', 'idx_demografia_ano');
            $table->foreign('municipio_id', 'fk_demografia_municipio')->references('id')->on('municipio')->restrictOnUpdate()->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `demografia_municipio` MODIFY `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demografia_municipio');
    }
};
