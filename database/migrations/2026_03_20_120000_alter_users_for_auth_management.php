<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('force_password_change')->default(false)->after('is_active');
            $table->softDeletes()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['is_active', 'force_password_change']);
        });
    }
};
