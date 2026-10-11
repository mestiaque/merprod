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
        // The standard terms (also added by the banks-and-terms migration; idempotent) so typed terms can match.
        foreach ([
            ['LC-SIGHT', 'LC at Sight', 'sight', 0], ['LC-U30', 'LC Usance 30 days', 'usance', 30], ['LC-U60', 'LC Usance 60 days', 'usance', 60],
            ['LC-U90', 'LC Usance 90 days', 'usance', 90], ['LC-U120', 'LC Usance 120 days', 'usance', 120], ['TT-ADV', 'TT in Advance', 'tt_advance', 0],
            ['TT-30', 'TT 30 days after shipment', 'tt', 30], ['DP', 'DP at Sight', 'dp', 0], ['DA-60', 'DA 60 days', 'da', 60],
        ] as [$code, $name, $type, $days]) {
            DB::table('msfl_com_payment_terms')->insertOrIgnore(['code' => $code, 'name' => $name, 'term_type' => $type, 'days' => $days, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

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
