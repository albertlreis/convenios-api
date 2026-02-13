<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MandatoPrefeito;
use App\Models\Municipio;
use App\Models\Partido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EleitoralController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'municipio_id' => ['nullable', 'integer'],
            'ano_eleicao' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'partido_id' => ['nullable', 'integer'],
            'vigente_hoje' => ['nullable', 'boolean'],
            'orderBy' => ['nullable', 'string', 'in:ano_eleicao,municipio_nome,partido_sigla,prefeito_nome,mandato_inicio'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $query = MandatoPrefeito::query()
            ->from('mandato_prefeito as mp')
            ->join('municipio as m', 'm.id', '=', 'mp.municipio_id')
            ->join('prefeito as p', 'p.id', '=', 'mp.prefeito_id')
            ->leftJoin('partido as pa', 'pa.id', '=', 'mp.partido_id')
            ->select([
                'mp.id',
                'mp.municipio_id',
                'mp.prefeito_id',
                'mp.partido_id',
                'mp.ano_eleicao',
                'mp.cd_eleicao',
                'mp.dt_eleicao',
                'mp.nr_turno',
                'mp.nr_candidato',
                'mp.mandato_inicio',
                'mp.mandato_fim',
                'mp.mandato_consecutivo',
                'mp.reeleito',
                'm.nome as municipio_nome',
                'm.uf as municipio_uf',
                'p.nome_completo as prefeito_nome_completo',
                'p.nome_urna as prefeito_nome_urna',
                'pa.sigla as partido_sigla',
                'pa.nome as partido_nome',
            ]);

        if (! empty($validated['q'])) {
            $like = '%'.mb_strtolower(trim((string) $validated['q'])).'%';
            $query->where(function ($nested) use ($like): void {
                $nested->whereRaw('LOWER(m.nome) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(p.nome_completo) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(COALESCE(p.nome_urna, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(pa.sigla, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(pa.nome, '')) LIKE ?", [$like]);
            });
        }

        if (! empty($validated['municipio_id'])) {
            $query->where('mp.municipio_id', (int) $validated['municipio_id']);
        }

        if (! empty($validated['ano_eleicao'])) {
            $query->where('mp.ano_eleicao', (int) $validated['ano_eleicao']);
        }

        if (! empty($validated['partido_id'])) {
            $query->where('mp.partido_id', (int) $validated['partido_id']);
        }

        if (($validated['vigente_hoje'] ?? null) !== null) {
            if ((bool) $validated['vigente_hoje']) {
                $query->whereDate('mp.mandato_inicio', '<=', now()->toDateString())
                    ->whereDate('mp.mandato_fim', '>=', now()->toDateString());
            }
        }

        $orderByMap = [
            'ano_eleicao' => 'mp.ano_eleicao',
            'municipio_nome' => 'm.nome',
            'partido_sigla' => 'pa.sigla',
            'prefeito_nome' => 'p.nome_completo',
            'mandato_inicio' => 'mp.mandato_inicio',
        ];
        $orderBy = $orderByMap[$validated['orderBy'] ?? 'ano_eleicao'] ?? 'mp.ano_eleicao';
        $direction = ($validated['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, min((int) ($validated['per_page'] ?? 20), 200));

        $paginator = (clone $query)
            ->orderBy($orderBy, $direction)
            ->orderBy('mp.id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        $agg = DB::query()->fromSub($query, 'eleitoral_filtrado')->selectRaw(
            'COUNT(*) as total_mandatos,
             COUNT(DISTINCT municipio_id) as total_municipios,
             COUNT(DISTINCT prefeito_id) as total_prefeitos,
             COUNT(DISTINCT partido_id) as total_partidos'
        )->first();

        $partidosDistribuicao = DB::query()
            ->fromSub($query, 'eleitoral_filtrado')
            ->selectRaw("COALESCE(partido_sigla, 'SEM_PARTIDO') as partido, COUNT(*) as total")
            ->groupBy('partido')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'partido' => $row->partido,
                'total' => (int) $row->total,
            ])->values();

        return response()->json([
            'sucesso' => true,
            'data' => [
                'results' => collect($paginator->items())->map(fn ($item): array => [
                    'id' => (int) $item->id,
                    'municipio_id' => (int) $item->municipio_id,
                    'prefeito_id' => (int) $item->prefeito_id,
                    'partido_id' => $item->partido_id !== null ? (int) $item->partido_id : null,
                    'ano_eleicao' => (int) $item->ano_eleicao,
                    'cd_eleicao' => (int) $item->cd_eleicao,
                    'dt_eleicao' => $item->dt_eleicao,
                    'nr_turno' => (int) $item->nr_turno,
                    'nr_candidato' => $item->nr_candidato !== null ? (int) $item->nr_candidato : null,
                    'mandato_inicio' => $item->mandato_inicio,
                    'mandato_fim' => $item->mandato_fim,
                    'mandato_consecutivo' => $item->mandato_consecutivo !== null ? (int) $item->mandato_consecutivo : null,
                    'reeleito' => $item->reeleito !== null ? (bool) $item->reeleito : null,
                    'municipio' => [
                        'id' => (int) $item->municipio_id,
                        'nome' => $item->municipio_nome,
                        'uf' => $item->municipio_uf,
                    ],
                    'prefeito' => [
                        'id' => (int) $item->prefeito_id,
                        'nome_completo' => $item->prefeito_nome_completo,
                        'nome_urna' => $item->prefeito_nome_urna,
                    ],
                    'partido' => $item->partido_id !== null ? [
                        'id' => (int) $item->partido_id,
                        'sigla' => $item->partido_sigla,
                        'nome' => $item->partido_nome,
                    ] : null,
                ])->values(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
                'meta' => [
                    'kpis' => [
                        'total_mandatos' => (int) ($agg->total_mandatos ?? 0),
                        'total_municipios' => (int) ($agg->total_municipios ?? 0),
                        'total_prefeitos' => (int) ($agg->total_prefeitos ?? 0),
                        'total_partidos' => (int) ($agg->total_partidos ?? 0),
                    ],
                    'distribuicao_partidos' => $partidosDistribuicao,
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

        $mandatos = MandatoPrefeito::query()
            ->with(['prefeito', 'partido'])
            ->where('municipio_id', $municipioId)
            ->orderByDesc('ano_eleicao')
            ->orderByDesc('mandato_inicio')
            ->get();

        $anos = $mandatos->pluck('ano_eleicao')
            ->filter()
            ->map(fn ($ano) => (int) $ano)
            ->unique()
            ->sortDesc()
            ->values();

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
                'anos_disponiveis' => $anos,
                'mandatos' => $mandatos->map(fn (MandatoPrefeito $mandato): array => [
                    'id' => $mandato->id,
                    'ano_eleicao' => $mandato->ano_eleicao,
                    'cd_eleicao' => $mandato->cd_eleicao,
                    'dt_eleicao' => $mandato->dt_eleicao?->format('Y-m-d'),
                    'nr_turno' => $mandato->nr_turno,
                    'nr_candidato' => $mandato->nr_candidato,
                    'mandato_inicio' => $mandato->mandato_inicio?->format('Y-m-d'),
                    'mandato_fim' => $mandato->mandato_fim?->format('Y-m-d'),
                    'mandato_consecutivo' => $mandato->mandato_consecutivo,
                    'reeleito' => $mandato->reeleito,
                    'prefeito' => $mandato->prefeito ? [
                        'id' => $mandato->prefeito->id,
                        'nome_completo' => $mandato->prefeito->nome_completo,
                        'nome_urna' => $mandato->prefeito->nome_urna,
                    ] : null,
                    'partido' => $mandato->partido ? [
                        'id' => $mandato->partido->id,
                        'sigla' => $mandato->partido->sigla,
                        'nome' => $mandato->partido->nome,
                    ] : null,
                ])->values(),
                'kpis' => [
                    'total_mandatos' => $mandatos->count(),
                    'total_partidos' => $mandatos->pluck('partido_id')->filter()->unique()->count(),
                    'total_prefeitos' => $mandatos->pluck('prefeito_id')->filter()->unique()->count(),
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
            ->select(['id', 'nome', 'uf'])
            ->when($like, fn ($query) => $query->whereRaw('LOWER(nome) LIKE ?', [$like]))
            ->orderBy('nome')
            ->limit($limit)
            ->get();

        $partidos = Partido::query()
            ->select(['id', 'sigla', 'nome'])
            ->when($like, function ($query) use ($like): void {
                $query->where(function ($nested) use ($like): void {
                    $nested->whereRaw("LOWER(COALESCE(sigla, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(nome, '')) LIKE ?", [$like]);
                });
            })
            ->orderBy('sigla')
            ->limit($limit)
            ->get();

        $anos = MandatoPrefeito::query()
            ->selectRaw('DISTINCT ano_eleicao')
            ->orderByDesc('ano_eleicao')
            ->pluck('ano_eleicao')
            ->map(fn ($ano) => (int) $ano)
            ->values();

        return response()->json([
            'sucesso' => true,
            'data' => [
                'municipios' => $municipios,
                'partidos' => $partidos,
                'anos' => $anos,
            ],
        ]);
    }
}
