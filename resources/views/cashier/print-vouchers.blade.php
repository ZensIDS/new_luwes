<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label Voucher</title>
    @php
        // 58/68/80 = kertas thermal. 100 = A4 (label 68 mm, 3 kolom per baris), sama dengan label harga.
        $paper = in_array((int) request('paper'), [58, 68, 80, 100], true) ? (int) request('paper') : 80;
        $isA4 = $paper === 100;
        $w = $isA4 ? 68 : $paper; // lebar satu label (mm)
        // Mode cetak sama dengan label harga: thermal (hitam putih) atau warna (aksen pink).
        $mode = request('mode') === 'warna' ? 'warna' : 'thermal';
        // Label 68 mm (termasuk A4 label 68 mm): tinggi label maksimal 35 mm, sama dengan label harga.
        $tall = $w === 68;
        $barcodeH = $tall ? '5mm' : '6mm';
        // Ukuran per kertas: [padding luar, padding isi, font nominal, font kode]
        $paperStyle = [
            58 => ['outer' => '1.5mm', 'content' => '1.5mm', 'value' => '28px', 'code' => '9px'],
            68 => ['outer' => '2mm', 'content' => '2.2mm', 'value' => '32px', 'code' => '9.5px'],
            80 => ['outer' => '2.5mm', 'content' => '3mm', 'value' => '36px', 'code' => '10px'],
        ][$w];
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
        .label-grid { width:{{ $isA4 ? '204mm' : $w . 'mm' }}; margin:0 auto; {{ $isA4 ? 'display:grid; grid-template-columns:repeat(3, 68mm); gap:0; align-items:start;' : '' }} }
        .label-outer { width:{{ $w }}mm; padding:1.2mm {{ $paperStyle['outer'] }}; break-inside:avoid; page-break-inside:avoid; background:#fff; {{ $isA4 ? 'border-right:0.25mm dotted #444; border-bottom:0.25mm dotted #444;' : 'border-bottom:1px dashed #777;' }}
               {{ $tall ? 'height:34.5mm; overflow:hidden;' : '' }} -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        @if ($isA4)
        .label-outer:nth-child(3n+1) { border-left:0.25mm dotted #444; }
        .label-outer:nth-child(-n+3) { border-top:0.25mm dotted #444; }
        @endif
        /* Warna aksen sama dengan label harga: thermal hitam putih, warna = pink (#e0577a) + latar pink muda. */
        .frame { --accent:{{ $mode === 'warna' ? '#e0577a' : '#000' }}; --soft:{{ $mode === 'warna' ? '#fbe6ea' : '#fff' }}; --stripbg:{{ $mode === 'warna' ? '#e0577a' : '#fff' }}; --stripfg:{{ $mode === 'warna' ? '#fff' : '#000' }};
                 display:grid; grid-template-columns:2.6mm 1fr 2.6mm; grid-template-rows:2.6mm 1fr; border:1px solid var(--accent); background:var(--soft); {{ $tall ? 'height:100%;' : '' }} }
        .strip { background:var(--stripbg); color:var(--stripfg); overflow:hidden; white-space:nowrap; font-size:5px; font-weight:800; letter-spacing:.5px; }
        .strip-top { grid-column:1 / 4; display:flex; align-items:center; border-bottom:1px solid var(--accent); }
        .strip-left, .strip-right { display:flex; align-items:center; justify-content:center; border-right:1px solid var(--accent); }
        .strip-right { border-right:none; border-left:1px solid var(--accent); }
        .strip-left span, .strip-right span { writing-mode:vertical-rl; transform:rotate(180deg); white-space:nowrap; }
        .label-content { padding:1mm {{ $paperStyle['content'] }} 1.2mm; text-align:center; min-width:0; overflow:hidden; }
        .value-label { font-size:7.5px; font-weight:900; letter-spacing:2.5px; line-height:1.2; color:var(--accent); }
        .value-main { font-size:{{ $paperStyle['value'] }}; font-weight:900; line-height:1.05; letter-spacing:.2px; color:var(--accent); }
        .value-main .rp { font-size:15px; font-weight:700; vertical-align:3px; margin-right:1px; }
        .value-main .pct { font-size:20px; font-weight:800; margin-left:1px; }
        .value-sub { font-size:8px; font-weight:700; line-height:1.25; {{ $tall ? 'display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;' : '' }} }
        .voucher-barcode { height:{{ $barcodeH }}; width:70%; margin:1mm auto .6mm; padding:0; overflow:hidden; background:#fff; }
        .voucher-barcode svg { display:block; width:100%; height:{{ $barcodeH }}; shape-rendering:crispEdges; }
        .code { font-family:'Courier New', monospace; font-size:{{ $paperStyle['code'] }}; font-weight:700; letter-spacing:1.2px; text-align:center; line-height:1.2; }
        @page { size:{{ $isA4 ? 'A4' : $paper . 'mm auto' }}; margin:{{ $isA4 ? '3mm' : '0' }}; }
        @media print {
            body { width:{{ $isA4 ? 'auto' : $paper . 'mm' }}; margin:0 auto; padding:0; background:#fff; }
            .toolbar { display:none !important; }
        }
    </style>
</head>
<body>
    @if (! $printing)
        <div class="toolbar">
            <h2>Cetak voucher promo</h2>
            <p class="muted">Voucher satuan menampilkan nominal potongan otomatis dari produk yang dipilih. Voucher bundling tidak menampilkan harga produk. Mendukung kertas thermal 58/68/80 mm dan A4 (label 68 mm, 3 kolom, untuk printer warna), mode Thermal atau Warna seperti label harga.</p>
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
                    <label>Ukuran kertas
                        <select name="paper">
                            <option value="80" {{ $paper === 80 ? 'selected' : '' }}>80 mm</option>
                            <option value="68" {{ $paper === 68 ? 'selected' : '' }}>68 mm</option>
                            <option value="58" {{ $paper === 58 ? 'selected' : '' }}>58 mm</option>
                            <option value="100" {{ $paper === 100 ? 'selected' : '' }}>A4 (label 68 mm)</option>
                        </select>
                    </label>
                    <label>Mode cetak
                        <select name="mode">
                            <option value="thermal" {{ $mode === 'thermal' ? 'selected' : '' }}>Thermal (hitam putih)</option>
                            <option value="warna" {{ $mode === 'warna' ? 'selected' : '' }}>Warna</option>
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