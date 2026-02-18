<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Municipio;
use App\Models\MunicipioDemografia;
use App\Models\RegiaoIntegracao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DemografiaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'municipio_id' => ['nullable', 'integer'],
            'regiao_integracao_id' => ['nullable', 'integer'],
            'ano' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ano_de' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'ano_ate' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'orderBy' => ['nullable', 'string', 'in:ano_ref,municipio_nome,populacao,eleitores'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = MunicipioDemografia::query()
            ->from('demografia_municipio as dm')
            ->join('municipio as m', 'm.id', '=', 'dm.municipio_id')
            ->leftJoin('regiao_integracao as r', 'r.id', '=', 'm.regiao_id')
            ->select([
                'dm.id',
                'dm.municipio_id',
                'dm.ano_ref',
                'dm.populacao',
                'dm.eleitores',
                'm.nome as municipio_nome',
                'm.uf as municipio_uf',
                'm.regiao_id as regiao_integracao_id',
                'r.descricao as regiao_integracao_descricao',
            ]);

        if (! empty($validated['q'])) {
            $like = '%'.mb_strtolower(trim((string) $validated['q'])).'%';
            $query->where(function ($nested) use ($like): void {
                $nested->whereRaw('LOWER(m.nome) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(COALESCE(r.descricao, '')) LIKE ?", [$like]);
            });
        }

        if (! empty($validated['municipio_id'])) {
            $query->where('dm.municipio_id', (int) $validated['municipio_id']);
        }

        if (! empty($validated['regiao_integracao_id'])) {
            $query->where('m.regiao_id', (int) $validated['regiao_integracao_id']);
        }

        if (! empty($validated['ano'])) {
            $query->where('dm.ano_ref', (int) $validated['ano']);
        }

        if (! empty($validated['ano_de'])) {
            $query->where('dm.ano_ref', '>=', (int) $validated['ano_de']);
        }

        if (! empty($validated['ano_ate'])) {
            $query->where('dm.ano_ref', '<=', (int) $validated['ano_ate']);
        }

        $orderByMap = [
            'ano_ref' => 'dm.ano_ref',
            'municipio_nome' => 'm.nome',
            'populacao' => 'dm.populacao',
            'eleitores' => 'dm.eleitores',
        ];
        $orderBy = $orderByMap[$validated['orderBy'] ?? 'municipio_nome'] ?? 'm.nome';
        $direction = ($validated['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $perPage = max(1, min((int) ($validated['per_page'] ?? 20), 200));
        $paginator = (clone $query)
            ->orderBy($orderBy, $direction)
            ->orderBy('dm.id')
            ->paginate($perPage)
            ->withQueryString();

        $baseAgg = DB::query()->fromSub($query, 'demografia_filtrada')->selectRaw(
            'COUNT(*) as total_registros,
             COUNT(DISTINCT municipio_id) as total_municipios,
             COALESCE(SUM(populacao), 0) as total_populacao,
             COALESCE(SUM(COALESCE(eleitores, 0)), 0) as total_eleitores,
             COALESCE(AVG(populacao), 0) as media_populacao,
             COALESCE(AVG(COALESCE(eleitores, 0)), 0) as media_eleitores'
        )->first();

        $series = DB::query()
            ->fromSub($query, 'demografia_filtrada')
            ->selectRaw('ano_ref, COALESCE(SUM(populacao), 0) as populacao_total, COALESCE(SUM(COALESCE(eleitores, 0)), 0) as eleitores_total')
            ->groupBy('ano_ref')
            ->orderBy('ano_ref')
            ->get()
            ->map(fn ($row): array => [
                'ano_ref' => (int) $row->ano_ref,
                'populacao_total' => (int) $row->populacao_total,
                'eleitores_total' => (int) $row->eleitores_total,
            ])
            ->values();

        return response()->json([
            'sucesso' => true,
            'data' => [
                'results' => collect($paginator->items())->map(fn ($item): array => [
                    'id' => (int) $item->id,
                    'municipio_id' => (int) $item->municipio_id,
                    'ano_ref' => (int) $item->ano_ref,
                    'populacao' => (int) $item->populacao,
                    'eleitores' => $item->eleitores !== null ? (int) $item->eleitores : null,
                    'municipio' => [
                        'id' => (int) $item->municipio_id,
                        'nome' => $item->municipio_nome,
                        'uf' => $item->municipio_uf,
                        'regiao_integracao_id' => $item->regiao_integracao_id !== null ? (int) $item->regiao_integracao_id : null,
                        'regiao_integracao_descricao' => $item->regiao_integracao_descricao,
                    ],
                ])->values(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
                'meta' => [
                    'kpis' => [
                        'total_registros' => (int) ($baseAgg->total_registros ?? 0),
                        'total_municipios' => (int) ($baseAgg->total_municipios ?? 0),
                        'total_populacao' => (int) ($baseAgg->total_populacao ?? 0),
                        'total_eleitores' => (int) ($baseAgg->total_eleitores ?? 0),
                        'media_populacao' => (int) round((float) ($baseAgg->media_populacao ?? 0)),
                        'media_eleitores' => (int) round((float) ($baseAgg->media_eleitores ?? 0)),
                    ],
                    'series' => $series,
                    'filtros_aplicados' => $validated,
                ],
            ],
        ]);
    }

    public function showMunicipio(Request $request, int $municipioId): JsonResponse
    {
        $municipio = Municipio::query()
            ->with('regiaoIntegracao')
            ->findOrFail($municipioId);

        $series = MunicipioDemografia::query()
            ->where('municipio_id', $municipioId)
            ->orderBy('ano_ref')
            ->get(['id', 'ano_ref', 'populacao', 'eleitores']);

        $ultimo = $series->last();

        return response()->json([
            'sucesso' => true,
            'data' => [
                'municipio' => [
                    'id' => $municipio->id,
                    'nome' => $municipio->nome,
                    'uf' => $municipio->uf,
                    'regiao_integracao' => $municipio->regiaoIntegracao ? [
                        'id' => $municipio->regiaoIntegracao->id,
                        'descricao' => $municipio->regiaoIntegracao->descricao,
                    ] : null,
                ],
                'series' => $series->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'ano_ref' => (int) $row->ano_ref,
                    'populacao' => (int) $row->populacao,
                    'eleitores' => $row->eleitores !== null ? (int) $row->eleitores : null,
                ])->values(),
                'indicadores' => [
                    'ano_mais_recente' => $ultimo?->ano_ref !== null ? (int) $ultimo->ano_ref : null,
                    'populacao_mais_recente' => $ultimo?->populacao !== null ? (int) $ultimo->populacao : null,
                    'eleitores_mais_recente' => $ultimo?->eleitores !== null ? (int) $ultimo->eleitores : null,
                    'total_registros' => $series->count(),
                ],
            ],
        ]);
    }

    public function lookups(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        $q = mb_strtolower(trim((string) ($validated['q'] ?? '')));
        $like = $q !== '' ? '%'.$q.'%' : null;

        $municipios = Municipio::query()
            ->select(['id', 'nome', 'uf', 'regiao_id'])
            ->when($like, fn ($query) => $query->whereRaw('LOWER(nome) LIKE ?', [$like]))
            ->orderBy('nome')
            ->limit($limit)
            ->get();

        $regioes = RegiaoIntegracao::query()
            ->select(['id', 'descricao'])
            ->when($like, fn ($query) => $query->whereRaw('LOWER(descricao) LIKE ?', [$like]))
            ->orderBy('descricao')
            ->limit($limit)
            ->get();

        $anos = MunicipioDemografia::query()
            ->selectRaw('DISTINCT ano_ref')
            ->orderByDesc('ano_ref')
            ->pluck('ano_ref')
            ->map(fn ($ano) => (int) $ano)
            ->values();

        return response()->json([
            'sucesso' => true,
            'data' => [
                'municipios' => $municipios,
                'regioes' => $regioes,
                'anos' => $anos,
            ],
        ]);
    }
}
