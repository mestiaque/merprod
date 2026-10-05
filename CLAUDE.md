# Merchandising v2 — `mestiaque/merchandising-sfl`

Guide for Claude Code working in this package. Read it before changing anything.

## What this is

The **only** merchandising + production module of the Suhana Fashions ERP (sidebar: **"Merchandising v2"**).
It covers inquiry → style / tech pack → costing → samples → order + PO → BOM → bulletin → T&A → production
(cutting … packing) → Finish Store (Inventory), plus dashboard, post-costing and reports.

- Package path: `/home/estiaque/Desktop/NIT/SFL/merchandising-sfl` (Laravel 12, PHP 8.3+)
- Host app: `/home/estiaque/Desktop/NIT/erp-suhana` (path repo, symlinked into `vendor/mestiaque/merchandising-sfl`). Run `php artisan …` from the host.
- Dev DB: `suhana_erp_actual` (MySQL). Host tests use a different DB (`suhana_erp`) that is not migrated.
- The old packages **MerchandisingTrace (v1, `mer_*`) and production-trace (`trc_*`) were removed on 2026-10-05** (composer removed, tables dropped; backups in `erp-suhana/storage/app/backups/2026-10-05_*`). Never re-introduce them or reference `ME\MerchandisingTrace` / `ME\ProductionTrace`. The folders may still exist on disk — ignore them.

### Working with the user
- The user writes Banglish / Bengali. **Reply in Bengali.**
- Don't commit unless asked. This package's git repo has no commits yet.
- Ask before destructive or hard-to-reverse steps (dropping tables, composer changes, deleting data). Dry-run in a DB transaction first.
- The user does **not** want any master data entered twice across modules (see "Who owns which master").

## Naming

| | |
|---|---|
| Namespace | `ME\MerchandisingSfl` (`src/`) |
| Tables | `msfl_*` |
| Routes | prefix `admin/merchandising-sfl`, names `msfl.*` (config `merchandising-sfl.route`) |
| Views | `merchandising-sfl::admin.*` (`src/resources/views/admin`) |
| Permissions | group `MERCHANDISING_SFL`, keys `msfl_<module>.<action>` (list / add / edit / view / delete / approve / all) |
| Document numbers | `<PREFIX>-<YYYY>-<0001>` via `Services/DocumentNumberService` (prefixes in `Config/config.php`: INQ CST ORD BOM SMP BLT TNA CUT) |

## Who owns which master (no duplicates!)

| Master | Owner | v2 reads it through |
|---|---|---|
| Buyers (with approval), Seasons, Product Types, Wash Types, Ship Modes, Factories, Currencies, Item Categories, Items (fabric & trims for costing/BOM), Sample Types, Machine Types, Operations | **v2** | `Support/MasterRegistry` (+ `Lines` screen) |
| Departments, Holidays, Floor-lines | **HR** (`hr_departments`, `hr_holidays`, `hr_floor_lines`) | read-only models `Models/Department`, `Models/Holiday`, `Models/FloorLine` |
| Colors, Sizes, Units (UOM), Suppliers, Machines, Stores, stock items | **Inventory** (`inv_*`) | read-only models `Models/Color`, `Size`, `Uom` (`code` accessor = short_name), `Supplier`; machines via `Services/InventoryMachines` |

- A v2 **Line** = an HR floor-line (`msfl_lines.hr_floor_line_id`) + planning figures (operators, minutes, efficiency). `Line::code` = HR line name ("Line 2"), `name` = "Floor 1 · Line 2".
- **Machine Types** are synced from Inventory machine `type` text (`msfl_machine_types.inv_type`); a line's machines are counted from Inventory machines whose `line` = the HR line name. Only helper workplaces (MAN, IRON) are added by hand.
- Columns pointing at HR / Inventory tables have **no DB foreign key** (soft reference + index) so deleting there never fails because of v2. Validation uses `Rule::exists('inv_…')->whereNull('deleted_at')`.
- Before adding any new master: check `hr_*`, `inv_*`, `msfl_*` for an existing one. Ask the user only if ownership is genuinely unclear.

## Process flow and where the code is

