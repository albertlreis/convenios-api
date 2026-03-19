<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmConvenioImportRequest;
use App\Http\Requests\UploadConvenioImportRequest;
use App\Http\Resources\ConvenioImportResource;
use App\Models\ConvenioImport;
use App\Services\ConvenioImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ConvenioImportController extends Controller
{
    public function __construct(private readonly ConvenioImportService $service)
    {
    }

    public function upload(UploadConvenioImportRequest $request): JsonResponse
    {
        $files = $request->importFiles();
        if ($files === []) {
            throw ValidationException::withMessages([
                'arquivo' => ['Envie ao menos um arquivo XLSX.'],
            ]);
        }

        if ($request->isMultiUploadRequest()) {
            $results = $this->service->uploadAndParseMany($files);
            $hasErrors = collect($results)->contains(fn (array $item): bool => ($item['status'] ?? null) === 'ERRO');

            return response()->json([
                'sucesso' => true,
                'mensagem' => 'Arquivos processados para staging.',
                'data' => $results,
            ], $hasErrors ? 207 : 201);
        }

        $import = $this->service->uploadAndParse($files[0], ['lista', 'parcelas', 'plano_interno']);

        return $this->asJson($this->loadPreview($import), 'Arquivo importado para staging.', 201);
    }

    public function uploadPi(UploadConvenioImportRequest $request): JsonResponse
    {
        $file = $request->file('arquivo');
        if (! $file) {
            throw ValidationException::withMessages([
                'arquivo' => ['Envie um arquivo no campo "arquivo" para importacao de PI por orgao.'],
            ]);
        }

        $import = $this->service->uploadAndParse($file, ['plano_interno']);
        $sheetFound = (bool) data_get($import->resumo, 'sheets.plano_interno.encontrada', false);
        if (! $sheetFound) {
            throw ValidationException::withMessages([
                'arquivo' => ['A planilha deve conter a aba "plano_interno".'],
            ]);
        }

        $import->update([
            'resumo' => array_merge($import->resumo ?? [], [
                'tipo' => 'plano_interno_por_orgao',
            ]),
        ]);

        return $this->asJson($this->loadPreview($import->fresh()), 'Arquivo PI por orgao importado para staging.', 201);
    }

    public function index(): JsonResponse
    {
        $imports = ConvenioImport::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'sucesso' => true,
            'mensagem' => 'Historico de importacoes.',
            'data' => $imports->map(fn (ConvenioImport $import) => ConvenioImportResource::make($import)->resolve()),
        ]);
    }

    public function confirm(ConfirmConvenioImportRequest $request): JsonResponse
    {
        $import = ConvenioImport::query()->findOrFail($request->integer('import_id'));
        $batchSize = max(50, min((int) $request->input('batch_size', 500), 2000));

        $import = $this->service->confirmImport($import, $batchSize);

        return $this->asJson($this->loadPreview($import), 'Importacao confirmada com processamento tolerante.');
    }

    public function show(int $id): JsonResponse
    {
        $import = ConvenioImport::query()->findOrFail($id);

        return $this->asJson($this->loadPreview($import), 'Status da importacao.');
    }

    public function confirmPi(Request $request, ConvenioImport $import): JsonResponse
    {
        $sync = filter_var($request->query('sync', '1'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $batchSize = max(50, min((int) $request->query('batch_size', 500), 2000));
        $import = $this->service->confirmPlanoInternoPorOrgao($import, $sync ?? true, $batchSize);

        return $this->asJson($this->loadPreview($import), 'Importacao de PI por orgao confirmada.');
    }

    private function loadPreview(ConvenioImport $import): ConvenioImport
    {
        return $import->load([
            'listaRows' => fn ($query) => $query->orderBy('row_number')->limit(20),
            'parcelasRows' => fn ($query) => $query->orderBy('row_number')->limit(20),
            'piRows' => fn ($query) => $query->orderBy('row_number')->limit(20),
            'pendingItems' => fn ($query) => $query->latest('id')->limit(50),
        ]);
    }

    private function asJson(ConvenioImport $import, string $mensagem, int $status = 200): JsonResponse
    {
        return response()->json([
            'sucesso' => true,
            'mensagem' => $mensagem,
            'data' => ConvenioImportResource::make($import)->resolve(),
        ], $status);
    }
}
