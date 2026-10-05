<?php

namespace ME\MerchandisingSfl\Models\Production;

use Illuminate\Database\Eloquent\Model;

class CuttingPart extends Model
{
    public $timestamps = false;

    protected $table = 'msfl_prod_cutting_parts';

    protected $fillable = ['cutting_id', 'part_name', 'qty'];
}