| Step | Screen (route) | Code |
|---|---|---|
| Masters | `msfl.masters.*` (`masters/{master}`) | `MasterController` + `Support/MasterRegistry` (fields, columns, `note`, `before_index` hook). New buyer → `submitForApproval()` |
| Inquiry | `msfl.inquiries.*` | `InquiryController`, `Models/Inquiry` |
| Style / tech pack | `msfl.styles.*` (+ images) | `StyleController`, `FileUploadService` |
| Cost sheet (per dozen) | `msfl.cost-sheets.*`, `.approve` | `CostSheet::recalculate()` is the only place totals are derived |
| Samples | `msfl.samples.*`, `.submit`, `.decide`, `.status` | `SampleController`, `Services/SampleDecision` (shared with central Approvals) |
| Order + PO lines (size breakdown) | `msfl.orders.*`, `.status` (confirm), `msfl.orders.pos.*` | `OrderController`, `OrderPoController` (logs revisions after confirm), `OrderPo::syncSizes()` |
| BOM | `msfl.boms.*`, `.approve`, `.revise` | `BomController`, `BomItem::requiredQty()` |
| Lines / Bulletin | `msfl.lines.*`, `msfl.bulletins.*` (+ print) | `LineController`, `Models/Bulletin::recalculate()/machineSummary()` |
| T&A plan | `msfl.tna.*`, `.tasks.update`, `.recalculate` | `TnaPlanController`, `Services/TnaPlanner`, `WorkingCalendar` |
| T&A Sheet (81 cols, buyer format) | `msfl.tna-sheet.index`, `.print` | `TnaSheetController`, `Services/TnaSheet` |
| Fabric requisition | `msfl.production.requisitions.*` | creates an **Inventory** `InvRequisition` (+ central approval); store approves/issues in Inventory |
| Cutting (sizes, parts, auto bundles) | `msfl.production.cuttings.*` | `Production/CuttingController` |
| Embroidery / Sewing / Washing / Finishing / Final QC / Packing | `msfl.production.entries.*` (`production/{stage}`) | `Production/EntryController`, `Services/ProductionFlow` |
| Production status per PO | `msfl.production.status.*` | `Production/StatusController` |
| Finish Store receive | Inventory `inventory.fg-receives.*` | capped by v2 packed qty (`SflInventory\Services\MerchandisingLink::finishSummary`) |
| Post cost sheet | `msfl.post-costing.*` (+ print) | `Services/PostCosting` |
| Dashboard | `msfl.dashboard` (+ host home widget) | `Services/DashboardStats`, `partials/dashboard-widget` |
| Reports | `msfl.reports.*` (`reports/{report}` + print) | `Services/Reports::LIST` / `run()`, one generic view |

## Domain rules (keep these true)

**Production (`Services/ProductionFlow`)**
- Route per PO: `cutting → [embroidery] → sewing → [washing] → finishing → final_qc → packing`; embroidery / washing only when `msfl_order_pos.needs_embroidery / needs_washing`.
- Each entry = input pcs + QC (pass, rework, reject). Cutting "passes" all it cuts.
- `can take in = previous stage pass − this stage input`; `WIP = input − pass − reject` (rework stays in WIP until it passes; rejects leave the flow).
- Guards: input ≤ can take in; pass + reject ≤ pieces in stage; reject/rework must be broken into defect rows that add up; **sewing rejects need part + Inventory machine**; the PO row is locked during the check; you can't delete a cutting / entry whose pieces a later stage already took.
- Only **confirmed** orders go into production (`Lookups::productionPos()`).

**T&A (`Services/TnaPlanner`)**
- Dates count back from shipment in working days (weekly off `MERCHANDISING_SFL_WEEKLY_OFF`, default Friday; HR holidays, ranges expanded).
- `syncAutoActuals()` (runs when a T&A page opens) fills actual dates from `auto_source`: order_confirmed, bom_approved, bulletin_approved, sample_submitted:/sample_approved:<CODE>, fabric_inhouse (first posted Buyer Store GRN of the style), cutting_started, sewing_started, sewing_done / washing_done / finishing_done / final_qc_done / packing_done.
- "Stage done" = full order qty cut and nothing waiting / in WIP at that stage or upstream; date = that stage's last entry.
- Bulletin SMV 0 must not hide the style SMV.

**T&A Sheet (`Services/TnaSheet`)** — one row per PO, 9 groups / 81 columns. Cells come from earlier processes (order, inquiry, style, PO + `msfl_order_po_revisions`, cost sheet, BOM YY, samples, Buyer Store GRNs for fabric consignments & trims keywords). Columns without a source are T&A tasks (codes like `FILE_HANDOVER`, `PILOT_STITCHING`, `FABRIC_LC` in the default template). PCD check: PASS / FAIL ("Cut started before …" or PCD passed without cutting) / PENDING.

**PO revisions** — after an order is confirmed, changing a PO's `po_qty`, `pcd_date` or `shipment_date` writes `msfl_order_po_revisions`; the sheet shows original / revised 1 / revised 2 (latest).

**Post costing (`Services/PostCosting`)** — budget = approved cost sheet per dozen × order qty; actual material = Inventory issues against the PO (`inv_issues.msfl_order_po_id`, BDT ÷ currency `exchange_rate`), **estimated from budget per pc × packed when no store value exists** (flagged); CM (both sides) = FOB − material − commercial − process − other; reject loss = rejects × budget material / pc.

**Approvals (host central Approvals page)** — modules `msfl.buyer` (`Approvals/BuyerApprovalHandler`, permission `msfl_buyer.approve`) and `msfl.sample` (`Approvals/SampleApprovalHandler`, `msfl_sample.approve`), registered in `MerchandisingSflServiceProvider::registerApprovalModules()`. Buyer `active()` scope = active **and** approved, so unapproved buyers never reach dropdowns or Inventory. Sample decided on its own page → `SampleDecision::closeCentral()` closes the central request.

