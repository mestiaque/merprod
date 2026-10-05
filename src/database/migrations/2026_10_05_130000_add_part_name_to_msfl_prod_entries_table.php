<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part-wise production: embroidery works on cut parts (Front, Back …) — sent from
 * cutting part by part and passed back to cutting — and cutting / embroidery QC
 * and rework name the part. Other stages leave it null (whole garment).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_prod_entries', function (Blueprint $table) {
            $table->string('part_name', 100)->nullable()->after('size_id');
        });
    }

    public function down(): void
    {
        Schema::table('msfl_prod_entries', function (Blueprint $table) {
            $table->dropColumn('part_name');
        });
    }
};
