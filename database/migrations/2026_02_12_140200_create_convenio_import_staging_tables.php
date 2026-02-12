<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenio_imports', function (Blueprint $table) {
            $table->id();
            $table->string('arquivo_nome');
            $table->string('arquivo_path');
            $table->string('status', 30)->default('uploaded');
            $table->unsignedInteger('total_lista_rows')->default(0);
            $table->unsignedInteger('total_parcelas_rows')->default(0);
            $table->unsignedInteger('total_pi_rows')->default(0);
            $table->unsignedInteger('total_issues')->default(0);
            $table->unsignedInteger('total_processados')->default(0);
            $table->unsignedInteger('total_pendencias')->default(0);
            $table->json('resumo')->nullable();
            $table->timestamp('confirmado_em')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('convenio_import_lista_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('convenio_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('normalized_data')->nullable();
            $table->string('status', 30)->default('parsed');
            $table->json('issues')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['import_id', 'row_number'], 'convenio_import_lista_rows_unique');
        });

        Schema::create('convenio_import_parcelas_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('convenio_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('normalized_data')->nullable();
            $table->string('status', 30)->default('parsed');
            $table->json('issues')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['import_id', 'row_number'], 'convenio_import_parcelas_rows_unique');
        });

        Schema::create('convenio_import_pi_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('convenio_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_data');
            $table->json('normalized_data')->nullable();
            $table->string('status', 30)->default('parsed');
            $table->json('issues')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['import_id', 'row_number'], 'convenio_import_pi_rows_unique');
        });

        Schema::create('convenio_import_pending_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('convenio_imports')->cascadeOnDelete();
            $table->string('source_sheet', 30);
            $table->unsignedBigInteger('source_row_id')->nullable();
            $table->unsignedInteger('source_row_number')->nullable();
            $table->string('reference_key')->nullable();
            $table->string('reason');
            $table->json('payload')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['import_id', 'source_sheet'], 'convenio_import_pending_sheet_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenio_import_pending_items');
        Schema::dropIfExists('convenio_import_pi_rows');
        Schema::dropIfExists('convenio_import_parcelas_rows');
        Schema::dropIfExists('convenio_import_lista_rows');
        Schema::dropIfExists('convenio_imports');
    }
};

