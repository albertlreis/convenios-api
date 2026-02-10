<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partido extends Model
{
    use SoftDeletes;

    protected $table = 'partido';

    protected $guarded = [];

    public function mandatos(): HasMany
    {
        return $this->hasMany(MandatoPrefeito::class, 'partido_id');
    }
}
