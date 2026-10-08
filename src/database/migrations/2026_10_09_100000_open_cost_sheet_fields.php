<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Open Cost Sheet layout: price type, CM from SMV × CPM ÷ efficiency, and the
 * supplier of each cost line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_cost_sheets', function (Blueprint $table) {
            $table->string('price_type', 10)->nullable()->after('currency_id');
            $table->decimal('cm_minute_rate', 10, 4)->nullable()->after('smv');
            $table->decimal('efficiency_percent', 6, 2)->nullable()->after('cm_minute_rate');
        });
        Schema::table('msfl_cost_sheet_items', function (Blueprint $table) {
            $table->string('supplier_name', 150)->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('msfl_cost_sheets', function (Blueprint $table) {
            $table->dropColumn(['price_type', 'cm_minute_rate', 'efficiency_percent']);
        });
        Schema::table('msfl_cost_sheet_items', function (Blueprint $table) {
            $table->dropColumn('supplier_name');
        });
    }
};
