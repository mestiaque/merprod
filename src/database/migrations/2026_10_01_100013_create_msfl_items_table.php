<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('msfl_item_categories')->nullOnDelete();
            $table->string('type', 20); // fabric, trims, accessories, packing
            $table->foreignId('uom_id')->nullable()->constrained('msfl_uoms')->nullOnDelete();
            $table->foreignId('default_supplier_id')->nullable()->constrained('msfl_suppliers')->nullOnDelete();
            $table->decimal('default_price', 12, 4)->nullable();
            $table->string('composition')->nullable();
            $table->decimal('gsm', 8, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_items');
    }
};
