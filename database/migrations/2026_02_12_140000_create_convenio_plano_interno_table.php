<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convenio_plano_interno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('convenio_id')->constrained('convenio')->cascadeOnDelete();
            $table->string('plano_interno', 32);
            $table->string('origem', 30)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['convenio_id', 'plano_interno'], 'convenio_pi_unique');
            $table->index('plano_interno', 'convenio_pi_plano_interno_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convenio_plano_interno');
    }
};

