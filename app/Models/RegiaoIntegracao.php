<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegiaoIntegracao extends Model
{
    use SoftDeletes;

    protected $table = 'regiao_integracao';

    protected $guarded = [];

    public function municipios(): HasMany
    {
        return $this->hasMany(Municipio::class, 'regiao_id');
    }
}
