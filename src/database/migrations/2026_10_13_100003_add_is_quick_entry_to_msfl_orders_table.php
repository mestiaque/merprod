<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks an order that skipped the full Bulletin/T&A/BOM planning flow — e.g.
 * an old/legacy order caught up directly in Production for delivery, with no
 * historical SMV/Bulletin data to re-create. Purely informational: nothing
 * elsewhere in the system requires this to be false, and no behaviour
 * changes based on it — it only shows a badge so these stay easy to spot
 * (and exclude from SMV/capacity reports later) once the full flow is the
 * normal path for new orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_orders', function (Blueprint $table) {
            $table->boolean('is_quick_entry')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('msfl_orders', function (Blueprint $table) {
            $table->dropColumn('is_quick_entry');
        });
    }
};
