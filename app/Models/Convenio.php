<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Convenio extends Model
{
    /** @use HasFactory<\Database\Factories\ConvenioFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'convenio';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date:Y-m-d',
            'data_fim' => 'date:Y-m-d',
            'valor_orgao' => 'decimal:2',
            'valor_contrapartida' => 'decimal:2',
            'valor_aditivo' => 'decimal:2',
            'valor_total_informado' => 'decimal:2',
            'valor_total_calculado' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function scopeComParcelasEmAberto(Builder $query): Builder
    {
        return $query->whereHas('parcelas', fn (Builder $parcelaQuery) => $parcelaQuery->emAberto());
    }

    public function scopeWithParcelasAgg(Builder $query): Builder
    {
        if (empty($query->getQuery()->columns)) {
            $query->select('convenio.*');
        }

        $baseParcelaQuery = Parcela::query()
            ->from('parcela')
            ->whereColumn('parcela.convenio_id', 'convenio.id')
            ->whereNull('parcela.deleted_at');

        $emAbertoCondition = Parcela::emAbertoCondition('parcela');

        $query->selectSub(
            (clone $baseParcelaQuery)->selectRaw('COUNT(*)'),
            'parcelas_total'
        )->selectSub(
            (clone $baseParcelaQuery)->selectRaw("COALESCE(SUM(CASE WHEN parcela.situacao = 'PAGA' THEN 1 ELSE 0 END), 0)"),
            'parcelas_pagas'
        )->selectSub(
            (clone $baseParcelaQuery)->selectRaw("COALESCE(SUM(CASE WHEN {$emAbertoCondition} THEN 1 ELSE 0 END), 0)"),
            'parcelas_em_aberto'
        )->selectSub(
            (clone $baseParcelaQuery)->selectRaw('COALESCE(SUM(COALESCE(parcela.valor_previsto, 0)), 0)'),
            'valor_previsto_total'
        )->selectSub(
            (clone $baseParcelaQuery)->selectRaw('COALESCE(SUM(COALESCE(parcela.valor_pago, 0)), 0)'),
            'valor_pago_total'
        )->selectSub(
            (clone $baseParcelaQuery)->selectRaw(
                "COALESCE(SUM(CASE WHEN {$emAbertoCondition} THEN GREATEST(COALESCE(parcela.valor_previsto, 0) - COALESCE(parcela.valor_pago, 0), 0) ELSE 0 END), 0)"
            ),
            'valor_em_aberto_total'
        );

        return $query;
    }

    public function orgao(): BelongsTo
    {
        return $this->belongsTo(Orgao::class, 'orgao_id');
    }

    public function municipioBeneficiario(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'municipio_beneficiario_id');
    }

    public function municipioConvenente(): BelongsTo
    {
        return $this->belongsTo(Municipio::class, 'convenente_municipio_id');
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(Parcela::class, 'convenio_id');
    }
}
