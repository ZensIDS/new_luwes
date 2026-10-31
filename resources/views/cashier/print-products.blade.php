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

        /* ===== Label harga =====
           Tinggi label dikunci: promo (ada diskon) maks 32,4 mm di kertas 80 mm (< 3,3 cm), tanpa diskon 21,6 mm.
           Ukuran lain mengikuti skala $k (68 mm / A4 => promo 27,5 mm & tanpa diskon 18,4 mm). */
        .label-sheet { width: {{ $isA4 ? '204mm' : $w . 'mm' }}; margin: 0 auto; {{ $isA4 ? 'display:grid; grid-template-columns:repeat(3, 68mm); gap:0; align-items:start;' : '' }} }
        .label-outer { width: {{ $w }}mm; padding: 0; break-inside: avoid; page-break-inside: avoid; background: #fff; {{ $isA4 ? 'outline:0.2mm dashed #888; outline-offset:-0.2mm;' : '' }} }
        .lbl { --accent: {{ $mode === 'warna' ? '#e0577a' : '#000' }}; --soft: {{ $mode === 'warna' ? '#fbe6ea' : '#fff' }}; --boxline: {{ $mode === 'warna' ? 'none' : '0.25mm solid #000' }};
               position: relative; display: flex; flex-direction: column; overflow: hidden; padding: {{ $mm(1.4) }} {{ $mm(3.5) }} {{ $mm(1.2) }}; color: #000; background: #fff; font-family: Arial, Helvetica, sans-serif;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .lbl.promo { height: {{ $mm(32.4) }}; }
        .lbl.plain { height: {{ $mm(21.6) }}; padding-top: {{ $mm(.8) }}; padding-bottom: {{ $mm(.7) }}; }
        .lbl .name { flex: none; font-size: {{ $mm(3.1) }}; font-weight: 900; text-transform: uppercase; line-height: 1.08; letter-spacing: .1px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .lbl.promo .name { height: {{ $mm(6.7) }}; }
        .lbl.plain .name { font-size: {{ $mm(2.7) }}; }
        .lbl .box { position: relative; flex: none; background: var(--soft); border: var(--boxline); border-radius: {{ $mm(2.2) }}; }
        .lbl.promo .box { margin-top: {{ $mm(2.4) }}; padding: {{ $mm(2) }} {{ $mm(15.5) }} {{ $mm(.8) }} {{ $mm(2.6) }}; }
        .lbl.plain .box { margin-top: auto; padding: {{ $mm(.3) }} {{ $mm(2.6) }}; }
        .lbl .pill { position: absolute; top: {{ $mm(-2) }}; left: {{ $mm(-.8) }}; background: var(--accent); color: #fff; font-size: {{ $mm(2.3) }}; font-weight: 800; letter-spacing: .2px; line-height: 1; padding: {{ $mm(1) }} {{ $mm(2.6) }}; border-radius: 99px; }
        .lbl .price { color: var(--accent); font-weight: 900; line-height: 1; white-space: nowrap; display: flex; align-items: baseline; gap: {{ $mm(1) }}; }
        .lbl .price .rp { font-size: 0.54em; font-weight: 800; }
        .lbl .price .num { letter-spacing: -.2px; }
        .lbl .normal { margin-top: {{ $mm(.8) }}; font-size: {{ $mm(2.7) }}; font-weight: 700; line-height: 1.15; }
        .lbl .normal del { text-decoration: none; position: relative; font-size: {{ $mm(3) }}; font-weight: 800; margin-left: {{ $mm(.5) }}; }
        .lbl .normal del::after { content: ''; position: absolute; left: -6%; right: -6%; top: 52%; border-top: {{ $mm(.4) }} solid var(--accent); transform: rotate(-4deg); }
        /* Lingkaran diskon: diturunkan, menumpang di sisi kanan bawah box harga sehingga tidak menambah tinggi label. */
        .lbl .circle { position: absolute; right: {{ $mm(2.4) }}; top: {{ $mm(14.6) }}; z-index: 2; width: {{ $mm(13.2) }}; height: {{ $mm(13.2) }}; border-radius: 50%; background: var(--accent); color: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1; }
        .lbl .circle .c-top { font-size: {{ $mm(2.1) }}; font-weight: 800; letter-spacing: .2px; }
        .lbl .circle .c-pct { font-size: {{ $mm(5) }}; font-weight: 900; margin-top: {{ $mm(.4) }}; }
        /* Barcode diperkecil: lebar tetap, tinggi rendah (sebelumnya selebar label & 7,8 mm). */
        .lbl .foot { display: flex; align-items: flex-end; gap: {{ $mm(2) }}; margin-top: auto; padding-right: {{ $mm(14.5) }}; }
        .lbl .bc { flex: 0 1 {{ $mm(30) }}; min-width: 0; height: {{ $mm(4.4) }}; overflow: hidden; }
        .lbl .bc svg { display: block; width: 100%; height: 100%; shape-rendering: crispEdges; }
        .lbl .digits { flex: 0 1 auto; min-width: 0; text-align: right; font-family: 'Courier New', monospace; font-size: {{ $mm(2.3) }}; font-weight: 700; letter-spacing: {{ $mm(.1) }}; line-height: 1.1; white-space: nowrap; }
        .lbl .digits small { display: block; font-family: Arial, sans-serif; font-size: {{ $mm(1.8) }}; font-weight: 400; letter-spacing: 0; margin-top: {{ $mm(.3) }}; }
        /* Tanpa diskon: nama, box harga (besar), lalu baris bawah = barcode kecil + kode + tanggal cetak. */
        .lbl.plain .foot { margin-top: {{ $mm(.6) }}; padding-right: 0; align-items: center; }
        .lbl.plain .bc { height: {{ $mm(3.6) }}; }
        .lbl.plain .digits { font-size: {{ $mm(2.2) }}; }
        .lbl.plain .date { margin-left: auto; font-size: {{ $mm(1.9) }}; white-space: nowrap; }
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
                // Ukuran harga dasar (kertas 80 mm): promo 7,8 mm, tanpa diskon 9 mm; mengecil bila angkanya panjang.
                if ($hasDisc) {
                    $priceScale = $digitsLen <= 10 ? 1 : .8;
                    $priceSize = round(12 * $k * $priceScale, 2) . 'mm';
                } else {
                    $priceScale = $digitsLen <= 10 ? 1 : .8;
                    $priceSize = round(12 * $k * $priceScale, 2) . 'mm';
                }
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
                    @if ($hasDisc)
                        <div class="lbl promo">
                            <div class="name" title="{{ $product->name }}">{{ $product->name }}</div>
                            <div class="box">
                                <div class="pill">HARGA PROMO</div>
                                <div class="price" style="font-size: {{ $priceSize }};"><span class="rp">Rp</span><span class="num">{{ $netText }}</span></div>
                                <div class="normal">Harga Normal Rp <del>{{ number_format($product->print_price_strike, 0, ',', '.') }}</del></div>
                            </div>
                            <div class="circle">
                                <span class="c-top">DISKON</span>
                                <span class="c-pct">{{ $product->print_discount_percent }}%</span>
                            </div>
                            <div class="foot">
                                <div class="bc">{!! $barcodeSvg !!}</div>
                                <div class="digits">{{ $product->code }}<small>{{ $printedAt?->format('d/m/Y') }}</small></div>
                            </div>
                        </div>
                    @else
                        <div class="lbl plain">
                            <div class="name" title="{{ $product->name }}">{{ $product->name }}</div>
                            <div class="box">
                                <div class="price" style="font-size: {{ $priceSize }};"><span class="rp">Rp</span><span class="num">{{ $netText }}</span></div>
                            </div>
                            <div class="foot">
                                <div class="bc">{!! $barcodeSvg !!}</div>
                                <div class="digits">{{ $product->code }}</div>
                                <div class="date">{{ $printedAt?->format('d/m/Y') }}</div>
                            </div>
                        </div>
                    @endif
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