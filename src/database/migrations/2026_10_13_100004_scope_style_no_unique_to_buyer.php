<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * style_no was globally unique, but two different buyers legitimately reuse
 * the same style number (confirmed by real Inventory GRN history hitting
 * this exact collision during an Inventory → Merchandising style sync). The
 * rest of the codebase already treats style identity as buyer_id + style_no
 * (e.g. StyleController::create() matches an inquiry's style_ref against a
 * master style scoped to the same buyer_id) — this migration makes the DB
 * constraint match that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('msfl_styles', function (Blueprint $table) {
            $table->dropUnique(['style_no']);
            $table->unique(['buyer_id', 'style_no']);
        });
    }

    public function down(): void
    {
        Schema::table('msfl_styles', function (Blueprint $table) {
            $table->dropUnique(['buyer_id', 'style_no']);
            $table->unique('style_no');
        });
    }
};
