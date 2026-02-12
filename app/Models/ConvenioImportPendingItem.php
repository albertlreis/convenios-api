<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConvenioImportPendingItem extends Model
{
    use HasFactory;

    protected $table = 'convenio_import_pending_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'resolved_at' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ConvenioImport::class, 'import_id');
    }
}

