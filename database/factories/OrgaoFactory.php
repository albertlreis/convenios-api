<?php

namespace Database\Factories;

use App\Models\Orgao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Orgao>
 */
class OrgaoFactory extends Factory
{
    protected $model = Orgao::class;

    public function definition(): array
    {
        return [
            'sigla' => strtoupper($this->faker->unique()->bothify('??##')),
            'nome' => 'Orgao '.$this->faker->company(),
        ];
    }
}
