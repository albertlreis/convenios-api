<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConvenioImport extends Model
{
    use HasFactory;

    protected $table = 'convenio_imports';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'resumo' => 'array',
            'confirmado_em' => 'datetime:Y-m-d H:i:s',
        ];
    }

    public function listaRows(): HasMany
    {
        return $this->hasMany(ConvenioImportListaRow::class, 'import_id');
    }

    public function parcelasRows(): HasMany
    {
        return $this->hasMany(ConvenioImportParcelaRow::class, 'import_id');
    }

    public function piRows(): HasMany
    {
        return $this->hasMany(ConvenioImportPiRow::class, 'import_id');
    }

    public function pendingItems(): HasMany
    {
        return $this->hasMany(ConvenioImportPendingItem::class, 'import_id');
    }
}

