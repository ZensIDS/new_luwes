<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label Voucher</title>
    @php
        $paper = request('paper') === '58' ? 58 : 80;
        $labelName = strtoupper($outlets->firstWhere('id', (int) $outletId)?->name ?? 'LUWES');
        $labelStrip = str_repeat($labelName . ' • VOUCHER • ', 5);
    @endphp
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
        /* Desain voucher ringkas (pipih) mengikuti label harga: strip outlet di atas dan kiri-kanan */
        .label-grid { width:{{ $paper }}mm; margin:0 auto; }
        .label-outer { width:{{ $paper }}mm; padding:1.2mm {{ $paper === 58 ? '1.5mm' : '2.5mm' }}; break-inside:avoid; page-break-inside:avoid; background:#fff; border-bottom:1px dashed #777; }
        .frame { display:grid; grid-template-columns:2.6mm 1fr 2.6mm; grid-template-rows:2.6mm 1fr; border:1px solid #000; background:#fff; }
        .strip { background:#fff; color:#000; overflow:hidden; white-space:nowrap; font-size:5px; font-weight:800; letter-spacing:.5px; }
        .strip-top { grid-column:1 / 4; display:flex; align-items:center; border-bottom:1px solid #000; }
        .strip-left, .strip-right { display:flex; align-items:center; justify-content:center; border-right:1px solid #000; }
        .strip-right { border-right:none; border-left:1px solid #000; }
        .strip-left span, .strip-right span { writing-mode:vertical-rl; transform:rotate(180deg); white-space:nowrap; }
        .label-content { padding:1mm {{ $paper === 58 ? '1.5mm' : '3mm' }} 1.2mm; text-align:center; }
        .value-label { font-size:7.5px; font-weight:900; letter-spacing:2.5px; line-height:1.2; }
        .value-main { font-size:{{ $paper === 58 ? '20px' : '24px' }}; font-weight:900; line-height:1.05; letter-spacing:.2px; }
        .value-main .rp { font-size:11px; font-weight:700; vertical-align:3px; margin-right:1px; }
        .value-main .pct { font-size:14px; font-weight:800; margin-left:1px; }
        .value-sub { font-size:8px; font-weight:700; line-height:1.25; }
        .voucher-barcode { height:9mm; margin:1mm 0 .6mm; padding:0 1.5mm; overflow:hidden; }
        .voucher-barcode svg { display:block; width:100%; height:9mm; shape-rendering:crispEdges; }
        .code { font-family:'Courier New', monospace; font-size:{{ $paper === 58 ? '9px' : '10px' }}; font-weight:700; letter-spacing:1.2px; text-align:center; line-height:1.2; }
        @page { size:{{ $paper }}mm auto; margin:0; }
        @media print {
            body { width:{{ $paper }}mm; margin:0 auto; padding:0; background:#fff; }
            .toolbar { display:none !important; }
        }
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
                    <label>Ukuran kertas thermal
                        <select name="paper">
                            <option value="80" {{ $paper === 80 ? 'selected' : '' }}>80 mm</option>
                            <option value="58" {{ $paper === 58 ? 'selected' : '' }}>58 mm</option>
                        </select>
                    </label>
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
                            @php $isRafaksi = $promotion->type === 'flash_sale'; @endphp
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
                @php
                    $isVoucherModel = $voucher->print_type === 'voucher';
                    $isBundle = ! $isVoucherModel && $voucher->type === 'bundle';
                    $discType = $isVoucherModel ? $voucher->type : $voucher->discount_type;
                    $discValue = (float) ($isVoucherModel ? $voucher->value : $voucher->discount_value);
                    $money = fn ($number) => number_format((float) $number, 0, ',', '.');
                    $percentText = rtrim(rtrim(number_format($discValue, 2, ',', '.'), '0'), ',');

                    // Nominal potongan yang paling jelas untuk pelanggan
                    $mainPrefix = '';
                    $mainValue = 'PROMO';
                    $mainSuffix = '';
                    $subText = null;
                    if ($isBundle) {
                        $mainValue = 'BUNDLING';
                        $subText = 'Beli paket, dapat harga / bonus spesial';
                    } elseif ($voucher->print_discount_amount !== null) {
                        $mainPrefix = 'Rp';
                        $mainValue = $money($voucher->print_discount_amount);
                        $subText = $discType === 'percentage' ? 'Diskon ' . $percentText . '%' : null;
                    } elseif ($discType === 'percentage' && $discValue > 0) {
                        $mainValue = $percentText;
                        $mainSuffix = '%';
                        $subText = ($voucher->max_discount_amount ?? null) ? 'Maksimal potongan Rp ' . $money($voucher->max_discount_amount) : null;
                    } elseif ($discValue > 0) {
                        $mainPrefix = 'Rp';
                        $mainValue = $money($discValue);
                    }

                    // Info tambahan digabung jadi satu baris kecil (subText) supaya label tetap pipih
                    $extra = [];
                    if ($voucher->print_product_name) {
                        $extra[] = $voucher->print_product_name;
                    }
                    if (($voucher->min_purchase ?? 0) > 0) {
                        $extra[] = 'Min. belanja Rp ' . $money($voucher->min_purchase);
                    }
                    $subLine = trim(implode(' • ', array_filter([$subText, ...$extra])));

                    // Barcode (Code 128, pengaturan generate tidak diubah). Pastikan SVG punya viewBox dan
                    // diregangkan pas selebar area cetak, supaya selalu rata tengah dan tidak terpotong.
                    $barcodeSvg = DNS1D::getBarcodeSVG((string) $voucher->code, 'C128', 1, 30, 'black', false, false);
                    if (! str_contains($barcodeSvg, 'viewBox')
                        && preg_match('/<svg\b[^>]*\swidth="([\d.]+)(?:px)?"/', $barcodeSvg, $svgW)
                        && preg_match('/<svg\b[^>]*\sheight="([\d.]+)(?:px)?"/', $barcodeSvg, $svgH)) {
                        $barcodeSvg = preg_replace('/<svg\b/', '<svg viewBox="0 0 ' . $svgW[1] . ' ' . $svgH[1] . '"', $barcodeSvg, 1);
                    }
                    if (! str_contains($barcodeSvg, 'preserveAspectRatio')) {
                        $barcodeSvg = preg_replace('/<svg\b/', '<svg preserveAspectRatio="none"', $barcodeSvg, 1);
                    }
                @endphp
                @for ($index = 0; $index < $voucher->print_qty; $index++)
                    <div class="label-outer">
                        <div class="frame">
                            <div class="strip strip-top"><span>{{ $labelStrip }}</span></div>
                            <div class="strip strip-left"><span>{{ $labelName }} • VOUCHER • </span></div>
                            <div class="label-content">
                                <div class="value-label">VOUCHER POTONGAN</div>
                                <div class="value-main">
                                    @if ($mainPrefix !== '')<span class="rp">{{ $mainPrefix }}</span>@endif{{ $mainValue }}@if ($mainSuffix !== '')<span class="pct">{{ $mainSuffix }}</span>@endif
                                </div>
                                @if ($subLine !== '')<div class="value-sub">{{ $subLine }}</div>@endif
                                <div class="voucher-barcode">
                                    {!! $barcodeSvg !!}
                                </div>
                                <div class="code">{{ $voucher->code }}</div>
                            </div>
                            <div class="strip strip-right"><span>{{ $labelName }} • VOUCHER • </span></div>
                        </div>
                    </div>
                @endfor
            @endforeach
        </div>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>