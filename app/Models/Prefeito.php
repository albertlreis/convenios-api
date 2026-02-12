<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prefeito extends Model
{
    protected $table = 'prefeito';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dt_nascimento' => 'date:Y-m-d',
        ];
    }

    public function mandatos(): HasMany
    {
        return $this->hasMany(MandatoPrefeito::class, 'prefeito_id');
    }
}
