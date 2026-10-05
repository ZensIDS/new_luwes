<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $penjualan->code }}</title>
    <script>
        // Ingat pilihan kertas per-perangkat, jadi cetak otomatis dari kasir & Re-Print
        // selalu langsung memakai ukuran terakhir (58 / 80) tanpa harus klik lagi.
        (function () {
            try {
                var url = new URL(window.location.href);
                var chosen = url.searchParams.get('paper');
                if (chosen === '58' || chosen === '80') {
                    window.localStorage.setItem('receipt-paper', chosen);
                    return;
                }
                var saved = window.localStorage.getItem('receipt-paper');
                if (saved === '58' || saved === '80') {
                    url.searchParams.set('paper', saved);
                    window.location.replace(url.toString());
                }
            } catch (e) {}
        })();
    </script>
    @php
        // Ukuran default kalau belum pernah memilih (ubah ke '80' jika mayoritas printer 80mm).
        $defaultPaper = '58';
        $paper = in_array(request('paper'), ['58', '80'], true) ? request('paper') : $defaultPaper;
        $paperWidth = $paper . 'mm';
        // Lebar area cetak NYATA printer thermal: POS-58 = 384 dot (~48mm), POS-80 = 576 dot (~72mm).
        // Kertas 58mm tidak bisa dicetak penuh 58mm, makanya sebelumnya sisi kanan terpotong / tidak pas.
        $printable = $paper === '58' ? '48mm' : '72mm';
        $baseFont = $paper === '58' ? '12px' : '14px';
        $smallFont = $paper === '58' ? '11px' : '12px';
        $titleFont = $paper === '58' ? '16px' : '20px';
        $totalFont = $paper === '58' ? '15px' : '18px';
        $items = $penjualan->items;
        $itemCount = $items->sum(fn ($item) => (int) $item->qty);
        $subtotalBeforePromotion = $items->sum(fn ($item) => (float) (
            $item->base_subtotal
                ?? ($item->base_price !== null
                    ? (float) $item->qty * (float) $item->base_price
                    : ($item->subtotal ?? ((float) $item->qty * (float) $item->price)))
        ));
        $promotionTotal = (float) ($penjualan->promotion_total ?? 0);
        $voucherTotal = (float) ($penjualan->voucher_total ?? 0);
        $grandTotal = (float) ($penjualan->grand_total
            ?? max(0, $subtotalBeforePromotion - $promotionTotal - $voucherTotal));
        $paidAmount = (float) ($penjualan->paid_amount ?? $penjualan->total ?? 0);
        $changeAmount = (float) ($penjualan->change_amount ?? max(0, $paidAmount - $grandTotal));
    @endphp
    <style>
        @page { size: {{ $paperWidth }} auto; margin: 0; }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            width: {{ $printable }};
            margin: 0 auto;
            padding: 2mm 0 8mm;
            font-family: 'Courier New', ui-monospace, monospace;
            font-size: {{ $baseFont }};
            font-weight: normal;
            color: #000;
            line-height: 1.3;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .store-logo { max-width: {{ $paper === '58' ? '28mm' : '40mm' }}; max-height: 18mm; margin-bottom: 1mm; filter: grayscale(1) contrast(1.6); }
        .store-name { font-size: {{ $titleFont }}; font-weight: bold; letter-spacing: .5px; word-break: break-word; }
        .small { font-size: {{ $smallFont }}; }
        hr { border: none; border-top: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: auto; }
        td { vertical-align: top; }
        .meta td { padding: 1px 0; }
        .meta td:first-child { white-space: nowrap; padding-right: 3px; }
        .meta td:last-child { text-align: right; word-break: break-word; }
        .item-row td { padding: 1px 0; }
        .item-name { word-break: break-word; overflow-wrap: anywhere; font-weight: bold; }
        .qty-price { font-size: {{ $smallFont }}; }
        .price-col { text-align: right; white-space: nowrap; padding-left: 3px; }
        .disc-row { font-size: {{ $smallFont }}; }
        .disc-value { text-align: right; white-space: nowrap; padding-left: 3px; }
        .strike { text-decoration: line-through; }
        .totals td { padding: 1px 0; }
        .totals .label { text-align: left; }
        .totals .value { text-align: right; white-space: nowrap; padding-left: 3px; }
        .grand-total { font-size: {{ $totalFont }}; font-weight: bold; }
        .footer-msg { margin-top: 6px; font-size: {{ $smallFont }}; word-break: break-word; }
        .footer-msg img { max-width: 100%; }

        /* Tampilan layar saja (tombol & petunjuk) */
        .no-print { margin: 14px auto 0; width: 100%; max-width: 340px; font-family: Arial, sans-serif; font-weight: normal; font-size: 14px; }
        .no-print a, .no-print button { display: block; width: 100%; margin-top: 6px; padding: 10px; border: 1px solid #777; background: #fff; color: #000; font: inherit; text-align: center; text-decoration: none; cursor: pointer; border-radius: 4px; }
        .no-print .primary { background: #111; color: #fff; border-color: #111; font-weight: bold; }
        .no-print .papers { display: flex; gap: 6px; }
        .no-print .papers a { margin-top: 6px; }
        .no-print .papers a.active { background: #e8f0fe; border-color: #1a56db; font-weight: bold; }
        .no-print .tips { margin-top: 10px; padding: 8px 10px; background: #fff8e1; border: 1px solid #f0d98a; border-radius: 4px; font-size: 12px; line-height: 1.5; text-align: left; }
        @media screen { body { padding-left: 0; padding-right: 0; } }
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>

<body>
    <div class="center">
        @if ($penjualan->outlet?->logo)
            <img class="store-logo" src="{{ asset($penjualan->outlet->logo) }}" alt="{{ $penjualan->outlet->name }}">
        @endif
        <div class="store-name">{{ $penjualan->outlet?->name ?? 'LUWES' }}</div>
        @if ($penjualan->outlet?->alamat)<div class="small">{{ $penjualan->outlet->alamat }}</div>@endif
        @if ($penjualan->outlet?->desc)<div class="small">{{ $penjualan->outlet->desc }}</div>@endif
        @if ($penjualan->outlet?->npwp)<div class="small">NPWP : {{ $penjualan->outlet->npwp }}</div>@endif
    </div>

    <hr>

    <table class="meta small">
        <tr><td>No. Struk</td><td>{{ $penjualan->code }}</td></tr>
        <tr><td>Tanggal</td><td>{{ optional($penjualan->created_at)->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Kassa</td><td>{{ $penjualan->kasir?->name ?? '—' }}</td></tr>
        <tr><td>Kasir</td><td>{{ $penjualan->cashierShift?->name ?? 'Kasir' }}</td></tr>
        <tr><td>Jam Cetak</td><td>{{ now()->format('H:i:s') }}</td></tr>
        <tr><td>Tgl Cetak</td><td>{{ now()->format('d/m/Y') }}</td></tr>
        @if ($penjualan->customer?->name)<tr><td>Customer</td><td>{{ $penjualan->customer->name }}</td></tr>@endif
    </table>

    <hr>

    <table>
        @foreach ($items as $item)
            @php
                $lineSubtotal = (float) ($item->subtotal ?? ((float) $item->qty * (float) $item->price));
                $unitPrice = (float) $item->price;
                $referencePrice = (float) ($item->harga_aktif ?? $item->base_price ?? $unitPrice);
                $discountPerUnit = max(0, $referencePrice - $unitPrice);
                $discountTotal = $discountPerUnit * (float) $item->qty;
            @endphp
            <tr class="item-row"><td colspan="2" class="item-name">{{ $item->product?->name ?? 'Produk' }}</td></tr>
            <tr class="item-row qty-price">
                <td>{{ $item->qty }} x @if ($referencePrice > $unitPrice)<span class="strike">@currency($referencePrice)</span> @endif @currency($unitPrice)</td>
                <td class="price-col">@currency($lineSubtotal)</td>
            </tr>
            @if ($discountTotal > 0)
                <tr class="disc-row"><td>Diskon Item ({{ $item->qty }}x @currency($discountPerUnit))</td><td class="disc-value">-@currency($discountTotal)</td></tr>
            @endif
        @endforeach
    </table>

    <hr>

    <table class="totals">
        <tr><td class="label">Jumlah Item</td><td class="value">{{ $itemCount }}</td></tr>
        <tr><td class="label">Subtotal</td><td class="value">@currency($subtotalBeforePromotion)</td></tr>
        @if ($promotionTotal > 0)<tr><td class="label">Diskon Rafaksi</td><td class="value">-@currency($promotionTotal)</td></tr>@endif
        @if ($voucherTotal > 0)<tr><td class="label">Voucher</td><td class="value">-@currency($voucherTotal)</td></tr>@endif
        <tr class="grand-total"><td class="label">TOTAL</td><td class="value">@currency($grandTotal)</td></tr>
    </table>

    <hr>

    <table class="totals">
        <tr><td class="label">{{ $penjualan->paymentMethod?->name ?? $penjualan->payment_method_name ?? 'Tunai' }}</td><td class="value">@currency($paidAmount)</td></tr>
        <tr><td class="label">Kembali</td><td class="value">@currency($changeAmount)</td></tr>
        @if ($penjualan->payment_reference)<tr class="small"><td class="label">Ref.</td><td class="value">{{ $penjualan->payment_reference }}</td></tr>@endif
    </table>

    <hr>

    @if ($penjualan->outlet?->footer)
        <div class="center footer-msg">{!! $penjualan->outlet->footer !!}</div>
    @else
        <div class="center footer-msg"><div>Harga Barang Sudah termasuk PPN</div><div>Terima Kasih Atas Kunjungan Anda</div></div>
    @endif

    <div class="no-print">
        <a href="{{ route('outlet.show', $penjualan->outlet_id) }}">Kembali</a>
        <button type="button" class="primary" onclick="window.print(); return false;">Print {{ $paper }}mm</button>
        <div class="papers">
            <a href="{{ route('penjualan.print', [$penjualan, 'paper' => '58']) }}" class="{{ $paper === '58' ? 'active' : '' }}">Kertas 58mm</a>
            <a href="{{ route('penjualan.print', [$penjualan, 'paper' => '80']) }}" class="{{ $paper === '80' ? 'active' : '' }}">Kertas 80mm</a>
        </div>
        <div class="tips">
            <b>Supaya pas di printer thermal:</b><br>
            1. Di dialog print pilih printer POS-nya, ukuran kertas <b>{{ $paper }}mm</b> (atau Roll Paper {{ $paper }}mm).<br>
            2. Margins: <b>None</b>, Scale: <b>100%</b> (jangan "Fit to page").<br>
            3. Matikan <b>Headers and footers</b>.
        </div>
    </div>

    @if (request('auto'))
        <script>
            (function () {
                var printed = false;
                function doPrint() {
                    if (printed) return;
                    printed = true;
                    setTimeout(function () { window.print(); }, 150);
                }
                // Tunggu logo selesai dimuat supaya tinggi struk tidak berubah saat dicetak.
                window.addEventListener('load', function () {
                    var pending = Array.prototype.filter.call(document.images, function (img) { return !img.complete; });
                    if (!pending.length) return doPrint();
                    var left = pending.length;
                    pending.forEach(function (img) {
                        var done = function () { if (--left <= 0) doPrint(); };
                        img.addEventListener('load', done);
                        img.addEventListener('error', done);
                    });
                    setTimeout(doPrint, 2000);
                });
                window.addEventListener('afterprint', function () { window.location.href = @json(route('outlet.show', $penjualan->outlet_id)); });
            })();
        </script>
    @endif
</body>

</html>