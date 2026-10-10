<?php

// Merged into config('permission')['modules'] at runtime by
// MerchandisingSflServiceProvider::mergePermissions(). Feeds the host's Roles
// Setup checkbox UI — permission strings are "<module_key>.<action_key>",
// e.g. 'msfl_buyer.list', 'msfl_order.add'.
//
// One group per top sidebar menu (Merchandising / Planning / Production).
// Checks read the keys across all groups, so moving a key between groups
// keeps every role's saved permissions working.

$crud = ['list' => 'List', 'add' => 'Create', 'edit' => 'Edit', 'view' => 'View', 'delete' => 'Delete', 'all' => 'All'];
$master = ['list' => 'List', 'add' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'all' => 'All'];

return [
    'MERCHANDISING' => [
        'msfl_dashboard' => ['label' => 'Dashboard', 'permissions' => ['view' => 'View', 'all' => 'All']],
        // Shows the "এই পাতা কী কাজে" help box (Support/PageHelp) on every screen.
        'msfl_page_help' => ['label' => 'Page Help (এই পাতা কী কাজে)', 'permissions' => ['view' => 'View']],

        // Master Data
        'msfl_buyer' => ['label' => 'Buyers', 'permissions' => $master + ['approve' => 'Approve / Reject']],
        'msfl_season' => ['label' => 'Seasons', 'permissions' => $master],
        'msfl_product_type' => ['label' => 'Product Types', 'permissions' => $master],
        'msfl_wash_type' => ['label' => 'Wash Types', 'permissions' => $master],
        'msfl_ship_mode' => ['label' => 'Ship Modes', 'permissions' => $master],
        'msfl_factory' => ['label' => 'Factories', 'permissions' => $master],
        'msfl_currency' => ['label' => 'Currencies', 'permissions' => $master],
        'msfl_item_category' => ['label' => 'Item Categories', 'permissions' => $master],
        'msfl_item' => ['label' => 'Items (Fabric & Trims)', 'permissions' => $master],
        'msfl_sample_type' => ['label' => 'Sample Types', 'permissions' => $master],
        'msfl_garment_part' => ['label' => 'Garment Parts', 'permissions' => $master],

        // Dev (R&D)
        'msfl_inquiry' => ['label' => 'Inquiries', 'permissions' => $crud],
        'msfl_style' => ['label' => 'Tech Pack / Styles', 'permissions' => $crud],
        // 'approve' locks the sheet (no further edits).
        'msfl_cost_sheet' => ['label' => 'Costing (Pre-order)', 'permissions' => $crud + ['approve' => 'Approve']],

        // Order + BOM
        // 'approve' confirms the order (locks POs from deletion of the order).
        'msfl_order' => ['label' => 'Orders', 'permissions' => $crud + ['approve' => 'Confirm Order']],
        'msfl_bom' => ['label' => 'BOM', 'permissions' => $crud + ['approve' => 'Approve']],

        // Sample Stages
        'msfl_sample' => ['label' => 'Sample Stages', 'permissions' => $crud + ['approve' => 'Approve / Reject']],

        'msfl_post_costing' => ['label' => 'Post Cost Sheet (Budget vs Actual)', 'permissions' => ['list' => 'List', 'view' => 'View', 'all' => 'All']],

        // Also opens Production → Reports and Planning → T&A Status (same report screens).
        'msfl_report' => ['label' => 'Reports (+ Production Reports, T&A Status)', 'permissions' => ['view' => 'View', 'all' => 'All']],
    ],

    'PLANNING' => [
        // Setup
        'msfl_machine_type' => ['label' => 'Machine Types', 'permissions' => $master],
        'msfl_line' => ['label' => 'Lines', 'permissions' => $master],
        'msfl_operation' => ['label' => 'Operations Library', 'permissions' => $master],
        'msfl_tna_template' => ['label' => 'T&A Templates', 'permissions' => $master],
        'msfl_daily_target' => ['label' => 'Daily Targets (cutting / packing / line value)', 'permissions' => $master],
        'msfl_bulletin' => ['label' => 'Bulletin', 'permissions' => $crud + ['approve' => 'Approve']],
        // 'edit' = update step dates / recalculate. Also opens T&A Sheet.
        'msfl_tna' => ['label' => 'T&A (+ T&A Sheet)', 'permissions' => $crud],
    ],

    'PRODUCTION' => [
        'msfl_prod_status' => ['label' => 'Production Status', 'permissions' => ['list' => 'List', 'all' => 'All']],
        'msfl_prod_requisition' => ['label' => 'Fabric Requisition', 'permissions' => ['list' => 'List', 'add' => 'Create', 'all' => 'All']],
        'msfl_prod_cutting' => ['label' => 'Cutting', 'permissions' => ['list' => 'List', 'add' => 'Create', 'view' => 'View', 'delete' => 'Delete', 'all' => 'All']],
        'msfl_prod_entry' => ['label' => 'Stage Entries (Embroidery … Packing, QC, Rework, Buyer QC)', 'permissions' => ['list' => 'List', 'add' => 'Create', 'delete' => 'Delete', 'all' => 'All']],
    ],

    'COMMERCIAL' => [
        'msfl_com_dashboard' => ['label' => 'Commercial Dashboard', 'permissions' => ['view' => 'View', 'all' => 'All']],
        // Setup
        'msfl_com_bank' => ['label' => 'Banks', 'permissions' => $master],
        'msfl_com_payment_term' => ['label' => 'Payment Terms', 'permissions' => $master],
        // 'approve' = Activate / Close / Re-open an LC.
        'msfl_export_lc' => ['label' => 'Export LC / Sales Contract', 'permissions' => $crud + ['approve' => 'Activate / Close']],
        'msfl_com_invoice' => ['label' => 'Commercial Invoice (+ Packing List)', 'permissions' => $crud],
        'msfl_com_report' => ['label' => 'Commercial Reports (LC Status, Export Register)', 'permissions' => ['view' => 'View', 'all' => 'All']],
    ],
];
