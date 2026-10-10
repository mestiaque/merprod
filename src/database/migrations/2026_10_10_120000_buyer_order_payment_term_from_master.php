<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer and Order pick their payment term from Commercial → Setup → Payment Terms
 * (payment_term_id) instead of typed text. Existing text is matched to a term by name
 * (case-insensitive); if any value has no matching term nothing is changed.
 */
return new class extends Migration
{
    private array $tables = ['msfl_buyers', 'msfl_orders'];

    public function up(): void
    {
        $terms = DB::table('msfl_com_payment_terms')->whereNull('deleted_at')->get(['id', 'name'])
            ->mapWithKeys(fn ($t) => [mb_strtolower(trim($t->name)) => $t->id]);

        $map = [];
        foreach ($this->tables as $table) {
            foreach (DB::table($table)->whereNotNull('payment_term')->where('payment_term', '<>', '')->distinct()->pluck('payment_term') as $text) {
                $id = $terms[mb_strtolower(trim($text))] ?? null;
                if (! $id) {
                    throw new RuntimeException("{$table}: payment term \"{$text}\" has no matching Payment Term — add it in Commercial → Setup → Payment Terms (same name) and run again.");
                }
                $map[$text] = $id;
            }
        }

        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('payment_term_id')->nullable()->after('payment_term')->constrained('msfl_com_payment_terms')->nullOnDelete();
            });
            foreach ($map as $text => $id) {
                DB::table($table)->where('payment_term', $text)->update(['payment_term_id' => $id]);
            }
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('payment_term'));
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('payment_term')->nullable()->after('payment_term_id'));
            DB::table($table)->whereNotNull('payment_term_id')->update([
                'payment_term' => DB::raw('(SELECT name FROM msfl_com_payment_terms WHERE msfl_com_payment_terms.id = ' . $table . '.payment_term_id)'),
            ]);
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('payment_term_id'));
        }
    }
};
