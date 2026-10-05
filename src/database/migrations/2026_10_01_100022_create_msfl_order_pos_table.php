<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_order_pos', function (Blueprint $table) {
            $table->id();
            // One row = one PO + one style + one color.
            $table->foreignId('order_id')->constrained('msfl_orders')->cascadeOnDelete();
            $table->foreignId('style_id')->constrained('msfl_styles')->restrictOnDelete();
            $table->foreignId('color_id')->constrained('msfl_colors')->restrictOnDelete();
            $table->string('po_no', 100);
            $table->unsignedInteger('po_qty')->default(0); // cached sum of size qty
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('total_value', 15, 4)->default(0);
            $table->date('pcd_date')->nullable();
            $table->date('shipment_date')->nullable();
            $table->foreignId('ship_mode_id')->nullable()->constrained('msfl_ship_modes')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->unique(['order_id', 'po_no', 'style_id', 'color_id'], 'msfl_order_pos_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_order_pos');
    }
};
