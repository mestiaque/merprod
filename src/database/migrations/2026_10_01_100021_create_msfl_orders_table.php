<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 30)->unique();
            $table->foreignId('buyer_id')->constrained('msfl_buyers')->restrictOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('msfl_seasons')->nullOnDelete();
            $table->foreignId('merchandiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('factory_id')->nullable()->constrained('msfl_factories')->nullOnDelete();
            $table->foreignId('inquiry_id')->nullable()->constrained('msfl_inquiries')->nullOnDelete();
            $table->string('buyer_order_ref', 100)->nullable(); // buyer's contract / LC reference
            $table->date('order_date');
            $table->foreignId('currency_id')->nullable()->constrained('msfl_currencies')->nullOnDelete();
            $table->string('delivery_term', 10)->nullable();
            $table->string('payment_term')->nullable();
            $table->unsignedInteger('total_qty')->default(0); // cached sum of PO qty
            $table->decimal('total_value', 15, 4)->default(0); // cached sum of PO value
            $table->string('status', 20)->default('draft'); // draft, confirmed, closed, cancelled
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('attachment')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_orders');
    }
};
