<?php

use App\Http\Controllers\Api\ConvenioController;
use App\Http\Controllers\Api\ConvenioIndicadoresController;
use App\Http\Controllers\Api\FinanceiroController;
use App\Http\Controllers\Api\MandatoController;
use App\Http\Controllers\Api\MunicipioController;
use App\Http\Controllers\Api\MunicipioDemografiaController;
use App\Http\Controllers\Api\MunicipioIndicadoresController;
use App\Http\Controllers\Api\OrgaoController;
use App\Http\Controllers\Api\ParcelaController;
use App\Http\Controllers\Api\PartidoController;
use App\Http\Controllers\Api\PrefeitoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', fn () => ['status' => 'ok']);

    Route::get('convenios/indicadores/quantidade-com-parcelas-em-aberto', [ConvenioIndicadoresController::class, 'quantidadeComParcelasEmAberto']);
    Route::get('convenios/indicadores/valores-em-aberto', [ConvenioIndicadoresController::class, 'valoresEmAberto']);
    Route::get('convenios/indicadores/populacao-atendida', [ConvenioIndicadoresController::class, 'populacaoAtendida']);
    Route::get('convenios/indicadores/eleitores-atendidos', [ConvenioIndicadoresController::class, 'eleitoresAtendidos']);
    Route::get('convenios/{convenio}/parcelas', [ConvenioController::class, 'parcelas']);
    Route::get('convenios/{convenio}/parcelas-em-aberto', [ConvenioController::class, 'parcelasEmAberto']);
    Route::get('convenios/{convenio}/financeiro', [FinanceiroController::class, 'showByConvenio']);
    Route::match(['post', 'patch'], 'convenios/{convenio}/restore', [ConvenioController::class, 'restore']);
    Route::apiResource('convenios', ConvenioController::class)->parameters([
        'convenios' => 'convenio',
    ]);

    Route::patch('parcelas/{parcela}/pagamento', [ParcelaController::class, 'patchPagamento']);
    Route::match(['post', 'patch'], 'parcelas/{parcela}/restore', [ParcelaController::class, 'restore']);
    Route::apiResource('parcelas', ParcelaController::class)->parameters([
        'parcelas' => 'parcela',
    ]);

    Route::get('municipios/indicadores/populacao-por-regiao', [MunicipioIndicadoresController::class, 'populacaoPorRegiao']);
    Route::get('municipios/indicadores/eleitores-por-regiao', [MunicipioIndicadoresController::class, 'eleitoresPorRegiao']);
    Route::apiResource('municipios', MunicipioController::class)->parameters([
        'municipios' => 'municipio',
    ]);
    Route::apiResource('municipio-demografias', MunicipioDemografiaController::class)->parameters([
        'municipio-demografias' => 'municipioDemografia',
    ]);

    Route::match(['post', 'patch'], 'orgaos/{orgao}/restore', [OrgaoController::class, 'restore']);
    Route::apiResource('orgaos', OrgaoController::class)->parameters([
        'orgaos' => 'orgao',
    ]);
    Route::apiResource('prefeitos', PrefeitoController::class)->parameters([
        'prefeitos' => 'prefeito',
    ]);
    Route::apiResource('mandatos', MandatoController::class)->parameters([
        'mandatos' => 'mandato',
    ]);
    Route::apiResource('partidos', PartidoController::class)->parameters([
        'partidos' => 'partido',
    ]);

    Route::get('financeiro/pi/{pi}', [FinanceiroController::class, 'showByPi']);
});
