<?php

$base = '/admin/merchandising-sfl';

// Three top-level menus (one group, shown in this order): Merchandising → Planning → Production.
return [
    [
        'group_title' => '',
        [
            'title' => 'Merchandising',
            'icon' => 'fa-solid fa-shirt',
            'icon_color' => 'text-primary',
            'permission' => '',
            'order' => 13,
            'children' => [
                ['title' => 'Dashboard', 'icon' => 'fa-solid fa-gauge', 'icon_color' => 'text-primary', 'permission' => 'msfl_dashboard', 'route' => "$base/dashboard"],
                [
                    'title' => 'Master Data',
                    'icon' => 'fa-solid fa-database',
                    'icon_color' => 'text-primary',
                    'permission' => '',
                    'children' => [
                        ['title' => 'Buyers', 'icon' => 'fa-solid fa-handshake', 'icon_color' => 'text-primary', 'permission' => 'msfl_buyer', 'route' => "$base/masters/buyers"],
                        ['title' => 'Seasons', 'icon' => 'fa-solid fa-snowflake', 'icon_color' => 'text-primary', 'permission' => 'msfl_season', 'route' => "$base/masters/seasons"],
                        ['title' => 'Product Types', 'icon' => 'fa-solid fa-shapes', 'icon_color' => 'text-primary', 'permission' => 'msfl_product_type', 'route' => "$base/masters/product-types"],
                        ['title' => 'Wash Types', 'icon' => 'fa-solid fa-soap', 'icon_color' => 'text-primary', 'permission' => 'msfl_wash_type', 'route' => "$base/masters/wash-types"],
                        ['title' => 'Ship Modes', 'icon' => 'fa-solid fa-plane-departure', 'icon_color' => 'text-primary', 'permission' => 'msfl_ship_mode', 'route' => "$base/masters/ship-modes"],
                        ['title' => 'Factories', 'icon' => 'fa-solid fa-industry', 'icon_color' => 'text-primary', 'permission' => 'msfl_factory', 'route' => "$base/masters/factories"],
                        ['title' => 'Currencies', 'icon' => 'fa-solid fa-money-bill', 'icon_color' => 'text-primary', 'permission' => 'msfl_currency', 'route' => "$base/masters/currencies"],
                        ['title' => 'Item Categories', 'icon' => 'fa-solid fa-layer-group', 'icon_color' => 'text-primary', 'permission' => 'msfl_item_category', 'route' => "$base/masters/item-categories"],
                        ['title' => 'Items (Fabric & Trims)', 'icon' => 'fa-solid fa-boxes-stacked', 'icon_color' => 'text-primary', 'permission' => 'msfl_item', 'route' => "$base/masters/items"],
                        ['title' => 'Sample Types', 'icon' => 'fa-solid fa-list-ol', 'icon_color' => 'text-primary', 'permission' => 'msfl_sample_type', 'route' => "$base/masters/sample-types"],
                        ['title' => 'Garment Parts', 'icon' => 'fa-solid fa-puzzle-piece', 'icon_color' => 'text-primary', 'permission' => 'msfl_garment_part', 'route' => "$base/masters/garment-parts"],
                    ],
                ],
                [
                    'title' => 'Dev (R&D)',
                    'icon' => 'fa-solid fa-flask',
                    'icon_color' => 'text-primary',
                    'permission' => '',
                    'children' => [
                        ['title' => 'Inquiries', 'icon' => 'fa-solid fa-magnifying-glass-dollar', 'icon_color' => 'text-primary', 'permission' => 'msfl_inquiry', 'route' => "$base/inquiries"],
                        ['title' => 'Tech Pack / Styles', 'icon' => 'fa-solid fa-vest-patches', 'icon_color' => 'text-primary', 'permission' => 'msfl_style', 'route' => "$base/styles"],
                        ['title' => 'Costing (Pre-order)', 'icon' => 'fa-solid fa-calculator', 'icon_color' => 'text-primary', 'permission' => 'msfl_cost_sheet', 'route' => "$base/cost-sheets"],
                    ],
                ],
                [
                    'title' => 'Order + BOM',
                    'icon' => 'fa-solid fa-file-signature',
                    'icon_color' => 'text-primary',
                    'permission' => '',
                    'children' => [
                        ['title' => 'Orders', 'icon' => 'fa-solid fa-file-signature', 'icon_color' => 'text-primary', 'permission' => 'msfl_order', 'route' => "$base/orders"],
                        ['title' => 'BOM', 'icon' => 'fa-solid fa-list-check', 'icon_color' => 'text-primary', 'permission' => 'msfl_bom', 'route' => "$base/boms"],
                        ['title' => 'Post Cost Sheet', 'icon' => 'fa-solid fa-scale-balanced', 'icon_color' => 'text-primary', 'permission' => 'msfl_post_costing', 'route' => "$base/post-costing"],
                    ],
                ],
                ['title' => 'Sample Stages', 'icon' => 'fa-solid fa-vial', 'icon_color' => 'text-primary', 'permission' => 'msfl_sample', 'route' => "$base/samples"],
                ['title' => 'Reports', 'icon' => 'fa-solid fa-chart-pie', 'icon_color' => 'text-primary', 'permission' => 'msfl_report', 'route' => "$base/reports"],
            ],
        ],
        [
            'title' => 'Planning',
            'icon' => 'fa-solid fa-calendar-days',
            'icon_color' => 'text-primary',
            'permission' => '',
            'order' => 13.1,
            'children' => [
                [
                    'title' => 'Setup',
                    'icon' => 'fa-solid fa-sliders',
                    'icon_color' => 'text-primary',
                    'permission' => '',
                    'children' => [
                        ['title' => 'Machine Types', 'icon' => 'fa-solid fa-gears', 'icon_color' => 'text-primary', 'permission' => 'msfl_machine_type', 'route' => "$base/masters/machine-types"],
                        ['title' => 'Lines', 'icon' => 'fa-solid fa-diagram-project', 'icon_color' => 'text-primary', 'permission' => 'msfl_line', 'route' => "$base/lines"],
                        ['title' => 'Operations Library', 'icon' => 'fa-solid fa-list-ol', 'icon_color' => 'text-primary', 'permission' => 'msfl_operation', 'route' => "$base/masters/operations"],
                        ['title' => 'T&A Templates', 'icon' => 'fa-solid fa-table-list', 'icon_color' => 'text-primary', 'permission' => 'msfl_tna_template', 'route' => "$base/tna-templates"],
                    ],
                ],
                ['title' => 'Bulletin', 'icon' => 'fa-solid fa-clipboard-list', 'icon_color' => 'text-primary', 'permission' => 'msfl_bulletin', 'route' => "$base/bulletins"],
                ['title' => 'T&A', 'icon' => 'fa-solid fa-calendar-check', 'icon_color' => 'text-primary', 'permission' => 'msfl_tna', 'route' => "$base/tna"],
                ['title' => 'T&A Sheet', 'icon' => 'fa-solid fa-table-cells', 'icon_color' => 'text-primary', 'permission' => 'msfl_tna', 'route' => "$base/tna-sheet"],
                ['title' => 'T&A Status', 'icon' => 'fa-solid fa-calendar-xmark', 'icon_color' => 'text-primary', 'permission' => 'msfl_report', 'route' => "$base/reports/tna-status"],
            ],
        ],
        [
            'title' => 'Production',
            'icon' => 'fa-solid fa-industry',
            'icon_color' => 'text-primary',
            'permission' => '',
            'order' => 13.2,
            'children' => [
                ['title' => 'Production Status', 'icon' => 'fa-solid fa-chart-column', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_status', 'route' => "$base/production/status"],
                ['title' => 'Fabric Requisition', 'icon' => 'fa-solid fa-dolly', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_requisition', 'route' => "$base/production/requisitions"],
                ['title' => 'Cutting', 'icon' => 'fa-solid fa-scissors', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_cutting', 'route' => "$base/production/cuttings"],
                ['title' => 'Embroidery', 'icon' => 'fa-solid fa-spa', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_entry', 'route' => "$base/production/embroidery"],
                ['title' => 'Sewing', 'icon' => 'fa-solid fa-shirt', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_entry', 'route' => "$base/production/sewing"],
                ['title' => 'Washing', 'icon' => 'fa-solid fa-soap', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_entry', 'route' => "$base/production/washing"],
                ['title' => 'Finishing', 'icon' => 'fa-solid fa-wand-magic-sparkles', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_entry', 'route' => "$base/production/finishing"],
                ['title' => 'Buyer QC', 'icon' => 'fa-solid fa-clipboard-check', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_entry', 'route' => "$base/production/final_qc"],
                ['title' => 'Packing', 'icon' => 'fa-solid fa-box', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_entry', 'route' => "$base/production/packing"],
                ['title' => 'QC', 'icon' => 'fa-solid fa-magnifying-glass', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_entry', 'route' => "$base/production/qc"],
                ['title' => 'Rework', 'icon' => 'fa-solid fa-rotate-left', 'icon_color' => 'text-primary', 'permission' => 'msfl_prod_entry', 'route' => "$base/production/rework"],
                [
                    'title' => 'Reports',
                    'icon' => 'fa-solid fa-chart-pie',
                    'icon_color' => 'text-primary',
                    'permission' => '',
                    'children' => [
                        ['title' => 'Daily Production', 'icon' => 'fa-solid fa-industry', 'icon_color' => 'text-primary', 'permission' => 'msfl_report', 'route' => "$base/reports/daily-production"],
                        ['title' => 'Defect Analysis', 'icon' => 'fa-solid fa-triangle-exclamation', 'icon_color' => 'text-primary', 'permission' => 'msfl_report', 'route' => "$base/reports/defects"],
                        ['title' => 'Shipment Status', 'icon' => 'fa-solid fa-truck-fast', 'icon_color' => 'text-primary', 'permission' => 'msfl_report', 'route' => "$base/reports/shipment-status"],
                        ['title' => 'Requisition Details', 'icon' => 'fa-solid fa-dolly', 'icon_color' => 'text-primary', 'permission' => 'msfl_report', 'route' => "$base/reports/requisition-details"],
                        ['title' => 'Requisition Summary', 'icon' => 'fa-solid fa-boxes-stacked', 'icon_color' => 'text-primary', 'permission' => 'msfl_report', 'route' => "$base/reports/requisition-summary"],
                    ],
                ],
            ],
        ],
    ],
];
