<?php

namespace Database\Factories;

use App\Models\Convenio;
use App\Models\Municipio;
use App\Models\Orgao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Convenio>
 */
class ConvenioFactory extends Factory
{
    protected $model = Convenio::class;

    public function definition(): array
    {
        $sigla = strtoupper($this->faker->lexify('??'));

        return [
            'orgao_id' => Orgao::factory(),
            'numero_convenio' => $this->faker->bothify('CV-####/####'),
            'codigo' => sprintf(
                '%s:%s/%d',
                $sigla,
                $this->faker->numerify('###'),
                $this->faker->numberBetween(2020, 2032)
            ),
            'municipio_beneficiario_id' => Municipio::factory(),
            'convenente_nome' => $this->faker->company(),
            'convenente_municipio_id' => Municipio::factory(),
            'plano_interno' => strtoupper($this->faker->bothify('??#######??')),
            'objeto' => $this->faker->sentence(8),
            'grupo_despesa' => $this->faker->randomElement(['CUSTEIO', 'CAPITAL']),
            'data_inicio' => $this->faker->date(),
            'data_fim' => $this->faker->date(),
            'valor_orgao' => $this->faker->randomFloat(2, 1000, 500000),
            'valor_contrapartida' => $this->faker->randomFloat(2, 0, 100000),
            'valor_aditivo' => $this->faker->randomFloat(2, 0, 50000),
            'valor_total_informado' => $this->faker->randomFloat(2, 1000, 600000),
            'valor_total_calculado' => $this->faker->randomFloat(2, 1000, 600000),
            'metadata' => ['factory' => true],
        ];
    }
}
