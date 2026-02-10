<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MandatoPrefeito extends Model
{
    use SoftDeletes;

    public const SITUACOES_VIGENTES = [
        'EM_EXERCICIO',
        'AFASTADO',
        'INTERINO',
    ];

    protected $table = 'mandato_prefeito';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'inicio' => 'date:Y-m-d',
            'fim' => 'date:Y-m-d',
            'mandato_consecutivo' => 'integer',
            'reeleito' => 'boolean',
        ];
    }

    public function scopeVigenteNaData(Builder $query, Carbon|string|null $data = null): Builder
    {
        $hoje = $data ? Carbon::parse($data)->toDateString() : now()->toDateString();

        return $query
            ->whereDate('inicio', '<=', $hoje)
            ->whereDate('fim', '>=', $hoje)
            ->whereIn('situacao', self::SITUACOES_VIGENTES);
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

    public function eleicao(): BelongsTo
    {
        return $this->belongsTo(Eleicao::class, 'eleicao_id');
    }
}
