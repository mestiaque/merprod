<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master data is entered once, in the module that owns it:
 *   - HR:        departments, holidays, floor-lines
 *   - Inventory: colors, sizes, units (UOM), suppliers
 * Merchandising v2 reads those tables instead of keeping its own copies.
 *
 * The *_id columns keep their names and indexes but lose the foreign key
 * to the dropped msfl_* table. No new cross-package foreign key is added,
 * so deleting a record in HR / Inventory never fails because v2 uses it.
 *
 * msfl_lines keeps only the planning figures (operators, minutes,
 * efficiency) and points at the HR floor-line for its floor / line name.
 */
return new class extends Migration
{
    /** table => columns whose foreign key pointed at a dropped master */
    private array $links = [
        'msfl_items' => ['uom_id', 'default_supplier_id'],
        'msfl_cost_sheet_items' => ['uom_id'],
        'msfl_order_pos' => ['color_id'],
        'msfl_order_po_sizes' => ['size_id'],
        'msfl_bom_items' => ['color_id', 'size_id', 'uom_id', 'supplier_id'],
    ];

    private array $dropped = ['msfl_colors', 'msfl_sizes', 'msfl_uoms', 'msfl_suppliers', 'msfl_departments', 'msfl_holidays'];

    public function up(): void
    {
        foreach ($this->dropped as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException("{$table} has data — move it to HR / Inventory before running this migration.");
            }
        }
        if (DB::table('msfl_lines')->exists()) {
            throw new RuntimeException('msfl_lines has data — link each line to an HR floor-line before running this migration.');
        }

        foreach ($this->links as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->dropForeign([$column]);
                }
            });
        }

        foreach ($this->dropped as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('msfl_lines', function (Blueprint $t) {
            $t->dropUnique(['code']);
            $t->dropColumn(['code', 'name', 'floor']);
        });
        Schema::table('msfl_lines', function (Blueprint $t) {
            $t->unsignedBigInteger('hr_floor_line_id')->after('id')->unique();
        });
    }

    public function down(): void
    {
        Schema::table('msfl_lines', function (Blueprint $t) {
            $t->dropUnique(['hr_floor_line_id']);
            $t->dropColumn('hr_floor_line_id');
        });
        Schema::table('msfl_lines', function (Blueprint $t) {
            $t->string('code', 50)->nullable()->unique()->after('id');
            $t->string('name')->nullable()->after('code');
            $t->string('floor', 100)->nullable()->after('name');
        });

        $master = function (string $name, callable $columns) {
            Schema::create($name, function (Blueprint $t) use ($columns) {
                $t->id();
                $columns($t);
                $t->boolean('is_active')->default(true);
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->softDeletes();
            });
        };
        $master('msfl_colors', fn ($t) => [$t->string('code', 50)->unique(), $t->string('name'), $t->string('pantone', 50)->nullable()]);
        $master('msfl_sizes', fn ($t) => [$t->string('name', 50)->unique(), $t->unsignedInteger('sort_order')->default(0)]);
        $master('msfl_uoms', fn ($t) => [$t->string('code', 20)->unique(), $t->string('name'), $t->unsignedTinyInteger('decimal_places')->default(2)]);
        $master('msfl_suppliers', fn ($t) => [
            $t->string('code', 50)->unique(), $t->string('name'), $t->string('type', 30)->nullable(), $t->string('country', 100)->nullable(),
            $t->string('contact_person')->nullable(), $t->string('phone', 50)->nullable(), $t->string('email')->nullable(),
            $t->unsignedInteger('lead_time_days')->nullable(), $t->string('payment_term')->nullable(),
        ]);
        $master('msfl_departments', fn ($t) => [$t->string('code', 50)->unique(), $t->string('name')]);
        $master('msfl_holidays', fn ($t) => [$t->date('date')->unique(), $t->string('name')]);
    }
};