## Inventory integration (package `SFL/sfl-inventory`)
- `inv_requisitions / inv_issues / inv_grns / inv_finished_goods_receives / inv_production_consumptions` carry `msfl_buyer_id`, `msfl_style_id`, `msfl_order_po_id`.
- `ME\SflInventory\Services\MerchandisingLink` = the bridge (v2 buyers/styles/POs, validation, `inventoryBuyerId()` maps a v2 buyer to `inv_buyers` by name, `finishSummary()` uses v2 `ProductionFlow` packing pass).
- Buyer Store issue only works if a posted GRN exists for the same inventory buyer + style text — v2 requisitions set both.
- Inventory items need a store (`opening_store_id`). Buyer-supplied GRNs post immediately; issues are created already approved.

## How to add things
- **Master**: migration (`msfl_*`), model using `Concerns/IsMaster`, one `MasterRegistry` entry, permission key in `Config/permission.php`, sidebar entry in `Config/sidebar.php`.
- **Report**: add to `Reports::LIST` + a private method returning `['headers','align','rows','totals'(,'period','status_col')]` — screen and print come for free.
- **Production stage**: `ProductionFlow::STAGES` + `route()`; sidebar; T&A auto source if needed.
- **T&A auto source**: `TnaTemplateTask::autoSources()` + a `match` arm in `TnaPlanner::autoActualDate()`.
- **Permissions/sidebar**: after adding a key, grant it to the role(s) (Super Admin = permission id 1, JSON in `permissions.permission`) or the menu stays hidden.
- **Migrations**: always a new file in `src/database/migrations`; never edit one that has run. Run only this package's file: `php artisan migrate --path=/home/estiaque/Desktop/NIT/SFL/merchandising-sfl/src/database/migrations/<file>.php --realpath --force`. No FKs to `hr_*` / `inv_*`.

## UI conventions (match exactly)
- Bootstrap 4.4 + jQuery + select2 (class `msfl-select2`, include `partials.select2-init`); wrapper `<div class="flex-grow-1 msfl-module">` with `partials.alerts` + `partials.ui-kit`.
- Cards; `table table-bordered table-sm`; `btn-sm`, `form-control-sm`; forms in `col-md-3` (4 per row) via `partials.input` / `partials.select`; filter inputs + **Filter + Reset** buttons in the same row; row actions `btn-custom success|yellow|danger`; deletes through `partials.delete-confirm-modal` (`data-action`).
- Repeating rows: `<template id="{prefix}RowTemplate">` + `tbody#{prefix}RowsBody` + `partials.line-items-script` (`__INDEX__` placeholder).
- Prints extend the host's `printMaster2` (header with `general()->logo()/title/address_one`, `@page` size).
- Blade gotchas: don't put an inline `@php(...)` before a block `@php … @endphp` in the same view; a directive needs whitespace before it (`other @if(...)`, not `other@if(...)`).
- Pagination: `->links('pagination::bootstrap-5')`.

## Seeders
- `MsflDefaultMasterSeeder` (+ `MsflPlanningSeeder`): sample types, ship modes, currencies, machine types, 53 operations, default 34-task T&A template. Already run on the dev DB.
- `MsflDemoSeeder`: full demo (codes `D-…`): 4 buyers (Zara pending approval), 4 styles, samples in every state, 4 orders (H&M PO 4501 fully produced & in Finish Store; 4502 revised + sewing; Primark 7701 embroidery; Next 9901 waiting for fabric; draft order), BOMs, bulletins, T&A, Inventory items / machines / GRN / issues / FG receive. Enters everything through the real routes; refuses to run twice. Already run on the dev DB.

## Verifying changes (recipes that work here)
- Lint: `php -l <file>`. Compile all views: run Blade's `compileString` over `src/resources/views` and `php -l` the output.
- **Render a page** from CLI: set `APP_RUNNING_IN_CONSOLE=false` (otherwise the host's `general()` is null and `websiteTitle()` crashes), log in a user (id 7 = Super Admin), `$kernel->handle(Request::create('/admin/merchandising-sfl/...','GET'))`. **One page per PHP process** — the host sidebar declares a global `renderMenu()` function.
- **Drive forms (POST/PUT)** in a script: bind `middleware.disable` → `true`, listen to `RouteMatched` and call `router->substituteBindings()` + `substituteImplicitBindings()` (route-model binding is middleware), give the request `app('session.store')`, read `errors` / `error` / `success` from the session; `Gate::before(fn () => true)`, `Mail::fake()`. Re-enable middleware before rendering a page (views need the shared `$errors`).
- Wrap experiments in `DB::beginTransaction()` … `DB::rollBack()`; confirm nothing leaked afterwards.

## Known gaps / open decisions
- **Fabric & Accessories Booking** not built: needs the user's decision — create Inventory purchase requisitions (needs v2 items ↔ Inventory items) or a separate v2 booking record. User chose to keep v2 items separate.
- Process (wash/print/emb) actual cost has no source yet (post costing carries budget).
- Inventory shipments are not linked to PO lines (no "shipped" qty in v2 yet).
- Currency exchange rates are all 1 (seeded) — set real rates in Master Data → Currencies.
- Pre-existing host issues unrelated to v2: some HR pages (`production-rate`, `individual-pay-slip`, `zkteco-data-import`, `attendances/sync-status`) error; host test DB `suhana_erp` not migrated.
