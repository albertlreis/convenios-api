<?php

namespace Database\Factories;

use App\Models\Convenio;
use App\Models\Parcela;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Parcela>
 */
class ParcelaFactory extends Factory
{
    protected $model = Parcela::class;

    public function definition(): array
    {
        $valorPrevisto = $this->faker->randomFloat(2, 1000, 50000);

        return [
            'convenio_id' => Convenio::factory(),
            'numero' => $this->faker->numberBetween(1, 12),
            'valor_previsto' => $valorPrevisto,
            'valor_pago' => null,
            'data_pagamento' => null,
            'nota_empenho' => $this->faker->bothify('NE-#####'),
            'data_ne' => $this->faker->date(),
            'valor_empenhado' => $valorPrevisto,
            'situacao' => 'PREVISTA',
            'observacoes' => null,
        ];
    }
}
