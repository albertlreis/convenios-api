<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipio extends Model
{
    /** @use HasFactory<\Database\Factories\MunicipioFactory> */
    use HasFactory;

    protected $table = 'municipio';

    protected $guarded = [];

    public function regiaoIntegracao(): BelongsTo
    {
        return $this->belongsTo(RegiaoIntegracao::class, 'regiao_id');
    }

    public function conveniosBeneficiarios(): HasMany
    {
        return $this->hasMany(Convenio::class, 'municipio_id');
    }

    public function convenios(): HasMany
    {
        return $this->conveniosBeneficiarios();
    }

    public function mandatos(): HasMany
    {
        return $this->hasMany(MandatoPrefeito::class, 'municipio_id');
    }

    public function demografias(): HasMany
    {
        return $this->hasMany(MunicipioDemografia::class, 'municipio_id');
    }
}
