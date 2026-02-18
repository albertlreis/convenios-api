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
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'municipio_id' => ['nullable', 'integer'],
            'orgao_id' => ['nullable', 'integer'],
            'situacao_financeira' => ['nullable', 'string', 'in:EM_ABERTO,QUITADO'],
            'com_parcelas_em_aberto' => ['nullable', 'boolean'],
            'vigencia_de' => ['nullable', 'date_format:Y-m-d'],
            'vigencia_ate' => ['nullable', 'date_format:Y-m-d'],
            'data_pagamento_de' => ['nullable', 'date_format:Y-m-d'],
            'data_pagamento_ate' => ['nullable', 'date_format:Y-m-d'],
            'plano_interno' => ['nullable', 'string', 'max:64'],
            'pi_codigo' => ['nullable', 'string', 'max:64'],
            'pi' => ['nullable', 'array'],
            'pi.*' => ['string', 'max:64'],
            'numero_convenio' => ['nullable', 'string', 'max:255'],
            'valor_total_min' => ['nullable', 'numeric', 'min:0'],
            'valor_total_max' => ['nullable', 'numeric', 'min:0'],
            'valor_em_aberto_min' => ['nullable', 'numeric', 'min:0'],
            'valor_em_aberto_max' => ['nullable', 'numeric', 'min:0'],
            'orderBy' => ['nullable', 'string'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'sort' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'with_trashed' => ['nullable', 'boolean'],
            'only_trashed' => ['nullable', 'boolean'],
        ]);

        $query = $this->buildConvenioIndexQuery($request);

        if (! empty($validated['q'])) {
            $term = mb_strtolower(trim((string) $validated['q']));
            $like = '%'.$term.'%';

            $query->where(function ($nested) use ($like): void {
                $nested->whereRaw('LOWER(COALESCE(convenio.numero_convenio, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(convenio.objeto, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(municipio_beneficiario.nome, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(orgao_rel.sigla, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(orgao_rel.nome, "")) LIKE ?', [$like])
                    ->orWhereExists(function ($subquery) use ($like): void {
                        $subquery->selectRaw('1')
                            ->from('convenio_plano_interno as cp_q')
                            ->whereColumn('cp_q.convenio_id', 'convenio.id')
                            ->whereRaw('LOWER(cp_q.plano_interno) LIKE ?', [$like]);
                    });
            });
        }

        if (! empty($validated['municipio_id'])) {
            $query->where('convenio.municipio_id', (int) $validated['municipio_id']);
        }

        if (! empty($validated['orgao_id'])) {
            $query->where('convenio.orgao_id', (int) $validated['orgao_id']);
        }

        if (! empty($validated['numero_convenio'])) {
            $query->where('convenio.numero_convenio', 'like', '%'.trim((string) $validated['numero_convenio']).'%');
        }

        if (($validated['com_parcelas_em_aberto'] ?? null) !== null) {
            if ((bool) $validated['com_parcelas_em_aberto']) {
                $query->comParcelasEmAberto();
            } else {
                $query->whereDoesntHave('parcelas', fn ($parcelaQuery) => $parcelaQuery->emAberto());
            }
        }

        if (($validated['situacao_financeira'] ?? null) === 'EM_ABERTO') {
            $query->comParcelasEmAberto();
        } elseif (($validated['situacao_financeira'] ?? null) === 'QUITADO') {
            $query->whereDoesntHave('parcelas', fn ($parcelaQuery) => $parcelaQuery->emAberto());
        }

        if (! empty($validated['vigencia_de'])) {
            $query->where(function ($nested) use ($validated): void {
                $nested->whereNull('convenio.data_fim')
                    ->orWhereDate('convenio.data_fim', '>=', $validated['vigencia_de']);
            });
        }

        if (! empty($validated['vigencia_ate'])) {
            $query->where(function ($nested) use ($validated): void {
                $nested->whereNull('convenio.data_inicio')
                    ->orWhereDate('convenio.data_inicio', '<=', $validated['vigencia_ate']);
            });
        }

        if (! empty($validated['data_pagamento_de']) || ! empty($validated['data_pagamento_ate'])) {
            $query->whereHas('parcelas', function ($parcelaQuery) use ($validated): void {
                if (! empty($validated['data_pagamento_de'])) {
                    $parcelaQuery->whereDate('data_pagamento', '>=', $validated['data_pagamento_de']);
                }
                if (! empty($validated['data_pagamento_ate'])) {
                    $parcelaQuery->whereDate('data_pagamento', '<=', $validated['data_pagamento_ate']);
                }
            });
        }

        $this->applyPlanoInternoFilters($query, $validated);

        if (($validated['valor_total_min'] ?? null) !== null) {
            $query->whereRaw('COALESCE(convenio.valor_total_calculado, convenio.valor_total_informado, 0) >= ?', [(float) $validated['valor_total_min']]);
        }

        if (($validated['valor_total_max'] ?? null) !== null) {
            $query->whereRaw('COALESCE(convenio.valor_total_calculado, convenio.valor_total_informado, 0) <= ?', [(float) $validated['valor_total_max']]);
        }

        if (($validated['valor_em_aberto_min'] ?? null) !== null) {
            $query->having('valor_em_aberto_total', '>=', (float) $validated['valor_em_aberto_min']);
        }

        if (($validated['valor_em_aberto_max'] ?? null) !== null) {
            $query->having('valor_em_aberto_total', '<=', (float) $validated['valor_em_aberto_max']);
        }

        [$orderBy, $direction, $isRawSort] = $this->resolveSort($request);
        if ($isRawSort) {
            $query->orderByRaw(sprintf('%s %s', $orderBy, $direction));
        } else {
            $query->orderBy($orderBy, $direction);
        }
        $query->orderBy('convenio.id');

        $perPage = max(1, min((int) ($validated['per_page'] ?? 15), 200));
        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'sucesso' => true,
            'data' => [
                'results' => ConvenioResource::collection(collect($paginator->items()))->resolve(),
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'perPage' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'lastPage' => $paginator->lastPage(),
                ],
                'meta' => [
                    'orderBy' => $orderBy,
                    'direction' => $direction,
                ],
            ],
        ]);
    }

    public function filtros(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
        ]);

        $limit = (int) ($validated['limit'] ?? 50);
        $q = mb_strtolower(trim((string) ($validated['q'] ?? '')));
        $like = $q !== '' ? '%'.$q.'%' : null;

        $orgaosQuery = DB::table('orgao')
            ->select(['id', 'sigla', 'nome'])
            ->whereNull('deleted_at')
            ->orderBy('sigla')
            ->limit($limit);

        if ($like !== null) {
            $orgaosQuery->where(function ($nested) use ($like): void {
                $nested->whereRaw('LOWER(COALESCE(sigla, "")) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(nome, "")) LIKE ?', [$like]);
            });
        }

        $municipiosQuery = DB::table('municipio')
            ->select(['id', 'nome', 'uf'])
            ->orderBy('nome')
            ->limit($limit);

        if ($like !== null) {
            $municipiosQuery->whereRaw('LOWER(COALESCE(nome, "")) LIKE ?', [$like]);
        }

        $pisQuery = DB::table('convenio_plano_interno')
            ->selectRaw('MIN(id) as id, plano_interno as codigo')
            ->groupBy('plano_interno')
            ->orderBy('plano_interno')
            ->limit($limit);

        if ($like !== null) {
            $pisQuery->whereRaw('LOWER(COALESCE(plano_interno, "")) LIKE ?', [$like]);
        }

        return response()->json([
            'sucesso' => true,
            'data' => [
                'orgaos' => $orgaosQuery->get(),
                'municipios' => $municipiosQuery->get(),
                'planos_internos' => $pisQuery->get(),
            ],
        ]);
    }

    public function store(StoreConvenioRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $planosInternos = $this->extractPlanosInternos($validated);
        unset($validated['plano_interno'], $validated['planos_internos']);

        $convenio = Convenio::query()->create($validated);
        $this->syncPlanosInternos($convenio, $planosInternos);

        $convenio->load(['orgao', 'municipio.regiaoIntegracao', 'planosInternos']);
        $convenio->loadAggregate('parcelas', 'id', 'count');

        return ConvenioResource::make($convenio)->response()->setStatusCode(201);
    }

    public function show(Request $request, int $convenio): JsonResponse
    {
        $validated = $request->validate([
            'parcelas_page' => ['nullable', 'integer', 'min:1'],
            'parcelas_per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'parcela_situacao' => ['nullable', 'string', 'in:PAGA,EM_ABERTO,CANCELADA,PREVISTA'],
            'parcelas_order_by' => ['nullable', 'string', 'in:numero,data_pagamento,valor_previsto,valor_pago,situacao'],
            'parcelas_direction' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $convenioModel = Convenio::query()
            ->select('convenio.*')
            ->with([
                'orgao',
                'municipio.regiaoIntegracao',
                'planosInternos',
            ])
            ->leftJoin('municipio as municipio_beneficiario', function ($join): void {
                $join->on('municipio_beneficiario.id', '=', 'convenio.municipio_id');
            })
            ->leftJoin('orgao as orgao_rel', function ($join): void {
                $join->on('orgao_rel.id', '=', 'convenio.orgao_id')
                    ->whereNull('orgao_rel.deleted_at');
            })
            ->leftJoinSub($this->mandatoVigenteSortSubquery(), 'mandato_vigente_sort', function ($join): void {
                $join->on('mandato_vigente_sort.municipio_id', '=', 'convenio.municipio_id');
            })
            ->addSelect([
                'municipio_nome' => DB::raw('municipio_beneficiario.nome'),
                'orgao_sigla' => DB::raw('orgao_rel.sigla'),
                'prefeito_nome_sort' => DB::raw('mandato_vigente_sort.prefeito_nome'),
                'partido_sigla_sort' => DB::raw('mandato_vigente_sort.partido_sigla'),
                'mandato_consecutivo_sort' => DB::raw('mandato_vigente_sort.mandato_consecutivo'),
                'plano_interno_sort' => DB::raw('(SELECT MIN(cp.plano_interno) FROM convenio_plano_interno cp WHERE cp.convenio_id = convenio.id)'),
            ])
            ->withTrashed();

        LatestMunicipioDemografia::join($convenioModel, 'convenio.municipio_id');
        $convenioModel->addSelect([
            'populacao_ref' => DB::raw('COALESCE(md_latest.populacao, 0)'),
            'eleitores_ref' => DB::raw('COALESCE(md_latest.eleitores, 0)'),
        ]);
        $convenioModel->withParcelasAgg();

        $convenioModel = $convenioModel->whereKey($convenio)->firstOrFail();

        $parcelasQuery = $convenioModel->parcelas()->withTrashed();
        $parcelaSituacao = $validated['parcela_situacao'] ?? null;

        if ($parcelaSituacao === 'EM_ABERTO') {
            $parcelasQuery->emAberto();
        } elseif (in_array($parcelaSituacao, ['PAGA', 'CANCELADA', 'PREVISTA'], true)) {
            $parcelasQuery->where('situacao', $parcelaSituacao);
        }

        $parcelasSortMap = [
            'numero' => 'numero',
            'data_pagamento' => 'data_pagamento',
            'valor_previsto' => 'valor_previsto',
            'valor_pago' => 'valor_pago',
            'situacao' => 'situacao',
        ];
        $parcelasOrderBy = $parcelasSortMap[$validated['parcelas_order_by'] ?? 'numero'] ?? 'numero';
        $parcelasDirection = $validated['parcelas_direction'] ?? 'asc';
        $parcelasPerPage = max(1, min((int) ($validated['parcelas_per_page'] ?? 25), 200));

        $parcelasPaginator = $parcelasQuery
            ->orderBy($parcelasOrderBy, $parcelasDirection)
            ->paginate($parcelasPerPage, ['*'], 'parcelas_page', (int) ($validated['parcelas_page'] ?? 1));

        return response()->json([
            'sucesso' => true,
            'data' => [
                'convenio' => ConvenioResource::make($convenioModel)->resolve(),
                'agregados' => [
                    'total_parcelas' => (int) ($convenioModel->parcelas_total ?? 0),
                    'parcelas_pagas' => (int) ($convenioModel->parcelas_pagas ?? 0),
                    'parcelas_em_aberto' => (int) ($convenioModel->parcelas_em_aberto ?? 0),
                    'valor_total_parcelas' => number_format((float) ($convenioModel->valor_previsto_total ?? 0), 2, '.', ''),
                    'valor_pago' => number_format((float) ($convenioModel->valor_pago_total ?? 0), 2, '.', ''),
                    'valor_em_aberto' => number_format((float) ($convenioModel->valor_em_aberto_total ?? 0), 2, '.', ''),
                    'percentual_execucao' => $this->calcExecucaoPercentual(
                        (float) ($convenioModel->valor_previsto_total ?? 0),
                        (float) ($convenioModel->valor_pago_total ?? 0)
                    ),
                ],
                'parcelas' => [
                    'results' => ParcelaResource::collection(collect($parcelasPaginator->items()))->resolve(),
                    'pagination' => [
                        'page' => $parcelasPaginator->currentPage(),
                        'perPage' => $parcelasPaginator->perPage(),
                        'total' => $parcelasPaginator->total(),
                        'lastPage' => $parcelasPaginator->lastPage(),
                    ],
                ],
            ],
        ]);
    }

    public function update(UpdateConvenioRequest $request, Convenio $convenio): ConvenioResource
    {
        $validated = $request->validated();
        $planosInternos = $this->extractPlanosInternos($validated);
        unset($validated['plano_interno'], $validated['planos_internos']);

        $convenio->fill($validated);
        $convenio->save();
        $this->syncPlanosInternos($convenio, $planosInternos);

        $convenio->load(['orgao', 'municipio.regiaoIntegracao', 'planosInternos']);
        $convenio->loadAggregate('parcelas', 'id', 'count');

        return ConvenioResource::make($convenio);
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

        $convenio->load(['orgao', 'municipio.regiaoIntegracao', 'planosInternos']);
        $convenio->loadAggregate('parcelas', 'id', 'count');

        return ConvenioResource::make($convenio);
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

    private function buildConvenioIndexQuery(Request $request)
    {
        $query = Convenio::query()
            ->select('convenio.*')
            ->with([
                'orgao',
                'municipio.regiaoIntegracao',
                'planosInternos',
            ])
            ->leftJoin('municipio as municipio_beneficiario', function ($join): void {
                $join->on('municipio_beneficiario.id', '=', 'convenio.municipio_id');
            })
            ->leftJoin('orgao as orgao_rel', function ($join): void {
                $join->on('orgao_rel.id', '=', 'convenio.orgao_id')
                    ->whereNull('orgao_rel.deleted_at');
            })
            ->leftJoinSub($this->mandatoVigenteSortSubquery(), 'mandato_vigente_sort', function ($join): void {
                $join->on('mandato_vigente_sort.municipio_id', '=', 'convenio.municipio_id');
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

        LatestMunicipioDemografia::join($query, 'convenio.municipio_id');
        $query->addSelect([
            'populacao_ref' => DB::raw('COALESCE(md_latest.populacao, 0)'),
            'eleitores_ref' => DB::raw('COALESCE(md_latest.eleitores, 0)'),
        ]);
        $query->withParcelasAgg();

        return $query;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function applyPlanoInternoFilters($query, array $validated): void
    {
        $piCodes = collect($validated['pi'] ?? [])
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();

        $singlePi = trim((string) ($validated['pi_codigo'] ?? $validated['plano_interno'] ?? ''));
        if ($singlePi !== '') {
            $piCodes[] = strtoupper($singlePi);
        }

        $piCodes = array_values(array_unique($piCodes));
        if ($piCodes === []) {
            return;
        }

        $query->whereExists(function ($subquery) use ($piCodes): void {
            $subquery->selectRaw('1')
                ->from('convenio_plano_interno as cp')
                ->whereColumn('cp.convenio_id', 'convenio.id')
                ->whereIn('cp.plano_interno', $piCodes);
        });
    }

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private function resolveSort(Request $request): array
    {
        $sortableFields = [
            'updated_at' => 'convenio.updated_at',
            'numero_convenio' => 'convenio.numero_convenio',
            'municipio_nome' => 'municipio_nome',
            'orgao_sigla' => 'orgao_sigla',
            'plano_interno' => 'plano_interno_sort',
            'data_inicio' => 'convenio.data_inicio',
            'data_fim' => 'convenio.data_fim',
            'valor_total' => 'COALESCE(convenio.valor_total_calculado, convenio.valor_total_informado, 0)',
            'valor_em_aberto' => 'valor_em_aberto_total',
            'parcelas_em_aberto' => 'parcelas_em_aberto',
        ];
        $rawSortFields = ['valor_total'];

        $legacySort = trim((string) $request->query('sort', ''));
        if ($legacySort !== '') {
            $direction = str_starts_with($legacySort, '-') ? 'desc' : 'asc';
            $field = ltrim($legacySort, '-');
            if (isset($sortableFields[$field])) {
                return [$sortableFields[$field], $direction, in_array($field, $rawSortFields, true)];
            }
        }

        $orderBy = (string) $request->query('orderBy', 'updated_at');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! isset($sortableFields[$orderBy])) {
            $orderBy = 'updated_at';
            $direction = 'desc';
        }

        return [$sortableFields[$orderBy], $direction, in_array($orderBy, $rawSortFields, true)];
    }

    private function calcExecucaoPercentual(float $valorTotalParcelas, float $valorPago): string
    {
        if ($valorTotalParcelas <= 0) {
            return '0.00';
        }

        $percentual = max(0, min(100, ($valorPago / $valorTotalParcelas) * 100));

        return number_format($percentual, 2, '.', '');
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
                'created_at' => now(),
                'updated_at' => now(),
            ]
        )->all();

        ConvenioPlanoInterno::query()->insert($payload);
    }
}
