<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMunicipioRequest;
use App\Http\Requests\UpdateMunicipioRequest;
use App\Http\Resources\MunicipioResource;
use App\Models\Municipio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MunicipioController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        $municipios = Municipio::query()
            ->with(['regiaoIntegracao'])
            ->orderBy('nome')
            ->paginate($perPage)
            ->withQueryString();

        return MunicipioResource::collection($municipios);
    }

    public function store(StoreMunicipioRequest $request): JsonResponse
    {
        $municipio = Municipio::query()->create($request->validated());
        $municipio->load('regiaoIntegracao');

        return MunicipioResource::make($municipio)->response()->setStatusCode(201);
    }

    public function show(Municipio $municipio): MunicipioResource
    {
        $municipio->load(['regiaoIntegracao', 'demografias']);

        return MunicipioResource::make($municipio);
    }

    public function update(UpdateMunicipioRequest $request, Municipio $municipio): MunicipioResource
    {
        $municipio->fill($request->validated());
        $municipio->save();

        return $this->show($municipio);
    }

    public function destroy(Municipio $municipio): JsonResponse
    {
        $municipio->delete();

        return response()->json(status: 204);
    }
}
