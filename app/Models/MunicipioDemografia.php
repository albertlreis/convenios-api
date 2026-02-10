<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MunicipioDemografia extends Model
{
    use SoftDeletes;

    protected $table = 'municipio_demografia';

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
