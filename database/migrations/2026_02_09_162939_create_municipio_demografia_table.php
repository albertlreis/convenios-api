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
        Schema::create('municipio_demografia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipio');
            $table->unsignedSmallInteger('ano_ref');
            $table->unsignedBigInteger('populacao')->default(0);
            $table->unsignedBigInteger('eleitores')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('is_active')->virtualAs('IF(deleted_at IS NULL, 1, NULL)');

            $table->unique(['municipio_id', 'ano_ref', 'is_active'], 'municipio_demografia_unique_active');
            $table->index('ano_ref');
            $table->index('municipio_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('municipio_demografia');
    }
};
