{{--
    Shared UI for Merchandising (SFL) pages — matches the HR module on the host's
    Bootstrap 4 theme: plain .card, .table-bordered.table-sm, small buttons,
    and icon-only row actions as .btn-custom (yellow = edit, danger = delete,
    success = view/approve, primary = print/label) from admin/assets/css/custom.css.
--}}
<style>
    /* Same sizing HR applies to its row-action buttons. */
    .btn-custom { padding: 0 3px !important; height: auto !important; }
    /* Bootstrap-5 utilities still used by a few layouts, not in BS 4.4. */
    .gap-1 { gap: .25rem; } .gap-2 { gap: .5rem; } .gap-3 { gap: 1rem; }
    .form-label { margin-bottom: .25rem; }
    .msfl-module .table td, .msfl-module .table th { vertical-align: middle; }
    /* Table headers: black text on light grey (host layout sets white-on-grey). */
    table.table thead { background: #e9ecef !important; color: #000 !important; }
    table.table thead th { color: #000 !important; }
    /* Select2 at the same height as .form-control-sm inputs. */
    .select2-container .select2-selection--single { height: calc(1.5em + .5rem + 2px) !important; font-size: .875rem; }
    .select2-container .select2-selection--single .select2-selection__rendered { line-height: calc(1.5em + .5rem) !important; }
    .select2-container .select2-selection--single .select2-selection__arrow { height: calc(1.5em + .5rem) !important; }
    .select2-container .select2-selection--multiple { min-height: calc(1.5em + .5rem + 2px) !important; font-size: .875rem; }
    /* Row-action cells stay on one line. */
    .msfl-module .table td.text-right:last-child { white-space: nowrap; }
    .msfl-module .table td:last-child > form { display: inline-block; }
</style>
