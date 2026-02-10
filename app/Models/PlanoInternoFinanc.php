<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanoInternoFinanc extends Model
{
    protected $connection = 'sqlsrv_financ';

    protected $table = 'gp.PlanoInternoFinanc';

    protected $guarded = [];

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;
}
