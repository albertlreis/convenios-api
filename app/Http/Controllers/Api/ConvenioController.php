<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConvenioRequest;
use App\Http\Requests\UpdateConvenioRequest;
use App\Http\Resources\ConvenioResource;
use App\Http\Resources\ParcelaResource;
use App\Models\Convenio;
use App\Models\ConvenioPlanoInterno;
use App\Support\LatestMunicipioDemografia;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConvenioController extends Controller
{
    public function index(Request $request)
    {
        $query = Convenio::query()
            ->select('convenio.*')
            ->with([
                'orgao',
                'municipioBeneficiario.regiaoIntegracao',
                'municipioConvenente',
                'planosInternos',
            ])
            ->leftJoin('municipio as municipio_beneficiario', function ($join): void {
                $join->on('municipio_beneficiario.id', '=', 'convenio.municipio_beneficiario_id');
            })
            ->leftJoin('orgao as orgao_rel', function ($join): void {
                $join->on('orgao_rel.id', '=', 'convenio.orgao_id')
                    ->whereNull('orgao_rel.deleted_at');
            })
            ->leftJoinSub($this->mandatoVigenteSortSubquery(), 'mandato_vigente_sort', function ($join): void {
                $join->on('mandato_vigente_sort.municipio_id', '=', 'convenio.municipio_beneficiario_id');
            })
            ->addSelect([
                'municipio_nome' => DB::raw('municipio_beneficiario.nome'),
                'orgao_sigla' => DB::raw('orgao_rel.sigla'),
                'prefeito_nome_sort' => DB::raw('mandato_vigente_sort.prefeito_nome'),
                'partido_sigla_sort' => DB::raw('mandato_vigente_sort.partido_sigla'),
                'mandato_consecutivo_sort' => DB::raw('mandato_vigente_sort.mandato_consecutivo'),
                'plano_interno_sort' => DB::raw('(SELECT MIN(cp.plano_interno) FROM convenio_plano_interno cp WHERE cp.convenio_id = convenio.id)'),
            ]);

        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        LatestMunicipioDemografia::join($query, 'convenio.municipio_beneficiario_id');

        $query->addSelect([
            'populacao_ref' => DB::raw('COALESCE(md_latest.populacao, 0)'),
            'eleitores_ref' => DB::raw('COALESCE(md_latest.eleitores, 0)'),
        ]);

        $query->withParcelasAgg();

        if ($request->filled('municipio_id')) {
            $query->where('convenio.municipio_beneficiario_id', $request->integer('municipio_id'));
        }

        if ($request->filled('regiao_id')) {
            $query->where('municipio_beneficiario.regiao_id', $request->integer('regiao_id'));
        }

        if ($request->filled('orgao_id')) {
            $query->where('convenio.orgao_id', $request->integer('orgao_id'));
        }

        if ($request->filled('plano_interno')) {
            $planoInterno = (string) $request->string('plano_interno');
            $normalizedPlanoInterno = str_replace('*', '%', $planoInterno);

            $query->whereExists(function ($subquery) use ($normalizedPlanoInterno, $request): void {
                $subquery->selectRaw('1')
                    ->from('convenio_plano_interno as cp')
                    ->whereColumn('cp.convenio_id', 'convenio.id');

                if ($request->boolean('plano_interno_like') || str_contains($normalizedPlanoInterno, '%')) {
                    $subquery->where('cp.plano_interno', 'like', $normalizedPlanoInterno);
                } else {
                    $subquery->where('cp.plano_interno', $normalizedPlanoInterno);
                }
            });
        }

        if ($request->filled('numero_convenio')) {
            $query->where('convenio.numero_convenio', 'like', '%'.$request->string('numero_convenio').'%');
        }

        if ($request->boolean('com_parcelas_em_aberto')) {
            $query->comParcelasEmAberto();
        }

        if ($request->filled('valor_em_aberto_min')) {
            $query->having('valor_em_aberto_total', '>=', (float) $request->input('valor_em_aberto_min'));
        }

        if ($request->filled('valor_em_aberto_max')) {
            $query->having('valor_em_aberto_total', '<=', (float) $request->input('valor_em_aberto_max'));
        }

        if ($request->filled('populacao_min')) {
            $query->whereRaw('COALESCE(md_latest.populacao, 0) >= ?', [(int) $request->input('populacao_min')]);
        }

        if ($request->filled('populacao_max')) {
            $query->whereRaw('COALESCE(md_latest.populacao, 0) <= ?', [(int) $request->input('populacao_max')]);
        }

        if ($request->filled('eleitores_min')) {
            $query->whereRaw('COALESCE(md_latest.eleitores, 0) >= ?', [(int) $request->input('eleitores_min')]);
        }

        if ($request->filled('eleitores_max')) {
            $query->whereRaw('COALESCE(md_latest.eleitores, 0) <= ?', [(int) $request->input('eleitores_max')]);
        }

        if ($request->filled('partido_id')) {
            $partidoId = $request->integer('partido_id');

            $query->whereExists(function ($subquery) use ($partidoId): void {
                $this->applyMandatoVigenteBase($subquery);
                $subquery->where('mandato_vigente.partido_id', $partidoId);
            });
        }

        if ($request->boolean('prefeito_primeiro_mandato')) {
            $query->whereExists(function ($subquery): void {
                $this->applyMandatoVigenteBase($subquery);
                $subquery->where('mandato_vigente.mandato_consecutivo', 1);
            });
        }

        if ($request->boolean('prefeito_segundo_mandato')) {
            $query->whereExists(function ($subquery): void {
                $this->applyMandatoVigenteBase($subquery);
                $subquery->where('mandato_vigente.mandato_consecutivo', 2);
            });
        }

        $sortInput = (string) $request->query('sort', 'codigo');
        $sortDirection = str_starts_with($sortInput, '-') ? 'desc' : 'asc';
        $sortField = ltrim($sortInput, '-');

        $sortableFields = [
            'codigo' => 'convenio.codigo',
            'numero_convenio' => 'convenio.numero_convenio',
            'data_inicio' => 'convenio.data_inicio',
            'data_fim' => 'convenio.data_fim',
            'plano_interno' => 'plano_interno_sort',
            'municipio_nome' => 'municipio_nome',
            'orgao_sigla' => 'orgao_sigla',
            'prefeito_nome' => 'prefeito_nome_sort',
            'partido_sigla' => 'partido_sigla_sort',
            'mandato_consecutivo' => 'mandato_consecutivo_sort',
            'valor_em_aberto_total' => 'valor_em_aberto_total',
            'parcelas_em_aberto' => 'parcelas_em_aberto',
        ];

        if (! isset($sortableFields[$sortField])) {
            $sortField = 'codigo';
            $sortDirection = 'asc';
        }

        $query->orderBy($sortableFields[$sortField], $sortDirection)
            ->orderBy('convenio.id');

        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        return ConvenioResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreConvenioRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $planosInternos = $this->extractPlanosInternos($validated);
        unset($validated['plano_interno'], $validated['planos_internos']);

        $convenio = Convenio::query()->create($validated);
        $this->syncPlanosInternos($convenio, $planosInternos);

        $convenio->load(['orgao', 'municipioBeneficiario.regiaoIntegracao', 'municipioConvenente', 'planosInternos']);
        $convenio->loadAggregate('parcelas', 'id', 'count');

        return ConvenioResource::make($convenio)->response()->setStatusCode(201);
    }

    public function show(int $convenio): ConvenioResource
    {
        $convenio = Convenio::query()
            ->withTrashed()
            ->whereKey($convenio)
            ->with([
                'orgao',
                'municipioBeneficiario.regiaoIntegracao',
                'municipioConvenente',
                'parcelas',
                'planosInternos',
            ])
            ->withParcelasAgg()
            ->firstOrFail();

        return ConvenioResource::make($convenio);
    }

    public function update(UpdateConvenioRequest $request, Convenio $convenio): ConvenioResource
    {
        $validated = $request->validated();
        $planosInternos = $this->extractPlanosInternos($validated);
        unset($validated['plano_interno'], $validated['planos_internos']);

        $convenio->fill($validated);
        $convenio->save();
        $this->syncPlanosInternos($convenio, $planosInternos);

        return $this->show($convenio->id);
    }

    public function destroy(Convenio $convenio): JsonResponse
    {
        $convenio->delete();

        return response()->json(status: 204);
    }

    public function restore(int $convenio): ConvenioResource
    {
        $convenio = Convenio::query()
            ->withTrashed()
            ->findOrFail($convenio);

        if ($convenio->trashed()) {
            $convenio->restore();
        }

        return $this->show($convenio->id);
    }

    public function parcelas(Request $request, int $convenio)
    {
        $convenio = Convenio::query()
            ->withTrashed()
            ->whereKey($convenio)
            ->firstOrFail();
        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        $parcelas = $convenio->parcelas()
            ->orderBy('numero')
            ->paginate($perPage)
            ->withQueryString();

        return ParcelaResource::collection($parcelas);
    }

    public function parcelasEmAberto(Request $request, int $convenio)
    {
        $convenio = Convenio::query()
            ->withTrashed()
            ->whereKey($convenio)
            ->firstOrFail();
        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        $parcelas = $convenio->parcelas()
            ->emAberto()
            ->orderBy('numero')
            ->paginate($perPage)
            ->withQueryString();

        return ParcelaResource::collection($parcelas);
    }

    private function mandatoVigenteSortSubquery(): QueryBuilder
    {
        $hoje = now()->toDateString();

        return DB::table('mandato_prefeito as mandato_vigente')
            ->from('mandato_prefeito as mandato_vigente')
            ->leftJoin('prefeito as prefeito_vigente', 'prefeito_vigente.id', '=', 'mandato_vigente.prefeito_id')
            ->leftJoin('partido as partido_vigente', 'partido_vigente.id', '=', 'mandato_vigente.partido_id')
            ->whereDate('mandato_vigente.mandato_inicio', '<=', $hoje)
            ->whereDate('mandato_vigente.mandato_fim', '>=', $hoje)
            ->selectRaw('mandato_vigente.municipio_id')
            ->selectRaw('MIN(prefeito_vigente.nome_completo) as prefeito_nome')
            ->selectRaw('MIN(partido_vigente.sigla) as partido_sigla')
            ->selectRaw('MIN(mandato_vigente.mandato_consecutivo) as mandato_consecutivo')
            ->groupBy('mandato_vigente.municipio_id');
    }

    private function applyMandatoVigenteBase(QueryBuilder $subquery): void
    {
        $subquery->selectRaw('1')
            ->from('mandato_prefeito as mandato_vigente')
            ->whereColumn('mandato_vigente.municipio_id', 'convenio.municipio_beneficiario_id')
            ->whereDate('mandato_vigente.mandato_inicio', '<=', now()->toDateString())
            ->whereDate('mandato_vigente.mandato_fim', '>=', now()->toDateString());
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, string>|null
     */
    private function extractPlanosInternos(array $validated): ?array
    {
        $planos = $validated['planos_internos'] ?? null;
        $planoUnico = $validated['plano_interno'] ?? null;

        if ($planos === null && $planoUnico === null) {
            return null;
        }

        $rawPlanos = [];
        if (is_array($planos)) {
            $rawPlanos = $planos;
        }
        if (is_string($planoUnico) && trim($planoUnico) !== '') {
            $rawPlanos[] = $planoUnico;
        }

        $normalized = collect($rawPlanos)
            ->map(fn ($item) => strtoupper(trim((string) $item)))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();

        return $normalized;
    }

    /**
     * @param  array<int, string>|null  $planosInternos
     */
    private function syncPlanosInternos(Convenio $convenio, ?array $planosInternos): void
    {
        if ($planosInternos === null) {
            return;
        }

        $convenio->planosInternos()->delete();

        if ($planosInternos === []) {
            return;
        }

        $payload = collect($planosInternos)->map(
            fn (string $planoInterno): array => [
                'convenio_id' => $convenio->id,
                'plano_interno' => $planoInterno,
                'origem' => 'api_manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        )->all();

        ConvenioPlanoInterno::query()->insert($payload);
    }
}
