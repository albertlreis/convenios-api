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
    public function quantidadeComParcelasEmAberto(): array
    {
        return [
            'quantidade' => Convenio::query()->comParcelasEmAberto()->count(),
        ];
    }

    public function valoresEmAberto(): array
    {
        $subquery = Convenio::query()
            ->select('convenio.id')
            ->withParcelasAgg();

        $totais = DB::query()
            ->fromSub($subquery, 'convenios_agg')
            ->selectRaw('COALESCE(SUM(convenios_agg.valor_em_aberto_total), 0) as valor_em_aberto_total')
            ->selectRaw('COALESCE(SUM(convenios_agg.valor_previsto_total), 0) as valor_previsto_total')
            ->selectRaw('COALESCE(SUM(convenios_agg.valor_pago_total), 0) as valor_pago_total')
            ->first();

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
}
