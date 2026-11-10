<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What is being done about a late T&A step — typed on the T&A page, shown on the T&A Milestone report. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('msfl_tna_tasks', 'corrective_action')) {
            Schema::table('msfl_tna_tasks', function (Blueprint $table) {
                $table->string('corrective_action', 500)->nullable()->after('remarks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('msfl_tna_tasks', 'corrective_action')) {
            Schema::table('msfl_tna_tasks', function (Blueprint $table) {
                $table->dropColumn('corrective_action');
            });
        }
    }
};
