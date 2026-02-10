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
        Schema::create('partido', function (Blueprint $table) {
            $table->id();
            $table->string('sigla', 20);
            $table->string('nome');
            $table->unsignedSmallInteger('numero')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_active')->virtualAs('IF(deleted_at IS NULL, 1, NULL)');

            $table->unique(['sigla', 'is_active'], 'partido_sigla_unique_active');
            $table->unique(['numero', 'is_active'], 'partido_numero_unique_active');
            $table->index('sigla');
            $table->index('numero');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partido');
    }
};
