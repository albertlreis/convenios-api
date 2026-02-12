<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\PlanoInternoFinanc;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class FinanceiroController extends Controller
{
    public function showByConvenio(Convenio $convenio): JsonResponse
    {
        $pi = trim((string) ($convenio->planosInternos()->orderBy('id')->value('plano_interno') ?? ''));

        if ($pi === '') {
            return response()->json([
                'data' => null,
                'warning' => 'Sem PI',
            ]);
        }

        return $this->responseByPi($pi);
    }

    public function showByPi(string $pi): JsonResponse
    {
        $validator = Validator::make(
            ['pi' => $pi],
            ['pi' => ['required', 'regex:/^[A-Za-z0-9]{11}$/']]
        );

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return $this->responseByPi($pi);
    }

    private function responseByPi(string $pi): JsonResponse
    {
        try {
            $registro = PlanoInternoFinanc::query()
                ->where('pif_PI', $pi)
                ->orderByDesc('pif_ano')
                ->orderByDesc('Geracao')
                ->first();
        } catch (QueryException $exception) {
            return response()->json([
                'message' => 'Erro ao consultar SQL Server financeiro.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'data' => $registro?->getAttributes(),
        ]);
    }
}
