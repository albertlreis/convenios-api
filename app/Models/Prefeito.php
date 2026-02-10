<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prefeito extends Model
{
    use SoftDeletes;

    protected $table = 'prefeito';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date:Y-m-d',
        ];
    }

    public function mandatos(): HasMany
    {
        return $this->hasMany(MandatoPrefeito::class, 'prefeito_id');
    }
}
