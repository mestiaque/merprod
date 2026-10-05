<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_order_po_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_po_id')->constrained('msfl_order_pos')->cascadeOnDelete();
            $table->foreignId('size_id')->constrained('msfl_sizes')->restrictOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->unique(['order_po_id', 'size_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_order_po_sizes');
    }
};
