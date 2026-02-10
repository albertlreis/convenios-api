<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMunicipioDemografiaRequest;
use App\Http\Requests\UpdateMunicipioDemografiaRequest;
use App\Http\Resources\MunicipioDemografiaResource;
use App\Models\MunicipioDemografia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MunicipioDemografiaController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        $demografias = MunicipioDemografia::query()
            ->with('municipio')
            ->orderByDesc('ano_ref')
            ->paginate($perPage)
            ->withQueryString();

        return MunicipioDemografiaResource::collection($demografias);
    }

    public function store(StoreMunicipioDemografiaRequest $request): JsonResponse
    {
        $demografia = MunicipioDemografia::query()->create($request->validated());
        $demografia->load('municipio');

        return MunicipioDemografiaResource::make($demografia)->response()->setStatusCode(201);
    }

    public function show(MunicipioDemografia $municipioDemografia): MunicipioDemografiaResource
    {
        $municipioDemografia->load('municipio');

        return MunicipioDemografiaResource::make($municipioDemografia);
    }

    public function update(UpdateMunicipioDemografiaRequest $request, MunicipioDemografia $municipioDemografia): MunicipioDemografiaResource
    {
        $municipioDemografia->fill($request->validated());
        $municipioDemografia->save();

        return $this->show($municipioDemografia);
    }

    public function destroy(MunicipioDemografia $municipioDemografia): JsonResponse
    {
        $municipioDemografia->delete();

        return response()->json(status: 204);
    }
}
