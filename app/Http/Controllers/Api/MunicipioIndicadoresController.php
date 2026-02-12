<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RegiaoIntegracao;
use App\Support\LatestMunicipioDemografia;
use Illuminate\Support\Facades\DB;

class MunicipioIndicadoresController extends Controller
{
    public function populacaoPorRegiao(): array
    {
        $data = $this->demografiaPorRegiao('populacao');

        return ['data' => $data];
    }

    public function eleitoresPorRegiao(): array
    {
        $data = $this->demografiaPorRegiao('eleitores');

        return ['data' => $data];
    }

    private function demografiaPorRegiao(string $campo): array
    {
        $query = RegiaoIntegracao::query()
            ->selectRaw('regiao_integracao.id')
            ->selectRaw('regiao_integracao.descricao')
            ->leftJoin('municipio', 'municipio.regiao_id', '=', 'regiao_integracao.id')
            ->groupBy('regiao_integracao.id', 'regiao_integracao.descricao')
            ->orderBy('regiao_integracao.descricao');

        LatestMunicipioDemografia::join($query, 'municipio.id');

        $query->addSelect(DB::raw("COALESCE(SUM(COALESCE(md_latest.{$campo}, 0)), 0) as total"));

        return $query->get()->map(fn ($row) => [
            'regiao_id' => $row->id,
            'regiao_descricao' => $row->descricao,
            $campo => (int) $row->total,
        ])->all();
    }
}
