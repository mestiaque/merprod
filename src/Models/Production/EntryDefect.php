<?php

namespace ME\MerchandisingSfl\Models\Production;

use Illuminate\Database\Eloquent\Model;

class EntryDefect extends Model
{
    public $timestamps = false;

    protected $table = 'msfl_prod_entry_defects';

    protected $fillable = ['entry_id', 'type', 'part_name', 'machine_id', 'defect', 'qty'];

    /** Inventory machine name/code (machines are entered once, in Inventory). */
    public function machineLabel(): ?string
    {
        if (! $this->machine_id) {
            return null;
        }
        $m = \Illuminate\Support\Facades\DB::table('inv_machines')->where('id', $this->machine_id)->first(['name', 'code']);

        return $m ? trim($m->code . ' — ' . $m->name) : null;
    }
}
