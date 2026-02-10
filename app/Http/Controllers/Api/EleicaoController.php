<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEleicaoRequest;
use App\Http\Requests\UpdateEleicaoRequest;
use App\Http\Resources\EleicaoResource;
use App\Models\Eleicao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EleicaoController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        $eleicoes = Eleicao::query()
            ->with('municipio')
            ->orderByDesc('ano_eleicao')
            ->paginate($perPage)
            ->withQueryString();

        return EleicaoResource::collection($eleicoes);
    }

    public function store(StoreEleicaoRequest $request): JsonResponse
    {
        $eleicao = Eleicao::query()->create($request->validated());
        $eleicao->load('municipio');

        return EleicaoResource::make($eleicao)->response()->setStatusCode(201);
    }

    public function show(Eleicao $eleicao): EleicaoResource
    {
        $eleicao->load('municipio');

        return EleicaoResource::make($eleicao);
    }

    public function update(UpdateEleicaoRequest $request, Eleicao $eleicao): EleicaoResource
    {
        $eleicao->fill($request->validated());
        $eleicao->save();

        return $this->show($eleicao);
    }

    public function destroy(Eleicao $eleicao): JsonResponse
    {
        $eleicao->delete();

        return response()->json(status: 204);
    }
}
