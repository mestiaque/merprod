<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Final Packing List: invoice no typed by hand until a Commercial Invoice exists for the PO. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_prod_final_packing_lists', function (Blueprint $table) {
            $table->string('invoice_no', 100)->nullable()->after('pack_date');
        });
    }

    public function down(): void
    {
        Schema::table('msfl_prod_final_packing_lists', function (Blueprint $table) {
            $table->dropColumn('invoice_no');
        });
    }
};
