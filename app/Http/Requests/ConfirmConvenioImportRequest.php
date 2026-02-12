<?php

namespace App\Http\Requests;

class ConfirmConvenioImportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'import_id' => ['required', 'integer', 'exists:convenio_imports,id'],
            'batch_size' => ['nullable', 'integer', 'min:50', 'max:2000'],
        ];
    }
}

