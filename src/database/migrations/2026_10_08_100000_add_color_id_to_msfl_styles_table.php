<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A style's color (Inventory inv_colors — soft reference, no FK). Order PO lines take
 * the color from the style (one color per style).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_styles', function (Blueprint $table) {
            $table->unsignedBigInteger('color_id')->nullable()->after('product_type_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('msfl_styles', function (Blueprint $table) {
            $table->dropIndex(['color_id']);
            $table->dropColumn('color_id');
        });
    }
};
