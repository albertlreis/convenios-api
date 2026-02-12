<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipioDemografia extends Model
{
    protected $table = 'demografia_municipio';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ano_ref' => 'integer',
            'populacao' => 'integer',
            'eleitores' => 'integer',
        ];
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_id');
    }
}
