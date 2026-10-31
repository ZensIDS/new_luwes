<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label Harga</title>
    @php
        // Mode cetak: thermal (hitam putih) atau warna. Layout sama, hanya variabel warna yang berbeda.
        $mode = ($mode ?? 'thermal') === 'warna' ? 'warna' : 'thermal';
        // 58/68/80 = kertas thermal (lebar label = lebar kertas). 100 = A4 (label 68 mm, 3 kolom per baris, cocok printer warna).
        $paper = in_array((int) request('paper'), [58, 68, 80, 100], true) ? (int) request('paper') : 80;
        $isA4 = $paper === 100;
        $w = $isA4 ? 68 : $paper;
        $k = $w / 80; // faktor skala dari ukuran dasar 80 mm
        $mm = fn (float $v) => round($v * $k, 2) . 'mm';
    @endphp
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 20px; font: 14px Arial, sans-serif; color: #222; background: #f4f6f9; }
        .toolbar { max-width: 1100px; margin: 0 auto 18px; padding: 16px; background: #fff; border: 1px solid #ddd; }
        .toolbar h2 { margin: 0 0 12px; font-size: 20px; }
        .toolbar form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        input, select, button { min-height: 34px; padding: 6px 9px; border: 1px solid #bbb; border-radius: 3px; background: #fff; }
        button { cursor: pointer; background: #337ab7; color: #fff; border-color: #286090; }
        button:disabled { cursor: not-allowed; opacity: .6; }
        .muted { color: #777; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { padding: 7px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }

        /* ===== Label harga (desain baru) ===== */
        .label-sheet { width: {{ $isA4 ? '204mm' : $w . 'mm' }}; margin: 0 auto; {{ $isA4 ? 'display:grid; grid-template-columns:repeat(3, 68mm); gap:0;' : '' }} }
        .label-outer { width: {{ $w }}mm; padding: {{ $isA4 ? '0' : '1.5mm 2mm' }}; break-inside: avoid; page-break-inside: avoid; background: #fff; {{ $isA4 ? 'border:0.2mm dashed #888;' : '' }} }
        .lbl { --accent: {{ $mode === 'warna' ? '#e0577a' : '#000' }}; --soft: {{ $mode === 'warna' ? '#fbe6ea' : '#fff' }}; --boxline: {{ $mode === 'warna' ? 'none' : '0.25mm solid #000' }};
               position: relative; padding: {{ $mm(3) }} {{ $mm(3.5) }} {{ $mm(2.5) }}; color: #000; background: #fff; font-family: Arial, Helvetica, sans-serif;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .lbl .head { position: relative; min-height: {{ $mm(11) }}; padding-right: {{ $mm(19) }}; }
        .lbl .head.no-circle { padding-right: 0; min-height: {{ $mm(8) }}; }
        .lbl .name { font-size: {{ $mm(4.1) }}; font-weight: 900; text-transform: uppercase; line-height: 1.15; letter-spacing: .1px; max-height: 2.35em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .lbl .circle { position: absolute; top: {{ $mm(-0.5) }}; right: 0; z-index: 2; width: {{ $mm(17.5) }}; height: {{ $mm(17.5) }}; border-radius: 50%; background: var(--accent); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1; }
        .lbl .circle .c-top { font-size: {{ $mm(2.5) }}; font-weight: 800; letter-spacing: .2px; }
        .lbl .circle .c-pct { font-size: {{ $mm(6.4) }}; font-weight: 900; margin-top: {{ $mm(.4) }}; }
        .lbl .box { position: relative; margin-top: {{ $mm(3.4) }}; padding: {{ $mm(3.8) }} {{ $mm(3) }} {{ $mm(2) }}; background: var(--soft); border: var(--boxline); border-radius: {{ $mm(2.6) }}; }
        .lbl .box.single { margin-top: {{ $mm(1) }}; padding-top: {{ $mm(2.4) }}; }
        .lbl .pill { position: absolute; top: {{ $mm(-2.4) }}; left: {{ $mm(-1) }}; background: var(--accent); color: #fff; font-size: {{ $mm(2.6) }}; font-weight: 800; letter-spacing: .2px; line-height: 1; padding: {{ $mm(1.2) }} {{ $mm(3) }}; border-radius: 99px; }
        .lbl .price { color: var(--accent); font-weight: 900; line-height: 1; white-space: nowrap; display: flex; align-items: baseline; gap: {{ $mm(1.2) }}; }
        .lbl .price .rp { font-size: {{ $mm(5) }}; font-weight: 800; }
        .lbl .price .num { letter-spacing: -.2px; }
        .lbl .normal { margin-top: {{ $mm(1.8) }}; padding-top: {{ $mm(1.2) }}; border-top: 0.2mm solid var(--accent); font-size: {{ $mm(2.6) }}; font-weight: 700; }
        .lbl .normal del { text-decoration: none; position: relative; font-size: {{ $mm(3.4) }}; font-weight: 800; margin-left: {{ $mm(.6) }}; }
        .lbl .normal del::after { content: ''; position: absolute; left: -6%; right: -6%; top: 52%; border-top: {{ $mm(.45) }} solid var(--accent); transform: rotate(-4deg); }
        .lbl .foot { display: flex; align-items: flex-end; gap: {{ $mm(2.5) }}; margin-top: {{ $mm(2.6) }}; }
        .lbl .bc { flex: 1 1 auto; min-width: 0; height: {{ $mm(7.8) }}; overflow: hidden; }
        .lbl .bc svg { display: block; width: 100%; height: {{ $mm(7.8) }}; shape-rendering: crispEdges; }
        .lbl .digits { flex: 0 0 auto; text-align: right; font-family: 'Courier New', monospace; font-size: {{ $mm(2.6) }}; font-weight: 700; letter-spacing: .6px; line-height: 1.1; }
        .lbl .digits small { display: block; font-family: Arial, sans-serif; font-size: 6px; font-weight: 400; letter-spacing: 0; margin-top: .6mm; }
        .cut-line { width: {{ $w }}mm; text-align: center; font-size: 8px; color: #555; padding: 1.5mm 0; letter-spacing: 1px; }

        @page { size: {{ $isA4 ? 'A4' : $w . 'mm auto' }}; margin: {{ $isA4 ? '3mm' : '0' }}; }
        @media print {
            body { width: {{ $isA4 ? 'auto' : $w . 'mm' }}; margin: 0 auto; padding: 0; background: #fff; }
            .toolbar { display: none !important; }
        }
    </style>
</head>

<body>
    @if (! $printing)
        <div class="toolbar">
            <h2>Cetak label harga produk</h2>
            <p class="muted">Harga coret dihitung dari HPP asli (tanpa potongan apa pun) + margin yang dikalikan dari HPP asli. Label mendukung kertas thermal 58mm, 68mm, dan 80mm, dan A4 (label 68 mm, 3 kolom, untuk printer warna). Pilih mode cetak Thermal (hitam putih) atau Warna sebelum mencetak. Persen diskon dihitung dari harga coret terhadap harga jual.</p>
            <form method="GET" action="{{ route('cashier.print.products') }}" target="_blank">
                @if ($outlets->count() > 1)
                    <label>Outlet
                        <select name="outlet_id">
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}" {{ (int) $outletId === (int) $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @elseif ($outletId)
                    <input type="hidden" name="outlet_id" value="{{ $outletId }}">
                @endif
                <input type="search" name="search" value="{{ $search }}" placeholder="Cari nama atau barcode">
                <button type="submit">Tampilkan produk</button>
            </form>

            @if ($products->isNotEmpty())
                <form method="POST" action="{{ route('cashier.print.products') }}" target="_blank" style="display:block;">
                    @csrf
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
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-products"></th>
                                <th>Barcode (kode)</th>
                                <th>Nama</th>
                                <th>Harga Coret</th>
                                <th>Harga Jual POS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($products as $product)
                                <tr>
                                    <td><input class="product-check" type="checkbox" name="product_ids[]" value="{{ $product->id }}"></td>
                                    <td>{{ $product->code }}</td>
                                    <td>{{ $product->name }}</td>
                                    <td>
                                        @if ($product->print_has_discount)<del>@currency($product->print_price_strike)</del>@endif
                                        <small class="muted">HPP: @currency($product->print_price_hpp) + Margin: @currency($product->print_price_margin)</small>
                                    </td>
                                    <td>@currency($product->print_price_net)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="submit" style="margin-top:12px;">Cetak label terpilih</button>
                </form>
            @else
                <p class="muted">Produk tidak ditemukan.</p>
            @endif
        </div>
    @else
        <div class="label-sheet">
        @foreach ($printItems as $product)
            @php
                $hasDisc = $product->print_has_discount;
                $netText = number_format($product->print_price_net, 0, ',', '.');
                $digitsLen = strlen($netText);
                $priceScale = $digitsLen <= 7 ? 1 : ($digitsLen === 8 ? .85 : ($digitsLen === 9 ? .75 : .62));
                $priceSize = round(11.5 * $k * $priceScale, 2) . 'mm';
                // Barcode Code 128 (sama seperti voucher); beri viewBox agar pas selebar area.
                $barcodeSvg = DNS1D::getBarcodeSVG((string) $product->code, 'C128', 1, 30, 'black', false, false);
                if (! str_contains($barcodeSvg, 'viewBox')
                    && preg_match('/<svg\b[^>]*\swidth="([\d.]+)(?:px)?"/', $barcodeSvg, $svgW)
                    && preg_match('/<svg\b[^>]*\sheight="([\d.]+)(?:px)?"/', $barcodeSvg, $svgH)) {
                    $barcodeSvg = preg_replace('/<svg\b/', '<svg viewBox="0 0 ' . $svgW[1] . ' ' . $svgH[1] . '"', $barcodeSvg, 1);
                }
                if (! str_contains($barcodeSvg, 'preserveAspectRatio')) {
                    $barcodeSvg = preg_replace('/<svg\b/', '<svg preserveAspectRatio="none"', $barcodeSvg, 1);
                }
            @endphp
            @for ($index = 0; $index < $product->print_qty; $index++)
                <div class="label-outer">
                    <div class="lbl">
                        <div class="head {{ $hasDisc ? '' : 'no-circle' }}">
                            <div class="name" title="{{ $product->name }}">{{ $product->name }}</div>
                            @if ($hasDisc)
                                <div class="circle">
                                    <span class="c-top">DISKON</span>
                                    <span class="c-pct">{{ $product->print_discount_percent }}%</span>
                                </div>
                            @endif
                        </div>
                        <div class="box {{ $hasDisc ? '' : 'single' }}">
                            @if ($hasDisc)<div class="pill">HARGA PROMO</div>@endif
                            <div class="price" style="font-size: {{ $priceSize }};"><span class="rp">Rp</span><span class="num">{{ $netText }}</span></div>
                            @if ($hasDisc)
                                <div class="normal">Harga Normal Rp <del>{{ number_format($product->print_price_strike, 0, ',', '.') }}</del></div>
                            @endif
                        </div>
                        <div class="foot">
                            <div class="bc">{!! $barcodeSvg !!}</div>
                            <div class="digits">{{ $product->code }}<small>{{ $printedAt?->format('d/m/Y') }}</small></div>
                        </div>
                    </div>
                </div>
                @if (! $isA4)
                    <div class="cut-line">- - - - - - - - - - - - - - - - - - - - - - - -</div>
                @endif
            @endfor
        @endforeach
        </div>
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>

</html>