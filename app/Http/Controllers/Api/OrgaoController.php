<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrgaoRequest;
use App\Http\Requests\UpdateOrgaoRequest;
use App\Http\Resources\OrgaoResource;
use App\Models\Orgao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrgaoController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        $query = Orgao::query();

        if ($request->boolean('only_trashed')) {
            $query->onlyTrashed();
        } elseif ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $orgaos = $query
            ->orderBy('sigla')
            ->paginate($perPage)
            ->withQueryString();

        return OrgaoResource::collection($orgaos);
    }

    public function store(StoreOrgaoRequest $request): JsonResponse
    {
        $orgao = Orgao::query()->create($request->validated());

        return OrgaoResource::make($orgao)->response()->setStatusCode(201);
    }

    public function show(int $orgao): OrgaoResource
    {
        $orgao = Orgao::query()
            ->withTrashed()
            ->whereKey($orgao)
            ->firstOrFail();
        $orgao->load('convenios');

        return OrgaoResource::make($orgao);
    }

    public function update(UpdateOrgaoRequest $request, Orgao $orgao): OrgaoResource
    {
        $orgao->fill($request->validated());
        $orgao->save();

        return $this->show($orgao->id);
    }

    public function destroy(Orgao $orgao): JsonResponse
    {
        $orgao->delete();

        return response()->json(status: 204);
    }

    public function restore(int $orgao): OrgaoResource
    {
        $orgao = Orgao::query()
            ->withTrashed()
            ->findOrFail($orgao);

        if ($orgao->trashed()) {
            $orgao->restore();
        }

        return $this->show($orgao->id);
    }
}
