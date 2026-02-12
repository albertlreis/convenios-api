<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partido extends Model
{
    protected $table = 'partido';

    protected $guarded = [];

    public function mandatos(): HasMany
    {
        return $this->hasMany(MandatoPrefeito::class, 'partido_id');
    }
}
