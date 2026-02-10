<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Parcela extends Model
{
    /** @use HasFactory<\Database\Factories\ParcelaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'parcela';

    protected $guarded = [];

    public static function emAbertoCondition(string $tableAlias = 'parcela'): string
    {
        return sprintf(
            '((%1$s.valor_previsto = 0 AND %1$s.data_pagamento IS NULL AND %1$s.situacao <> \'PAGA\') OR ((%1$s.valor_previsto IS NULL OR %1$s.valor_previsto <> 0) AND (%1$s.data_pagamento IS NULL OR %1$s.valor_pago IS NULL OR (%1$s.valor_previsto IS NOT NULL AND %1$s.valor_pago < %1$s.valor_previsto))))',
            $tableAlias
        );
    }

    protected function casts(): array
    {
        return [
            'data_pagamento' => 'date:Y-m-d',
            'data_ne' => 'date:Y-m-d',
            'valor_previsto' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'valor_empenhado' => 'decimal:2',
        ];
    }

    public function scopeEmAberto(Builder $query): Builder
    {
        return $query->whereRaw(self::emAbertoCondition($query->getModel()->getTable()));
    }

    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class, 'convenio_id');
    }
}
