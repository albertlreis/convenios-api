<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PatchParcelaPagamentoRequest;
use App\Http\Requests\StoreParcelaRequest;
use App\Http\Requests\UpdateParcelaRequest;
use App\Http\Resources\ParcelaResource;
use App\Models\Parcela;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParcelaController extends Controller
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->query('per_page', 15), 200));

        $parcelas = Parcela::query()
            ->with('convenio')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return ParcelaResource::collection($parcelas);
    }

    public function store(StoreParcelaRequest $request): JsonResponse
    {
        $parcela = Parcela::query()->create($request->validated());
        $parcela->load('convenio');

        return ParcelaResource::make($parcela)->response()->setStatusCode(201);
    }

    public function show(Parcela $parcela): ParcelaResource
    {
        $parcela->load('convenio');

        return ParcelaResource::make($parcela);
    }

    public function update(UpdateParcelaRequest $request, Parcela $parcela): ParcelaResource
    {
        $parcela->fill($request->validated());
        $parcela->save();

        return $this->show($parcela);
    }

    public function destroy(Parcela $parcela): JsonResponse
    {
        $parcela->delete();

        return response()->json(status: 204);
    }

    public function patchPagamento(PatchParcelaPagamentoRequest $request, Parcela $parcela): ParcelaResource
    {
        $data = $request->validated();

        $parcela->fill($data);

        if (! array_key_exists('situacao', $data)) {
            $dataPagamento = $data['data_pagamento'] ?? $parcela->data_pagamento;
            $valorPrevisto = array_key_exists('valor_previsto', $data) ? $data['valor_previsto'] : $parcela->valor_previsto;
            $valorPago = array_key_exists('valor_pago', $data) ? $data['valor_pago'] : $parcela->valor_pago;

            if (! empty($dataPagamento) && $valorPrevisto !== null && $valorPago !== null && (float) $valorPago >= (float) $valorPrevisto) {
                $parcela->situacao = 'PAGA';
            }
        }

        $parcela->save();

        return $this->show($parcela);
    }
}
