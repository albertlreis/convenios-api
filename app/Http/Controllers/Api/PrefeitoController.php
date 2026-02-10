<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePrefeitoRequest;
use App\Http\Requests\UpdatePrefeitoRequest;
use App\Http\Resources\PrefeitoResource;
use App\Models\Prefeito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrefeitoController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        $prefeitos = Prefeito::query()
            ->orderBy('nome_completo')
            ->paginate($perPage)
            ->withQueryString();

        return PrefeitoResource::collection($prefeitos);
    }

    public function store(StorePrefeitoRequest $request): JsonResponse
    {
        $prefeito = Prefeito::query()->create($request->validated());

        return PrefeitoResource::make($prefeito)->response()->setStatusCode(201);
    }

    public function show(Prefeito $prefeito): PrefeitoResource
    {
        $prefeito->load('mandatos');

        return PrefeitoResource::make($prefeito);
    }

    public function update(UpdatePrefeitoRequest $request, Prefeito $prefeito): PrefeitoResource
    {
        $prefeito->fill($request->validated());
        $prefeito->save();

        return $this->show($prefeito);
    }

    public function destroy(Prefeito $prefeito): JsonResponse
    {
        $prefeito->delete();

        return response()->json(status: 204);
    }
}
