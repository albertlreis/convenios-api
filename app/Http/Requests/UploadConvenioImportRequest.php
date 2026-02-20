<?php

namespace App\Http\Requests;

use Illuminate\Http\UploadedFile;

class UploadConvenioImportRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'arquivo' => ['nullable', 'file', 'max:20480', 'required_without:files'],
            'files' => ['nullable', 'array', 'min:1', 'required_without:arquivo'],
            'files.*' => ['file', 'max:20480'],
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function importFiles(): array
    {
        $multiFiles = $this->file('files', []);
        if (! is_array($multiFiles)) {
            $multiFiles = $multiFiles !== null ? [$multiFiles] : [];
        }

        $files = array_values(array_filter($multiFiles, fn (mixed $file): bool => $file instanceof UploadedFile));
        if ($files !== []) {
            return $files;
        }

        $single = $this->file('arquivo');

        return $single instanceof UploadedFile ? [$single] : [];
    }

    public function isMultiUploadRequest(): bool
    {
        return $this->hasFile('files');
    }
}
