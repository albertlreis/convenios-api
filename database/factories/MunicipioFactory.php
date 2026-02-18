<?php

namespace Database\Factories;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Municipio>
 */
class MunicipioFactory extends Factory
{
    protected $model = Municipio::class;

    public function definition(): array
    {
        return [
            'regiao_id' => null,
            'nome' => $this->faker->unique()->city(),
            'uf' => 'PA',
            'codigo_ibge' => $this->faker->unique()->numerify('1######'),
            'codigo_tse' => null,
        ];
    }
}
