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
        Schema::create('prefeito', function (Blueprint $table) {
            $table->id();
            $table->string('nome_completo');
            $table->string('nome_urna')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('cpf_hash')->nullable();
            $table->string('chave');
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_active')->virtualAs('IF(deleted_at IS NULL, 1, NULL)');

            $table->unique(['chave', 'is_active'], 'prefeito_chave_unique_active');
            $table->index('nome_completo');
            $table->index('nome_urna');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prefeito');
    }
};
