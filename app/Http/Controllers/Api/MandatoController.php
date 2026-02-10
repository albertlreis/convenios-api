<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMandatoRequest;
use App\Http\Requests\UpdateMandatoRequest;
use App\Http\Resources\MandatoResource;
use App\Models\MandatoPrefeito;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MandatoController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        $mandatos = MandatoPrefeito::query()
            ->with(['municipio', 'prefeito', 'partido', 'eleicao'])
            ->orderByDesc('inicio')
            ->paginate($perPage)
            ->withQueryString();

        return MandatoResource::collection($mandatos);
    }

    public function store(StoreMandatoRequest $request): JsonResponse
    {
        $mandato = MandatoPrefeito::query()->create($request->validated());
        $mandato->load(['municipio', 'prefeito', 'partido', 'eleicao']);

        return MandatoResource::make($mandato)->response()->setStatusCode(201);
    }

    public function show(MandatoPrefeito $mandato): MandatoResource
    {
        $mandato->load(['municipio', 'prefeito', 'partido', 'eleicao']);

        return MandatoResource::make($mandato);
    }

    public function update(UpdateMandatoRequest $request, MandatoPrefeito $mandato): MandatoResource
    {
        $mandato->fill($request->validated());
        $mandato->save();

        return $this->show($mandato);
    }

    public function destroy(MandatoPrefeito $mandato): JsonResponse
    {
        $mandato->delete();

        return response()->json(status: 204);
    }
}
