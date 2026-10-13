<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Section" on an Operations Library entry (e.g. "Slv & Cuff", "Back & Front
 * Part") — reuses the existing Garment Parts master instead of a new one, so
 * entries stay dynamic/admin-addable without duplicating master data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_operations', function (Blueprint $table) {
            $table->foreignId('garment_part_id')->nullable()->after('machine_type_id')
                ->constrained('msfl_garment_parts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('msfl_operations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('garment_part_id');
        });
    }
};
