<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production entries get a kind (Production → Reject & Rework screen):
 *   production — pieces in + QC (the stage screens)
 *   qc         — reject / rework found among pieces the stage already passed
 *   rework     — rework pieces fixed: pass / reject
 * Existing rows are production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_prod_entries', function (Blueprint $table) {
            $table->string('kind', 12)->default('production')->after('stage');
        });
    }

    public function down(): void
    {
        Schema::table('msfl_prod_entries', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
