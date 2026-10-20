<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Critical / Major / Minor per reject / rework row — feeds the Daily Quality DHU & RFT report. Older rows stay null (unclassified). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_prod_entry_defects', function (Blueprint $table) {
            $table->string('severity', 10)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('msfl_prod_entry_defects', function (Blueprint $table) {
            $table->dropColumn('severity');
        });
    }
};
