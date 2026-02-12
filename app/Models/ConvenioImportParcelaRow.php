<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvenioImportParcelaRow extends Model
{
    use HasFactory;

    protected $table = 'convenio_import_parcelas_rows';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'normalized_data' => 'array',
            'issues' => 'array',
            'processed_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ConvenioImport::class, 'import_id');
    }
}

