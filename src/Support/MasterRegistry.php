<?php

namespace ME\MerchandisingSfl\Support;

use App\Models\User;
use Illuminate\Validation\Rule;
use ME\MerchandisingSfl\Models;

/**
 * Every Master Data screen (route segment => definition). One controller
 * (MasterController) and one view (masters/index) render all of them.
 *
 * Field keys:
 *   name, label, type (text|email|textarea|number|decimal|date|select|boolean),
 *   required (bool), unique (bool), max (int), col (bootstrap col-md-*),
 *   options (array, or callable returning [value => label]) for select.
 * Column keys: label, value (attribute name, or Closure(Model): string).
 * `is_active` is added to every master automatically.
 */
class MasterRegistry
{
    public static function all(): array
    {
        $users = fn () => User::query()->orderBy('name')->pluck('name', 'id')->all();
        $active = fn (string $model) => fn () => $model::query()->active()->orderBy('name')->pluck('name', 'id')->all();
        $code = ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 50];
        $name = ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'max' => 255];
        $codeName = [['label' => 'Code', 'value' => 'code'], ['label' => 'Name', 'value' => 'name']];

        return [
            'buyers' => [
                'title' => 'Buyers', 'singular' => 'Buyer', 'model' => Models\Buyer::class, 'permission' => 'msfl_buyer',
                'fields' => [
                    $code, $name,
                    ['name' => 'short_name', 'label' => 'Short Name', 'type' => 'text', 'max' => 50],
                    ['name' => 'merchandiser_id', 'label' => 'Merchandiser', 'type' => 'select', 'options' => $users, 'exists' => 'users'],
                    ['name' => 'country', 'label' => 'Country', 'type' => 'text', 'max' => 100],
                    ['name' => 'agent_name', 'label' => 'Agent / Buying House', 'type' => 'text'],
                    ['name' => 'contact_person', 'label' => 'Contact Person', 'type' => 'text'],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'max' => 50],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email'],
                    ['name' => 'payment_term', 'label' => 'Payment Term', 'type' => 'text'],
                    ['name' => 'delivery_term', 'label' => 'Delivery Term', 'type' => 'select', 'options' => Models\Order::DELIVERY_TERMS],
                    ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'col' => 12],
                ],
                'columns' => [
                    ...$codeName,
                    ['label' => 'Country', 'value' => 'country'],
                    ['label' => 'Merchandiser', 'value' => fn ($m) => $m->merchandiser->name ?? '-'],
                    ['label' => 'Contact', 'value' => fn ($m) => collect([$m->contact_person, $m->phone])->filter()->implode(' / ') ?: '-'],
                    ['label' => 'Delivery Term', 'value' => 'delivery_term'],
                    ['label' => 'Approval', 'value' => fn ($m) => $m->approvalLabel()],
                ],
                'with' => ['merchandiser'],
                'note' => fn () => 'A new buyer goes to <strong>Approvals</strong> and can be used (orders, Inventory) only once approved. Editing a rejected buyer sends it again.',
            ],
            'seasons' => [
                'title' => 'Seasons', 'singular' => 'Season', 'model' => Models\Season::class, 'permission' => 'msfl_season',
                'fields' => [
                    $code, $name,
                    ['name' => 'year', 'label' => 'Year', 'type' => 'number', 'min' => 2000, 'max' => 2100],
                    ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date'],
                    ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date'],
                ],
                'columns' => [
                    ...$codeName,
                    ['label' => 'Year', 'value' => 'year'],
                    ['label' => 'Period', 'value' => fn ($m) => $m->start_date ? $m->start_date->format('d M Y') . ' – ' . optional($m->end_date)->format('d M Y') : '-'],
                ],
            ],
            'product-types' => [
                'title' => 'Product Types', 'singular' => 'Product Type', 'model' => Models\ProductType::class, 'permission' => 'msfl_product_type',
                'fields' => [
                    $code, $name,
                    ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => ['woven' => 'Woven', 'knit' => 'Knit', 'denim' => 'Denim', 'sweater' => 'Sweater']],
                    ['name' => 'default_smv', 'label' => 'Default SMV', 'type' => 'decimal'],
                ],
                'columns' => [...$codeName, ['label' => 'Category', 'value' => fn ($m) => ucfirst((string) $m->category) ?: '-'], ['label' => 'Default SMV', 'value' => 'default_smv']],
            ],
            'wash-types' => [
                'title' => 'Wash Types', 'singular' => 'Wash Type', 'model' => Models\WashType::class, 'permission' => 'msfl_wash_type',
                'fields' => [$code, $name],
                'columns' => $codeName,
            ],
            'ship-modes' => [
                'title' => 'Ship Modes', 'singular' => 'Ship Mode', 'model' => Models\ShipMode::class, 'permission' => 'msfl_ship_mode',
                'fields' => [$code, $name],
                'columns' => $codeName,
            ],
            'factories' => [
                'title' => 'Factories', 'singular' => 'Factory', 'model' => Models\Factory::class, 'permission' => 'msfl_factory',
                'fields' => [
                    $code, $name,
                    ['name' => 'capacity_per_month', 'label' => 'Capacity / Month (pcs)', 'type' => 'number', 'min' => 0],
                    ['name' => 'is_own', 'label' => 'Own Factory', 'type' => 'boolean', 'default' => true],
                    ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'col' => 12],
                ],
                'columns' => [...$codeName, ['label' => 'Capacity / Month', 'value' => 'capacity_per_month'], ['label' => 'Own / Sub-contract', 'value' => fn ($m) => $m->is_own ? 'Own' : 'Sub-contract']],
            ],
            'currencies' => [
                'title' => 'Currencies', 'singular' => 'Currency', 'model' => Models\Currency::class, 'permission' => 'msfl_currency',
                'fields' => [
                    ['name' => 'code', 'label' => 'Code (ISO)', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 10],
                    $name,
                    ['name' => 'symbol', 'label' => 'Symbol', 'type' => 'text', 'max' => 10],
                    ['name' => 'exchange_rate', 'label' => 'Exchange Rate (to BDT)', 'type' => 'decimal', 'required' => true],
                ],
                'columns' => [...$codeName, ['label' => 'Symbol', 'value' => 'symbol'], ['label' => 'Rate (BDT)', 'value' => 'exchange_rate']],
            ],
            'item-categories' => [
                'title' => 'Item Categories', 'singular' => 'Item Category', 'model' => Models\ItemCategory::class, 'permission' => 'msfl_item_category',
                'fields' => [$code, $name, ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => self::itemTypes()]],
                'columns' => [...$codeName, ['label' => 'Type', 'value' => fn ($m) => self::itemTypes()[$m->type] ?? $m->type]],
            ],
            'items' => [
                'title' => 'Items (Fabric & Trims)', 'singular' => 'Item', 'model' => Models\Item::class, 'permission' => 'msfl_item',
                'fields' => [
                    $code, $name,
                    ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'options' => self::itemTypes()],
                    ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'options' => $active(Models\ItemCategory::class), 'exists' => 'msfl_item_categories'],
                    ['name' => 'uom_id', 'label' => 'Unit', 'type' => 'select', 'options' => $active(Models\Uom::class), 'exists' => 'inv_units'],
                    ['name' => 'default_supplier_id', 'label' => 'Default Supplier', 'type' => 'select', 'options' => $active(Models\Supplier::class), 'exists' => 'inv_suppliers'],
                    ['name' => 'default_price', 'label' => 'Default Price', 'type' => 'decimal'],
                    ['name' => 'composition', 'label' => 'Composition', 'type' => 'text'],
                    ['name' => 'gsm', 'label' => 'GSM', 'type' => 'decimal'],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'col' => 12],
                ],
                'columns' => [
                    ...$codeName,
                    ['label' => 'Type', 'value' => fn ($m) => self::itemTypes()[$m->type] ?? $m->type],
                    ['label' => 'Category', 'value' => fn ($m) => $m->category->name ?? '-'],
                    ['label' => 'Unit', 'value' => fn ($m) => $m->uom->code ?? '-'],
                    ['label' => 'Supplier', 'value' => fn ($m) => $m->defaultSupplier->name ?? '-'],
                    ['label' => 'Price', 'value' => 'default_price'],
                ],
                'with' => ['category', 'uom', 'defaultSupplier'],
                'filters' => ['type' => self::itemTypes()],
            ],
            'sample-types' => [
                'title' => 'Sample Types', 'singular' => 'Sample Type', 'model' => Models\SampleType::class, 'permission' => 'msfl_sample_type',
                'fields' => [
                    $code, $name,
                    ['name' => 'sequence', 'label' => 'Sequence', 'type' => 'number', 'min' => 0],
                    ['name' => 'requires_buyer_approval', 'label' => 'Needs Buyer Approval', 'type' => 'boolean', 'default' => true],
                ],
                'columns' => [...$codeName, ['label' => 'Sequence', 'value' => 'sequence'], ['label' => 'Buyer Approval', 'value' => fn ($m) => $m->requires_buyer_approval ? 'Yes' : 'No']],
                'order_by' => ['sequence', 'asc'],
            ],

            'garment-parts' => [
                'title' => 'Garment Parts', 'singular' => 'Garment Part', 'model' => Models\GarmentPart::class, 'permission' => 'msfl_garment_part',
                'fields' => [$code, ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 100]],
                'columns' => $codeName,
                'note' => fn () => 'Parts (Front, Back, Sleeve …) picked in Cutting → Parts Cut, part-wise Embroidery, QC / Rework and defect rows. Renaming a part doesn\'t change entries already saved with the old name.',
                'order_by' => ['name', 'asc'],
            ],

            // Planning → Setup
            'machine-types' => [
                'title' => 'Machine Types', 'singular' => 'Machine Type', 'model' => Models\MachineType::class, 'permission' => 'msfl_machine_type',
                'fields' => [$code, $name, ['name' => 'is_helper', 'label' => 'Helper workplace (no machine — MAN, IRON)', 'type' => 'boolean', 'col' => 6]],
                'columns' => [...$codeName, ['label' => 'Inventory Type', 'value' => fn ($m) => $m->inv_type ?? '-'], ['label' => 'Counted as', 'value' => fn ($m) => $m->is_helper ? 'Helper' : 'Operator']],
                // Machine types come from Inventory → Machines; only helper workplaces are added here.
                'before_index' => fn () => \ME\MerchandisingSfl\Services\InventoryMachines::syncTypes(),
                'note' => fn () => \ME\MerchandisingSfl\Services\InventoryMachines::available()
                    ? 'Machine types are filled from <a href="' . e(\Illuminate\Support\Facades\Route::has('inventory.machines.index') ? route('inventory.machines.index') : '#') . '" target="_blank">Inventory → Machines</a> (their <strong>Type</strong>) — don\'t add machines here. Set a short code (e.g. SNLS) for each, and add helper workplaces (MAN, IRON) that aren\'t machines.'
                    : null,
                'order_by' => ['code', 'asc'],
            ],
            'operations' => [
                'title' => 'Operations Library', 'singular' => 'Operation', 'model' => Models\Operation::class, 'permission' => 'msfl_operation',
                'before_index' => fn () => \ME\MerchandisingSfl\Services\InventoryMachines::syncTypes(),
                'fields' => [
                    $code, $name,
                    ['name' => 'machine_type_id', 'label' => 'Machine Type (M/C)', 'type' => 'select', 'options' => fn () => Models\MachineType::query()->active()->orderBy('code')->get()->mapWithKeys(fn ($t) => [$t->id => $t->code . ' — ' . $t->name])->all(), 'exists' => 'msfl_machine_types'],
                    ['name' => 'attachment', 'label' => 'Attachment', 'type' => 'select', 'options' => self::attachments()],
                    ['name' => 'default_smv', 'label' => 'Default SMV', 'type' => 'decimal'],
                ],
                'columns' => [...$codeName, ['label' => 'M/C', 'value' => fn ($m) => $m->machineType->code ?? '-'], ['label' => 'Attachment', 'value' => 'attachment'], ['label' => 'Default SMV', 'value' => 'default_smv']],
                'with' => ['machineType'],
            ],
        ];
    }

    public static function get(string $slug): array
    {
        $definition = self::all()[$slug] ?? abort(404);
        $definition['slug'] = $slug;

        return $definition;
    }

    public static function itemTypes(): array
    {
        return ['fabric' => 'Fabric', 'trims' => 'Trims', 'accessories' => 'Accessories', 'packing' => 'Packing'];
    }

    /** Work aids on a bulletin operation. */
    public static function attachments(): array
    {
        return ['Guide' => 'Guide', 'Pattern' => 'Pattern', 'Cutter' => 'Cutter', 'Table' => 'Table', 'Folder' => 'Folder', 'Binder' => 'Binder', 'Template' => 'Template', 'Jig' => 'Jig'];
    }

    /** Select options as [value => label], resolving lazy (callable) option lists. */
    public static function options(array $field): array
    {
        $options = $field['options'] ?? [];

        return $options instanceof \Closure ? $options() : $options;
    }

    public static function rules(array $definition, $ignoreId = null): array
    {
        $table = (new $definition['model'])->getTable();
        $rules = ['is_active' => ['nullable', 'boolean']];

        foreach ($definition['fields'] as $field) {
            $fieldRules = [! empty($field['required']) ? 'required' : 'nullable'];

            $fieldRules = array_merge($fieldRules, match ($field['type']) {
                'email' => ['email', 'max:255'],
                'textarea' => ['string', 'max:5000'],
                'number' => array_filter(['integer', 'min:' . ($field['min'] ?? 0), isset($field['max']) ? 'max:' . $field['max'] : null]),
                'decimal' => ['numeric', 'min:0'],
                'date' => ['date'],
                'boolean' => ['boolean'],
                'select' => isset($field['exists'])
                    ? [Rule::exists($field['exists'], 'id')]
                    : [Rule::in(array_keys(self::options($field)))],
                default => ['string', 'max:' . ($field['max'] ?? 255)],
            });

            if (! empty($field['unique'])) {
                $fieldRules[] = Rule::unique($table, $field['name'])->ignore($ignoreId);
            }

            $rules[$field['name']] = $fieldRules;
        }

        return $rules;
    }

    /** Display value of one index column for a record. */
    public static function columnValue(array $column, $record): string
    {
        $value = $column['value'] instanceof \Closure ? ($column['value'])($record) : $record->{$column['value']};

        return ($value === null || $value === '') ? '-' : (string) $value;
    }
}
