<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartidoRequest;
use App\Http\Requests\UpdatePartidoRequest;
use App\Http\Resources\PartidoResource;
use App\Models\Partido;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartidoController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        $partidos = Partido::query()
            ->orderBy('sigla')
            ->paginate($perPage)
            ->withQueryString();

        return PartidoResource::collection($partidos);
    }

    public function store(StorePartidoRequest $request): JsonResponse
    {
        $partido = Partido::query()->create($request->validated());

        return PartidoResource::make($partido)->response()->setStatusCode(201);
    }

    public function show(Partido $partido): PartidoResource
    {
        $partido->load('mandatos');

        return PartidoResource::make($partido);
    }

    public function update(UpdatePartidoRequest $request, Partido $partido): PartidoResource
    {
        $partido->fill($request->validated());
        $partido->save();

        return $this->show($partido);
    }

    public function destroy(Partido $partido): JsonResponse
    {
        $partido->delete();

        return response()->json(status: 204);
    }
}
