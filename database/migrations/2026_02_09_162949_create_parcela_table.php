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
        Schema::create('parcela', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convenio_id')->constrained('convenio')->cascadeOnDelete();
            $table->integer('numero');
            $table->decimal('valor_previsto', 15, 2)->nullable();
            $table->decimal('valor_pago', 15, 2)->nullable();
            $table->date('data_pagamento')->nullable();
            $table->string('nota_empenho', 50)->nullable();
            $table->date('data_ne')->nullable();
            $table->decimal('valor_empenhado', 15, 2)->nullable();
            $table->enum('situacao', ['PREVISTA', 'PAGA', 'CANCELADA'])->default('PREVISTA');
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_active')->virtualAs('IF(deleted_at IS NULL, 1, NULL)');

            $table->unique(['convenio_id', 'numero', 'is_active'], 'parcela_convenio_numero_unique_active');
            $table->index('convenio_id');
            $table->index(['convenio_id', 'situacao', 'data_pagamento'], 'parcela_convenio_situacao_data_index');
            $table->index('situacao');
            $table->index('nota_empenho');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcela');
    }
};
