<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('msfl_boms')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('msfl_items')->restrictOnDelete();
            $table->string('item_type', 20); // snapshot of msfl_items.type
            // Blank color / size = the line applies to every color / size of the order.
            $table->foreignId('color_id')->nullable()->constrained('msfl_colors')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->constrained('msfl_sizes')->nullOnDelete();
            $table->string('placement')->nullable(); // garment part / where it is used
            $table->decimal('consumption', 14, 4); // per piece, in uom
            $table->foreignId('uom_id')->nullable()->constrained('msfl_uoms')->nullOnDelete();
            $table->decimal('wastage_percent', 5, 2)->default(0);
            $table->decimal('rate', 12, 4)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('msfl_suppliers')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_bom_items');
    }
};
