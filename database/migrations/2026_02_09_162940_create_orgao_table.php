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
        Schema::create('orgao', function (Blueprint $table) {
            $table->id();
            $table->string('sigla', 20);
            $table->string('nome');
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_active')->virtualAs('IF(deleted_at IS NULL, 1, NULL)');

            $table->unique(['sigla', 'is_active'], 'orgao_sigla_unique_active');
            $table->index('sigla');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orgao');
    }
};
