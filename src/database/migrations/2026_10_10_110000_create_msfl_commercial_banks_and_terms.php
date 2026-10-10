<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial masters: Banks (ours / buyers' / suppliers') and Payment Terms (at sight,
 * usance n days, TT, DP / DA). The Export LC picks its issuing bank, lien bank and payment
 * term from them instead of typed text (those text columns held no data yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_com_banks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->string('bank_type', 20)->default('our');
            $table->string('branch')->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->string('ad_code', 30)->nullable();
            $table->string('account_no', 50)->nullable();
            $table->string('country', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('msfl_com_payment_terms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('term_type', 20);
            $table->unsignedSmallInteger('days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        if (DB::table('msfl_com_export_lcs')->exists()) {
            throw new RuntimeException('Export LCs exist — move their bank / term text to the new masters by hand before this migration.');
        }
        Schema::table('msfl_com_export_lcs', function (Blueprint $table) {
            $table->dropColumn(['payment_term', 'usance_days', 'issuing_bank', 'lien_bank']);
        });
        Schema::table('msfl_com_export_lcs', function (Blueprint $table) {
            $table->foreignId('payment_term_id')->nullable()->after('tolerance_percent')->constrained('msfl_com_payment_terms')->nullOnDelete();
            $table->foreignId('issuing_bank_id')->nullable()->after('payment_term_id')->constrained('msfl_com_banks')->nullOnDelete();
            $table->foreignId('lien_bank_id')->nullable()->after('issuing_bank_id')->constrained('msfl_com_banks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('msfl_com_export_lcs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lien_bank_id');
            $table->dropConstrainedForeignId('issuing_bank_id');
            $table->dropConstrainedForeignId('payment_term_id');
        });
        Schema::table('msfl_com_export_lcs', function (Blueprint $table) {
            $table->string('payment_term', 20)->default('at_sight');
            $table->unsignedSmallInteger('usance_days')->nullable();
            $table->string('issuing_bank')->nullable();
            $table->string('lien_bank')->nullable();
        });
        Schema::dropIfExists('msfl_com_payment_terms');
        Schema::dropIfExists('msfl_com_banks');
    }
};
