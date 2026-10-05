<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_styles', function (Blueprint $table) {
            $table->id();
            $table->string('style_no', 100)->unique();
            $table->string('name');
            $table->foreignId('buyer_id')->constrained('msfl_buyers')->restrictOnDelete();
            $table->foreignId('inquiry_id')->nullable()->constrained('msfl_inquiries')->nullOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('msfl_seasons')->nullOnDelete();
            $table->foreignId('merchandiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained('msfl_product_types')->nullOnDelete();
            $table->foreignId('wash_type_id')->nullable()->constrained('msfl_wash_types')->nullOnDelete();
            $table->decimal('smv', 8, 2)->nullable();
            $table->decimal('target_cm', 12, 4)->nullable();
            $table->decimal('confirm_cm', 12, 4)->nullable();
            $table->string('fabric_sourced_by', 10)->default('self'); // self, buyer
            $table->text('fabric_description')->nullable();
            $table->text('description')->nullable();
            $table->string('tech_pack_file')->nullable();
            $table->string('artwork_file')->nullable();
            $table->string('size_chart_file')->nullable();
            $table->string('development_status', 20)->default('new'); // new, in_development, sample_stage, approved, dropped
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_styles');
    }
};
