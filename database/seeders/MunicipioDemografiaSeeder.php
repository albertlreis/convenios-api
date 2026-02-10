<?php

namespace Database\Seeders;

use App\Models\Municipio;
use App\Models\MunicipioDemografia;
use Illuminate\Database\Seeder;

class MunicipioDemografiaSeeder extends Seeder
{
    public function run(): void
    {
        $demografias = require database_path('seeders/data/demografia_pa.php');

        $municipiosByIbge = Municipio::query()
            ->get(['id', 'codigo_ibge'])
            ->keyBy('codigo_ibge');

        foreach ($demografias as $demografia) {
            $municipio = $municipiosByIbge->get($demografia['codigo_ibge']);

            if (! $municipio) {
                continue;
            }

            MunicipioDemografia::query()->updateOrCreate(
                [
                    'municipio_id' => $municipio->id,
                    'ano_ref' => $demografia['ano_ref'],
                ],
                [
                    'populacao' => $demografia['populacao'],
                    'eleitores' => $demografia['eleitores'],
                ]
            );
        }
    }
}
