<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MandatoPrefeito extends Model
{
    protected $table = 'mandato_prefeito';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ano_eleicao' => 'integer',
            'cd_eleicao' => 'integer',
            'dt_eleicao' => 'date:Y-m-d',
            'nr_turno' => 'integer',
            'nr_candidato' => 'integer',
            'mandato_inicio' => 'date:Y-m-d',
            'mandato_fim' => 'date:Y-m-d',
            'mandato_consecutivo' => 'integer',
            'reeleito' => 'boolean',
        ];
    }

    public function scopeVigenteNaData(Builder $query, Carbon|string|null $data = null): Builder
    {
        $hoje = $data ? Carbon::parse($data)->toDateString() : now()->toDateString();

        return $query
            ->whereDate('mandato_inicio', '<=', $hoje)
            ->whereDate('mandato_fim', '>=', $hoje);
    }

    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_id');
    }

    public function prefeito(): BelongsTo
    {
        return $this->belongsTo(Prefeito::class, 'prefeito_id');
    }

    public function partido(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'partido_id');
    }
}
