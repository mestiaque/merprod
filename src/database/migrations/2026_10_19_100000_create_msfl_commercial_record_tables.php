<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial (step 2) — record-keeping only, each with its document file: Supplier PI,
 * Back-to-Back LC, Import Shipment, Export Documents (CO / GSP / EXP / Shipping Advice)
 * and Bank Realization. New tables only; nothing else reads or writes them, and no FKs
 * to inv_* tables (supplier_id is an inv_suppliers id, as Merchandising's Supplier model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('msfl_com_btb_lcs', function (Blueprint $table) {
            $table->id();
            $table->string('btb_lc_no', 100);
            $table->date('lc_date');
            $table->foreignId('export_lc_id')->nullable()->constrained('msfl_com_export_lcs')->nullOnDelete();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->foreignId('bank_id')->nullable()->constrained('msfl_com_banks')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('msfl_currencies')->nullOnDelete();
            $table->decimal('lc_value', 16, 2)->default(0);
            $table->foreignId('payment_term_id')->nullable()->constrained('msfl_com_payment_terms')->nullOnDelete();
            $table->date('last_shipment_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('msfl_com_supplier_pis', function (Blueprint $table) {
            $table->id();
            $table->string('pi_no', 100);
            $table->date('pi_date');
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->string('goods_type', 20)->nullable();
            $table->foreignId('order_id')->nullable()->constrained('msfl_orders')->nullOnDelete();
            $table->foreignId('export_lc_id')->nullable()->constrained('msfl_com_export_lcs')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained('msfl_currencies')->nullOnDelete();
            $table->decimal('pi_value', 16, 2)->default(0);
            $table->foreignId('btb_lc_id')->nullable()->constrained('msfl_com_btb_lcs')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('msfl_com_import_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('btb_lc_id')->nullable()->constrained('msfl_com_btb_lcs')->nullOnDelete();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->string('invoice_no', 100)->nullable();
            $table->decimal('invoice_value', 16, 2)->nullable();
            $table->foreignId('ship_mode_id')->nullable()->constrained('msfl_ship_modes')->nullOnDelete();
            $table->string('bl_no', 100)->nullable();
            $table->date('bl_date')->nullable();
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
            $table->date('arrival_date')->nullable();
            $table->date('doc_release_date')->nullable();
            $table->date('goods_release_date')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('msfl_com_export_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('msfl_com_invoices')->nullOnDelete();
            $table->string('doc_type', 30);
            $table->string('doc_no', 100)->nullable();
            $table->date('doc_date')->nullable();
            $table->string('forwarder')->nullable();
            $table->date('booking_date')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('msfl_com_realizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('msfl_com_invoices')->nullOnDelete();
            $table->date('realization_date');
            $table->foreignId('bank_id')->nullable()->constrained('msfl_com_banks')->nullOnDelete();
            $table->decimal('realized_amount', 16, 2)->default(0);
            $table->decimal('bank_charge', 16, 2)->nullable();
            $table->string('prc_no', 100)->nullable();
            $table->date('prc_date')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_com_realizations');
        Schema::dropIfExists('msfl_com_export_documents');
        Schema::dropIfExists('msfl_com_import_shipments');
        Schema::dropIfExists('msfl_com_supplier_pis');
        Schema::dropIfExists('msfl_com_btb_lcs');
    }
};
