<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Master Harga Jual — {{ $outletName }} — {{ $scopeLabel }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 14px; font: 10px/1.3 Arial, Helvetica, sans-serif; color: #222; background: #f4f6f9; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
            margin: 0 0 12px; padding: 10px 14px; background: #fff; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
        .toolbar button { min-height: 34px; padding: 6px 14px; border: 1px solid #286090; border-radius: 4px; background: #337ab7; color: #fff; cursor: pointer; font-size: 13px; }
        .toolbar .muted { color: #777; font-size: 12px; }
        .sheet { background: #fff; padding: 10px; }
        .doc-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 10px; margin-bottom: 8px; }
        .doc-head h1 { margin: 0; font-size: 16px; }
        .doc-head .sub { margin-top: 2px; font-size: 11px; color: #444; }
        .doc-head .meta { text-align: right; font-size: 10px; color: #555; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        thead { display: table-header-group; }
        tfoot { display: table-row-group; }
        th, td { border: 0.2mm solid #999; padding: 1mm 1.4mm; vertical-align: top; }
        th { background: #e9e9e9; font-weight: bold; text-align: left; white-space: nowrap; }
        tr { page-break-inside: avoid; break-inside: avoid; }
        .c { text-align: center; }
        .r { text-align: right; }
        .nw { white-space: nowrap; }
        .muted { color: #888; }
        .small { font-size: 8px; color: #666; }
        .neg { color: #c0392b; }
        tr.sep td { background: #dcdcf0; color: #333; font-weight: bold; font-size: 11px; letter-spacing: .5px;
            -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        @page { size: A4 landscape; margin: 8mm; }
        @media print {
            body { padding: 0; background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; }
        }
    </style>
</head>

<body>
    <div class="toolbar">
        <span>
            <strong>{{ number_format($total, 0, ',', '.') }}</strong> produk &middot; kertas A4 landscape
            <span class="muted">(halaman dimuat bertahap; tunggu sampai selesai sebelum mencetak)</span>
        </span>
        <span>
            <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        </span>
    </div>

    <div class="sheet">
        <div class="doc-head">
            <div>
                <h1>Master Harga Jual</h1>
                <div class="sub">Outlet: <strong>{{ $outletName }}</strong> &middot; {{ $scopeLabel }}</div>
            </div>
            <div class="meta">
                Dicetak {{ $printedAt }}<br>oleh {{ $printedBy }}<br>{{ number_format($total, 0, ',', '.') }} produk
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Nama Product</th>
                    <th>HPP</th>
                    <th class="c">Pajak</th>
                    <th class="c">Disc Reg</th>
                    <th class="c">Disc 1</th>
                    <th class="c">Disc 2</th>
                    <th class="c">Disc Toko</th>
                    <th class="c">Margin</th>
                    <th class="c">Harga Jual</th>
                    <th class="c">Penyesuaian</th>
                    <th>Catatan</th>
                    <th>Terakhir Update</th>
                    <th>Stock Outlet</th>
                </tr>
            </thead>
            <tbody>