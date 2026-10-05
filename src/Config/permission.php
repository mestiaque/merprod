<?php

// Merged into config('permission')['modules']['MERCHANDISING_SFL'] at runtime by
// MerchandisingSflServiceProvider::mergePermissions(). Feeds the host's Roles
// Setup checkbox UI — permission strings are "<module_key>.<action_key>",
// e.g. 'msfl_buyer.list', 'msfl_order.add'.

$crud = ['list' => 'List', 'add' => 'Create', 'edit' => 'Edit', 'view' => 'View', 'delete' => 'Delete', 'all' => 'All'];
$master = ['list' => 'List', 'add' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete', 'all' => 'All'];

return [
    'MERCHANDISING_SFL' => [
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

        // Planning
        'msfl_machine_type' => ['label' => 'Planning — Machine Types', 'permissions' => $master],
        'msfl_line' => ['label' => 'Planning — Lines', 'permissions' => $master],
        'msfl_operation' => ['label' => 'Planning — Operations Library', 'permissions' => $master],
        'msfl_tna_template' => ['label' => 'Planning — T&A Templates', 'permissions' => $master],
        'msfl_bulletin' => ['label' => 'Planning — Bulletin', 'permissions' => $crud + ['approve' => 'Approve']],
        // 'edit' = update step dates / recalculate.
        'msfl_tna' => ['label' => 'Planning — T&A', 'permissions' => $crud],

        // Sample Stages
        'msfl_sample' => ['label' => 'Sample Stages', 'permissions' => $crud + ['approve' => 'Approve / Reject']],

        'msfl_post_costing' => ['label' => 'Post Cost Sheet (Budget vs Actual)', 'permissions' => ['list' => 'List', 'view' => 'View', 'all' => 'All']],

        'msfl_report' => ['label' => 'Reports', 'permissions' => ['view' => 'View', 'all' => 'All']],

        // Production
        'msfl_prod_status' => ['label' => 'Production — Status', 'permissions' => ['list' => 'List', 'all' => 'All']],
        'msfl_prod_requisition' => ['label' => 'Production — Fabric Requisition', 'permissions' => ['list' => 'List', 'add' => 'Create', 'all' => 'All']],
        'msfl_prod_cutting' => ['label' => 'Production — Cutting', 'permissions' => ['list' => 'List', 'add' => 'Create', 'view' => 'View', 'delete' => 'Delete', 'all' => 'All']],
        'msfl_prod_entry' => ['label' => 'Production — Stage Entries (Embroidery … Packing)', 'permissions' => ['list' => 'List', 'add' => 'Create', 'delete' => 'Delete', 'all' => 'All']],
    ],
];
