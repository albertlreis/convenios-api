<?php

namespace App\Http\Requests;

class UploadConvenioImportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ];
    }
}

