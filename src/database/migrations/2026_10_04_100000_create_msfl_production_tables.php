<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production — simple piece-count entry against an order PO:
 *   fabric requisition (to an Inventory store) → cutting (sizes, parts,
 *   bundles) → [embroidery] → sewing → [washing] → finishing → final QC →
 *   packing, with QC (pass / rework / reject + defect detail) at each stage.
 *
 * Sizes, Inventory requisitions and Inventory machines are referenced by id
 * without a foreign key (they belong to the Inventory package).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Optional stages, per PO (embroidery / washing may or may not apply).
        Schema::table('msfl_order_pos', function (Blueprint $table) {
            $table->boolean('needs_embroidery')->default(false)->after('ship_mode_id');
            $table->boolean('needs_washing')->default(false)->after('needs_embroidery');
        });

        Schema::create('msfl_prod_fabric_requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_po_id')->constrained('msfl_order_pos')->restrictOnDelete();
            $table->unsignedBigInteger('inv_requisition_id')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('msfl_prod_cuttings', function (Blueprint $table) {
            $table->id();
            $table->string('cutting_no', 30)->unique();
            $table->foreignId('order_po_id')->constrained('msfl_order_pos')->restrictOnDelete();
            $table->date('cutting_date');
            $table->string('table_no', 50)->nullable();
            $table->unsignedInteger('lay_count')->nullable();
            $table->decimal('fabric_used', 12, 2)->nullable();
            $table->unsignedInteger('bundle_size')->default(20);
            $table->unsignedInteger('total_qty')->default(0);
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('msfl_prod_cutting_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_id')->constrained('msfl_prod_cuttings')->cascadeOnDelete();
            $table->unsignedBigInteger('size_id')->index();
            $table->unsignedInteger('qty');
        });

        Schema::create('msfl_prod_cutting_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_id')->constrained('msfl_prod_cuttings')->cascadeOnDelete();
            $table->string('part_name', 100);
            $table->unsignedInteger('qty');
        });

        Schema::create('msfl_prod_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_id')->constrained('msfl_prod_cuttings')->cascadeOnDelete();
            $table->string('bundle_no', 40)->unique();
            $table->unsignedBigInteger('size_id')->index();
            $table->unsignedInteger('qty');
        });

        Schema::create('msfl_prod_entries', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 20);
            $table->date('entry_date');
            $table->foreignId('order_po_id')->constrained('msfl_order_pos')->restrictOnDelete();
            $table->unsignedBigInteger('size_id')->nullable()->index();
            $table->foreignId('line_id')->nullable()->constrained('msfl_lines')->nullOnDelete();
            $table->unsignedInteger('input_qty')->default(0);
            $table->unsignedInteger('pass_qty')->default(0);
            $table->unsignedInteger('rework_qty')->default(0);
            $table->unsignedInteger('reject_qty')->default(0);
            $table->string('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['order_po_id', 'stage']);
        });

        Schema::create('msfl_prod_entry_defects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('msfl_prod_entries')->cascadeOnDelete();
            $table->string('type', 10); // reject | rework
            $table->string('part_name', 100)->nullable();
            $table->unsignedBigInteger('machine_id')->nullable()->index(); // inv_machines
            $table->string('defect', 150)->nullable();
            $table->unsignedInteger('qty');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('msfl_prod_entry_defects');
        Schema::dropIfExists('msfl_prod_entries');
        Schema::dropIfExists('msfl_prod_bundles');
        Schema::dropIfExists('msfl_prod_cutting_parts');
        Schema::dropIfExists('msfl_prod_cutting_sizes');
        Schema::dropIfExists('msfl_prod_cuttings');
        Schema::dropIfExists('msfl_prod_fabric_requisitions');

        Schema::table('msfl_order_pos', function (Blueprint $table) {
            $table->dropColumn(['needs_embroidery', 'needs_washing']);
        });
    }
};
