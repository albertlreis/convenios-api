<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Eleicao extends Model
{
    use SoftDeletes;

    protected $table = 'eleicao';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dt_eleicao' => 'date:Y-m-d',
            'ano_eleicao' => 'integer',
            'turno' => 'integer',
        ];
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_id');
    }

    public function mandatos(): HasMany
    {
        return $this->hasMany(MandatoPrefeito::class, 'eleicao_id');
    }
}
