<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrgaoSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('orgao')->upsert([
            [
                'id' => 1,
                'sigla' => 'SESPA',
                'nome' => 'Secretaria de Estado de Saúde Pública',
                'codigo_sigplan' => 2,
            ],
            [
                'id' => 2,
                'sigla' => 'SEOP',
                'nome' => 'Secretaria de Estado de Obras Públicas',
                'codigo_sigplan' => 7,
            ],
            [
                'id' => 3,
                'sigla' => 'SEINFRA',
                'nome' => 'Secretaria de Estado de Infraestrutura e Logística',
                'codigo_sigplan' => 53,
            ],
            [
                'id' => 4,
                'sigla' => 'SEDUC',
                'nome' => 'Secretaria de Estado de Educação',
                'codigo_sigplan' => 20,
            ],
        ], ['id'], ['sigla', 'nome', 'codigo_sigplan']);
    }
}
