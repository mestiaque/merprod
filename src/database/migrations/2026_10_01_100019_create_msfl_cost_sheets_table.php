<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_cost_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('cost_sheet_no', 30)->unique();
            $table->foreignId('buyer_id')->constrained('msfl_buyers')->restrictOnDelete();
            // A pre-order costing often comes before the style exists — style
            // and inquiry are optional; style_ref keeps the buyer's reference.
            $table->foreignId('style_id')->nullable()->constrained('msfl_styles')->nullOnDelete();
            $table->foreignId('inquiry_id')->nullable()->constrained('msfl_inquiries')->nullOnDelete();
            $table->string('style_ref', 150)->nullable();
            $table->string('garment_description')->nullable();
            $table->string('size_range', 100)->nullable();
            $table->foreignId('currency_id')->nullable()->constrained('msfl_currencies')->nullOnDelete();
            $table->date('costing_date');
            $table->unsignedInteger('order_qty')->nullable();
            $table->decimal('smv', 8, 2)->nullable();
            // Every cost below is per dozen; per-piece figures are derived.
            $table->decimal('fabric_cost', 15, 4)->default(0);
            $table->decimal('trims_cost', 15, 4)->default(0);
            $table->decimal('process_cost', 15, 4)->default(0); // wash, print, embroidery, other
            $table->decimal('cm_cost', 15, 4)->default(0);
            $table->decimal('commercial_percent', 6, 2)->default(0);
            $table->decimal('commercial_cost', 15, 4)->default(0);
            $table->decimal('other_cost', 15, 4)->default(0);
            $table->decimal('total_cost', 15, 4)->default(0);
            $table->decimal('profit_percent', 6, 2)->default(0);
            $table->decimal('profit_amount', 15, 4)->default(0);
            $table->decimal('offer_price', 15, 4)->default(0); // per piece
            $table->decimal('buyer_target_price', 15, 4)->nullable(); // per piece
            $table->decimal('final_price', 15, 4)->nullable(); // per piece, agreed
            $table->string('status', 20)->default('draft'); // draft, approved
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_cost_sheets');
    }
};
