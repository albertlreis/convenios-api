<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $sqlPath = database_path('seeders/data/05_users.sql');

        DB::unprepared(file_get_contents($sqlPath));
    }
}
