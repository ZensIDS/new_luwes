<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label Voucher</title>
    <style>
        * { box-sizing:border-box; }
        body { margin:0; padding:20px; font:14px Arial,sans-serif; color:#222; background:#f4f6f9; }
        .toolbar { max-width:1100px; margin:0 auto 18px; padding:16px; background:#fff; border:1px solid #ddd; }
        .toolbar h2 { margin:0 0 12px; font-size:20px; }
        .toolbar form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        input, select, button { min-height:34px; padding:6px 9px; border:1px solid #bbb; border-radius:3px; background:#fff; }
        button { cursor:pointer; background:#337ab7; color:#fff; border-color:#286090; }
        .muted { color:#777; font-size:12px; }
        table { width:100%; border-collapse:collapse; margin-top:14px; }
        th, td { padding:7px; border:1px solid #ddd; text-align:left; }
        th { background:#f5f5f5; }
        .label-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:4mm; width:100%; max-width:194mm; margin:0 auto; }
        .voucher-label { min-height:39mm; padding:3.5mm; border:1px dashed #777; background:#fff; text-align:center; overflow:hidden; break-inside:avoid; }
        .voucher-name { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; height:9mm; font-size:13px; font-weight:700; line-height:4.5mm; }
        .voucher-barcode { height:13mm; margin:2mm 0 1mm; overflow:hidden; display:flex; justify-content:center; align-items:flex-start; }
        .voucher-barcode svg { display:block; width:auto; max-width:100%; height:13mm; shape-rendering:crispEdges; }
        .voucher-discount { min-height:6mm; margin:2mm 0; font-size:14px; font-weight:700; }
        .code { width:100%; margin-top:1mm; font-size:9px; letter-spacing:1px; text-align:center; }
        @page { size:A4 portrait; margin:8mm; }
        @media print {
            body { padding:0; background:#fff; }
            .toolbar { display:none !important; }
            .label-grid { max-width:none; }
        }
        @media (max-width:700px) { .label-grid { grid-template-columns:repeat(2, 1fr); } }
    </style>
</head>
<body>
    @if (! $printing)
        <div class="toolbar">
            <h2>Cetak voucher promo</h2>
            <p class="muted">Voucher satuan menampilkan nominal potongan otomatis dari produk yang dipilih. Voucher bundling tidak menampilkan harga produk.</p>
            <form method="GET" action="{{ route('cashier.print.vouchers') }}" target="_blank">
                @if ($outlets->count() > 1)
                    <label>Outlet <select name="outlet_id">
                        @foreach ($outlets as $outlet)<option value="{{ $outlet->id }}" {{ (int) $outletId === (int) $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>@endforeach
                    </select></label>
                @elseif ($outletId)
                    <input type="hidden" name="outlet_id" value="{{ $outletId }}">
                @endif
                <input type="search" name="search" value="{{ $search }}" placeholder="Cari nama atau kode voucher">
                <button type="submit">Tampilkan voucher</button>
            </form>
            @if ($vouchers->isNotEmpty() || $promotions->isNotEmpty())
                <form method="GET" action="{{ route('cashier.print.vouchers') }}" target="_blank" style="display:block;">
                    @if ($outletId)<input type="hidden" name="outlet_id" value="{{ $outletId }}">@endif
                    <input type="hidden" name="print" value="1">
                    <table>
                        <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('.voucher-check:not(:disabled)').forEach((el) => el.checked = this.checked)"></th><th>Kode</th><th>Nama voucher / promo</th><th>Potongan</th><th>Qty label</th></tr></thead>
                        <tbody>
                        @foreach ($vouchers as $voucher)
                            <tr>
                                <td><input class="voucher-check" type="checkbox" name="voucher_ids[]" value="{{ $voucher->id }}"></td>
                                <td>{{ $voucher->barcode }}</td>
                                <td>{{ $voucher->name }}</td>
                                <td>{{ $voucher->print_discount_amount !== null ? 'Rp ' . number_format($voucher->print_discount_amount, 0, ',', '.') : '—' }}</td>
                                <td><input type="number" name="qty[{{ $voucher->id }}]" value="1" min="1" max="100" style="width:75px;"></td>
                            </tr>
                        @endforeach
                        @foreach ($promotions as $promotion)
                            @php($isRafaksi = $promotion->type === 'flash_sale')
                            <tr>
                                <td><input class="voucher-check" type="checkbox" name="promotion_ids[]" value="{{ $promotion->id }}" @if ($isRafaksi) disabled title="Promo Rafaksi tidak dapat dicetak sebagai voucher" @endif></td>
                                <td>{{ $promotion->code }}</td>
                                <td>{{ $promotion->name }}</td>
                                <td>{{ $promotion->print_discount_amount !== null ? 'Rp ' . number_format($promotion->print_discount_amount, 0, ',', '.') : '—' }}</td>
                                <td><input type="number" name="qty[{{ $promotion->id }}]" value="1" min="1" max="100" style="width:75px;"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <button type="submit" style="margin-top:12px;">Cetak voucher / promo terpilih</button>
                </form>
            @else
                <p class="muted">Voucher atau promo aktif yang belum dipakai tidak ditemukan.</p>
            @endif
        </div>
    @else
        <div class="label-grid">
            @foreach ($printItems as $voucher)
                @for ($index = 0; $index < $voucher->print_qty; $index++)
                    <div class="voucher-label">
                        <div class="voucher-name" title="{{ $voucher->name }}">{{ $voucher->name }}</div>
                        <div class="voucher-barcode">
                            {!! DNS1D::getBarcodeSVG((string) $voucher->code, 'C128', 1, 30, 'black', false, false) !!}
                        </div>
                        <div class="code">{{ $voucher->code }}</div>
                        <div class="voucher-discount">
                            @if ($voucher->print_discount_amount !== null)
                                Potongan Rp {{ number_format($voucher->print_discount_amount, 0, ',', '.') }}
                            @elseif ($voucher->print_type === 'promotion' && $voucher->type === 'bundle')
                                Promo bundling
                            @else
                                Promo
                            @endif
                        </div>
                    </div>
                @endfor
            @endforeach
        </div>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
