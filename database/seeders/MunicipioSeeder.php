<?php

namespace Database\Seeders;

use App\Models\Municipio;
use Illuminate\Database\Seeder;

class MunicipioSeeder extends Seeder
{
    public function run(): void
    {
        $municipios = require database_path('seeders/data/municipios_pa.php');

        foreach ($municipios as $municipio) {
            Municipio::query()->updateOrCreate(
                [
                    'codigo_ibge' => $municipio['codigo_ibge'],
                    'uf' => $municipio['uf'],
                ],
                [
                    'legacy_id' => $municipio['legacy_id'],
                    'regiao_id' => $municipio['regiao_id'],
                    'nome' => $municipio['nome'],
                    'codigo_tse' => $municipio['codigo_tse'],
                ]
            );
        }
    }
}
