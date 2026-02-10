<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Orgao extends Model
{
    /** @use HasFactory<\Database\Factories\OrgaoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'orgao';

    protected $guarded = [];

    public function convenios(): HasMany
    {
        return $this->hasMany(Convenio::class, 'orgao_id');
    }
}
