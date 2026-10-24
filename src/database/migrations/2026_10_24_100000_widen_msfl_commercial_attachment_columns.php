<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial records keep several document files: `attachment` now holds a JSON list of
 * {path, name} (FileUploadService::list / storeList), too long for a 255-char string.
 * Existing single paths stay valid and read as one file.
 */
return new class extends Migration
{
    private const TABLES = [
        'msfl_com_export_lcs', 'msfl_com_supplier_pis', 'msfl_com_btb_lcs',
        'msfl_com_import_shipments', 'msfl_com_export_documents', 'msfl_com_realizations',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->text('attachment')->nullable()->change());
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('attachment')->nullable()->change());
        }
    }
};
