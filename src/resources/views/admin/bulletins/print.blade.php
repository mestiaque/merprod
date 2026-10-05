<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Operation Bulletin — {{ $bulletin->style->style_no ?? $bulletin->bulletin_no }}</title>
    @include('merchandising-sfl::admin.bulletins.partials.sheet-style')
    <style>
        body { font-family: "Times New Roman", serif; margin: 12px; }
        h2 { text-align: center; color: #1a0dab; font-weight: normal; margin: 0 0 4px; font-size: 18px; }
        .no-print { text-align: right; margin-bottom: 6px; }
        @media print { .no-print { display: none; } body { margin: 0; } @page { size: A4 portrait; margin: 8mm; } }
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">Print</button></div>
    <h2>Operation Bulletin</h2>
    @include('merchandising-sfl::admin.bulletins.partials.sheet', ['showInactive' => false])
</body>
</html>
