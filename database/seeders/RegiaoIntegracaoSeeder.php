<?php

namespace Database\Seeders;

use App\Models\RegiaoIntegracao;
use Illuminate\Database\Seeder;

class RegiaoIntegracaoSeeder extends Seeder
{
    public function run(): void
    {
        $regioes = require database_path('seeders/data/regioes_pa.php');

        foreach ($regioes as $regiao) {
            RegiaoIntegracao::query()->updateOrCreate(
                ['descricao' => $regiao['descricao']],
                ['legacy_id' => $regiao['legacy_id']]
            );
        }
    }
}
