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
        Schema::create('regiao_integracao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->string('descricao');
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_active')->virtualAs('IF(deleted_at IS NULL, 1, NULL)');

            $table->unique('descricao');
            $table->unique(['legacy_id', 'is_active'], 'regiao_integracao_legacy_unique_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regiao_integracao');
    }
};
