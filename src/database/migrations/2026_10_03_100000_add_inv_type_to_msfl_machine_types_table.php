<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machines are entered once, in Inventory. A machine type here is linked to
 * Inventory's machine `type` text so line machine counts can be read from
 * there instead of being typed again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_machine_types', function (Blueprint $table) {
            $table->string('inv_type', 150)->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('msfl_machine_types', function (Blueprint $table) {
            $table->dropIndex(['inv_type']);
            $table->dropColumn('inv_type');
        });
    }
};
