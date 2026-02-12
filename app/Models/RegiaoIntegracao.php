<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegiaoIntegracao extends Model
{
    protected $table = 'regiao_integracao';

    protected $guarded = [];

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class, 'regiao_id');
    }
}
