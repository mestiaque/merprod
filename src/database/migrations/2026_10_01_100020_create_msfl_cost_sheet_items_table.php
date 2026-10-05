<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_cost_sheet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_sheet_id')->constrained('msfl_cost_sheets')->cascadeOnDelete();
            $table->string('group', 20); // fabric, trims, process
            $table->foreignId('item_id')->nullable()->constrained('msfl_items')->nullOnDelete();
            $table->string('description')->nullable();
            $table->foreignId('uom_id')->nullable()->constrained('msfl_uoms')->nullOnDelete();
            $table->decimal('consumption', 14, 4)->default(0); // per dozen
            $table->decimal('rate', 14, 4)->default(0);
            $table->decimal('amount', 15, 4)->default(0); // consumption x rate, per dozen
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_cost_sheet_items');
    }
};
