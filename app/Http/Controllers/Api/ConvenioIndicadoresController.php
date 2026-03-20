<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\Municipio;
use App\Support\LatestMunicipioDemografia;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ConvenioIndicadoresController extends Controller
{
    public function home(): array
    {
        $totais = $this->totaisConvenios();

        return [
            'convenios_encerrados' => (int) ($totais->convenios_encerrados ?? 0),
            'convenios_com_parcelas_em_aberto' => (int) ($totais->convenios_com_parcelas_em_aberto ?? 0),
            'valor_pago_total' => number_format((float) ($totais->valor_pago_total ?? 0), 2, '.', ''),
            'valor_em_aberto_total' => number_format((float) ($totais->valor_em_aberto_total ?? 0), 2, '.', ''),
        ];
    }

    public function quantidadeComParcelasEmAberto(): array
    {
        $totais = $this->totaisConvenios();

        return [
            'quantidade' => (int) ($totais->convenios_com_parcelas_em_aberto ?? 0),
        ];
    }

    public function valoresEmAberto(): array
    {
        $totais = $this->totaisConvenios();

        return [
            'valor_em_aberto_total' => number_format((float) ($totais->valor_em_aberto_total ?? 0), 2, '.', ''),
            'valor_previsto_total' => number_format((float) ($totais->valor_previsto_total ?? 0), 2, '.', ''),
            'valor_pago_total' => number_format((float) ($totais->valor_pago_total ?? 0), 2, '.', ''),
        ];
    }

    public function populacaoAtendida(): array
    {
        return [
            'populacao_atendida' => $this->totalDemografiaMunicipiosAtendidos('populacao'),
        ];
    }

    public function eleitoresAtendidos(): array
    {
        return [
            'eleitores_atendidos' => $this->totalDemografiaMunicipiosAtendidos('eleitores'),
        ];
    }

    private function totalDemografiaMunicipiosAtendidos(string $campo): int
    {
        $query = Municipio::query()
            ->selectRaw("COALESCE(SUM(COALESCE(md_latest.{$campo}, 0)), 0) as total")
            ->whereExists(function (Builder $subquery): void {
                $subquery->selectRaw('1')
                    ->from('convenio')
                    ->whereColumn('convenio.municipio_id', 'municipio.id')
                    ->whereNull('convenio.deleted_at');
            });

        LatestMunicipioDemografia::join($query, 'municipio.id');

        return (int) ($query->value('total') ?? 0);
    }

    private function totaisConvenios(): object
    {
        $subquery = Convenio::query()
            ->select(['convenio.id'])
            ->withParcelasAgg();

        return DB::query()
            ->fromSub($subquery, 'convenios_agg')
            ->selectRaw('COALESCE(SUM(CASE WHEN convenios_agg.parcelas_total > 0 AND convenios_agg.parcelas_em_aberto = 0 THEN 1 ELSE 0 END), 0) as convenios_encerrados')
            ->selectRaw('COALESCE(SUM(CASE WHEN convenios_agg.parcelas_em_aberto > 0 THEN 1 ELSE 0 END), 0) as convenios_com_parcelas_em_aberto')
            ->selectRaw('COALESCE(SUM(convenios_agg.valor_em_aberto_total), 0) as valor_em_aberto_total')
            ->selectRaw('COALESCE(SUM(convenios_agg.valor_previsto_total), 0) as valor_previsto_total')
            ->selectRaw('COALESCE(SUM(CASE WHEN convenios_agg.parcelas_em_aberto > 0 THEN convenios_agg.valor_pago_total ELSE 0 END), 0) as valor_pago_total')
            ->first();
    }
}
