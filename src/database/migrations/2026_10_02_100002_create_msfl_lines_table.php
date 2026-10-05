<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_lines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('floor', 100)->nullable();
            $table->unsignedInteger('operators')->default(0);
            $table->unsignedInteger('helpers')->default(0);
            $table->unsignedInteger('working_minutes')->default(600); // per day, incl. OT
            $table->decimal('efficiency_percent', 5, 2)->default(60);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_lines');
    }
};
