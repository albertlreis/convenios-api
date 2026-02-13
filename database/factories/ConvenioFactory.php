<?php

namespace Database\Factories;

use App\Models\Convenio;
use App\Models\ConvenioPlanoInterno;
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
        return [
            'orgao_id' => Orgao::factory(),
            'numero_convenio' => $this->faker->bothify('CV-####/####'),
            'municipio_id' => Municipio::factory(),
            'convenente_nome' => $this->faker->company(),
            'objeto' => $this->faker->sentence(8),
            'grupo_despesa' => $this->faker->randomElement(['CUSTEIO', 'CAPITAL']),
            'data_inicio' => $this->faker->date(),
            'data_fim' => $this->faker->date(),
            'valor_orgao' => $this->faker->randomFloat(2, 1000, 500000),
            'valor_contrapartida' => $this->faker->randomFloat(2, 0, 100000),
            'valor_aditivo' => $this->faker->randomFloat(2, 0, 50000),
            'valor_total_informado' => $this->faker->randomFloat(2, 1000, 600000),
            'valor_total_calculado' => $this->faker->randomFloat(2, 1000, 600000),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Convenio $convenio): void {
            ConvenioPlanoInterno::query()->create([
                'convenio_id' => $convenio->id,
                'plano_interno' => strtoupper($this->faker->bothify('??#######??')),
            ]);
        });
    }
}
