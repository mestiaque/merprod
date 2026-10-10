<?php

namespace ME\MerchandisingSfl\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use ME\MerchandisingSfl\Models\Commercial\ExportLc;
use ME\MerchandisingSfl\Services\Commercial\LcStatus;

/** Commercial invoice header (shipping / packing figures) + its PO lines (lines[]). */
class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->route('invoice') ? 'msfl_com_invoice.edit' : 'msfl_com_invoice.add');
    }

    /** Lines left at 0 pcs are ignored. */
    protected function prepareForValidation(): void
    {
        $this->merge(['lines' => array_values(array_filter((array) $this->input('lines', []), fn ($l) => is_array($l) && (int) ($l['qty'] ?? 0) > 0))]);
    }

    public function rules(): array
    {
        return [
            'export_lc_id' => ['required', Rule::exists('msfl_com_export_lcs', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'invoice_date' => ['required', 'date'],
            'inv_shipment_id' => ['nullable', 'integer'],
            'exp_no' => ['nullable', 'string', 'max:50'],
            'exp_date' => ['nullable', 'date'],
            'ship_mode_id' => ['nullable', Rule::exists('msfl_ship_modes', 'id')],
            'bl_no' => ['nullable', 'string', 'max:100'],
            'bl_date' => ['nullable', 'date'],
            'vessel' => ['nullable', 'string', 'max:150'],
            'container_no' => ['nullable', 'string', 'max:150'],
            'port_of_loading' => ['nullable', 'string', 'max:100'],
            'port_of_discharge' => ['nullable', 'string', 'max:100'],
            'final_destination' => ['nullable', 'string', 'max:100'],
            'net_weight' => ['nullable', 'numeric', 'min:0'],
            'gross_weight' => ['nullable', 'numeric', 'min:0'],
            'cbm' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.order_po_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.cartons' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return ['lines.required' => 'Enter the shipped qty of at least one PO.', 'export_lc_id.exists' => 'Pick an active Export LC.'];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $lc = ExportLc::findOrFail($this->export_lc_id);
            $invoice = $this->route('invoice');
            $rows = app(LcStatus::class)->poRows($lc, $invoice?->id)->keyBy(fn ($r) => $r['po']->id);
            foreach ($this->input('lines') as $i => $line) {
                $row = $rows->get((int) $line['order_po_id']);
                if (! $row) {
                    $validator->errors()->add("lines.{$i}.order_po_id", 'A PO is not on this LC.');
                } elseif ((int) $line['qty'] > $row['available']) {
                    $po = $row['po'];
                    $validator->errors()->add("lines.{$i}.qty", "PO {$po->po_no} · " . ($po->style->style_no ?? '') . ": only {$row['available']} pcs can be invoiced (packed {$row['packed']}, already invoiced {$row['invoiced']}).");
                }
            }
        }];
    }
}
