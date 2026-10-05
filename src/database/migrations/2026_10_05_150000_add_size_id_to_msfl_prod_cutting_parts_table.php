<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Parts cut per size (Front S 100, Front M 150 …) — a product is buyer + style + color + size. Size = inv_sizes (no FK). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_prod_cutting_parts', function (Blueprint $table) {
            $table->unsignedBigInteger('size_id')->nullable()->index()->after('part_name');
        });
    }

    public function down(): void
    {
        Schema::table('msfl_prod_cutting_parts', function (Blueprint $table) {
            $table->dropColumn('size_id');
        });
    }
};
