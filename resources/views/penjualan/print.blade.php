<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $penjualan->code }}</title>
    <script>
        // Ingat pilihan kertas per-perangkat, jadi cetak otomatis dari kasir & Re-Print
        // selalu langsung memakai ukuran terakhir (58 / 68 / 80) tanpa harus klik lagi.
        (function () {
            try {
                var url = new URL(window.location.href);
                var chosen = url.searchParams.get('paper');
                if (chosen === '58' || chosen === '68' || chosen === '80') {
                    window.localStorage.setItem('receipt-paper', chosen);
                    return;
                }
                var saved = window.localStorage.getItem('receipt-paper');
                if (saved === '58' || saved === '68' || saved === '80') {
                    url.searchParams.set('paper', saved);
                    window.location.replace(url.toString());
                }
            } catch (e) {}
        })();
    </script>
    @php
        // Ukuran default kalau belum pernah memilih (ubah ke '80' jika mayoritas printer 80mm).
        $defaultPaper = '58';

        // Profil per ukuran kertas. Untuk menambah ukuran lain cukup tambah satu baris di sini
        // (lalu satu tombol di bagian bawah halaman).
        //   printable = lebar area cetak NYATA printer thermal (POS-58 = 384 dot ~48mm, POS-80 = 576 dot ~72mm;
        //               kertas 68mm diperkirakan ~58mm, sesuaikan di sini bila hasil cetak masih terpotong / terlalu sempit)
        //   font      = ukuran huruf dalam pt
        $paperProfiles = [
            '58' => ['printable' => '48mm', 'base' => '7.5pt', 'small' => '6.75pt', 'title' => '10.5pt', 'tiny' => '6pt', 'item' => '7pt', 'total' => '9.5pt', 'logo' => '28mm'],
            '68' => ['printable' => '58mm', 'base' => '8.25pt', 'small' => '7.1pt', 'title' => '12pt', 'tiny' => '6.4pt', 'item' => '7.5pt', 'total' => '10.5pt', 'logo' => '34mm'],
            '80' => ['printable' => '72mm', 'base' => '9pt', 'small' => '7.5pt', 'title' => '13.5pt', 'tiny' => '6.75pt', 'item' => '8pt', 'total' => '11.5pt', 'logo' => '40mm'],
        ];
        $requestedPaper = request('paper');
        $paper = is_string($requestedPaper) && isset($paperProfiles[$requestedPaper]) ? $requestedPaper : $defaultPaper;
        $profile = $paperProfiles[$paper];
        $paperWidth = $paper . 'mm';
        $printable = $profile['printable'];
        $baseFont = $profile['base'];
        $smallFont = $profile['small'];
        $titleFont = $profile['title'];
        $tinyFont = $profile['tiny'];
        $itemNameFont = $profile['item'];
        $totalFont = $profile['total'];
        $logoMaxWidth = $profile['logo'];
        $items = $penjualan->displayItems();
        $itemCount = $items->sum(fn ($item) => (int) $item->qty);
        // Rincian per item dihitung dari satu sumber supaya semua angka di struk saling menjumlah:
        // harga normal x qty - diskon toko - diskon rafaksi = subtotal item.
        $lines = $items->map(function ($item) {
            $qty = (int) $item->qty;
            $net = (float) ($item->subtotal ?? ($qty * (float) $item->price));
            $afterStore = (float) ($item->base_subtotal ?? $net);
            $afterStore = max($afterStore, $net);
            $normalPrice = (float) ($item->harga_aktif ?? 0);
            $gross = max($normalPrice * $qty, $afterStore);

            return [
                'item' => $item,
                'qty' => $qty,
                'gross' => $gross,
                'unit' => $qty > 0 ? $gross / $qty : $gross,
                'store' => $gross - $afterStore,
                'promo' => $afterStore - $net,
                'net' => $net,
            ];
        });
        $subtotalGross = (float) $lines->sum('gross');
        $storeDiscountTotal = (float) $lines->sum('store');
        $promotionTotal = (float) $lines->sum('promo');
        $discountTotal = $storeDiscountTotal + $promotionTotal;
        $voucherTotal = (float) ($penjualan->voucher_total ?? 0);
        $roundingAmount = (float) ($penjualan->rounding_amount ?? 0);
        $grandTotal = (float) ($penjualan->grand_total
            ?? max(0, $subtotalGross - $discountTotal - $voucherTotal + $roundingAmount));
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
            /* Font formal (sans-serif tegas, tetap terbaca di printer thermal) */
            font-family: Arial, 'Helvetica Neue', Helvetica, sans-serif;
            letter-spacing: 0;
            font-size: {{ $baseFont }};
            font-weight: normal;
            color: #000;
            line-height: 1.3;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .store-logo { max-width: {{ $logoMaxWidth }}; max-height: 18mm; margin-bottom: 1mm; filter: grayscale(1) contrast(1.6); }
        .store-name { font-size: {{ $titleFont }}; font-weight: bold; letter-spacing: .5px; word-break: break-word; }
        .small { font-size: {{ $smallFont }}; }
        .tiny { font-size: {{ $tinyFont }}; line-height: 1.25; word-break: break-word; }
        .header-info { margin-top: 1mm; }
        .meta { margin-top: 3mm; font-size: {{ $smallFont }}; }

        /* Hanya 2 garis: batas info transaksi/barang (putus-putus) dan barang/total (tegas). Sisanya jarak. */
        .rule-dash { border: none; border-top: 1px dashed #000; margin: 3mm 0; }
        .rule-solid { border: none; border-top: 1px solid #000; margin: 0 0 2.5mm; }

        .row { display: flex; justify-content: space-between; align-items: baseline; gap: 2mm; padding: .3px 0; }
        .item .row { padding: 0; }
        .row .val { text-align: right; white-space: nowrap; }
        .row .key { min-width: 0; word-break: break-word; }

        .item { margin-bottom: 1.2mm; page-break-inside: avoid; line-height: 1.2; }
        .item-name { font-size: {{ $itemNameFont }}; font-weight: bold; overflow-wrap: anywhere; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; max-height: 2.4em; margin-bottom: .2mm; }
        .item-calc { font-size: {{ $smallFont }}; }
        .item-disc { padding-left: 3mm; font-size: {{ $smallFont }}; }

                /* Ringkasan: label menempel di kiri nominal, seluruh blok rata kanan.
           Lebar kolom mengikuti nominal terpanjang, jadi blok melebar ke kiri bila angka makin besar. */
        .summary { display: grid; grid-template-columns: auto auto; justify-content: end; align-items: baseline; column-gap: 3mm; row-gap: .6px; }
        .summary .lbl, .summary .num { text-align: right; }
        .summary .num { white-space: nowrap; }
        .summary .num.wrap-ok { white-space: normal; overflow-wrap: anywhere; }
        .summary .grand { font-size: {{ $totalFont }}; font-weight: bold; padding: 1mm 0; }
        .block { margin-top: 2.5mm; }
        .footer-msg { margin-top: 0; font-size: {{ $smallFont }}; word-break: break-word; }
        .footer-msg img { max-width: 100%; }
        .printed-at { margin-top: 2mm; font-size: {{ $tinyFont }}; }

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
        @if ($penjualan->outlet?->alamat)<div class="tiny header-info">{{ $penjualan->outlet->alamat }}</div>@endif
        @if ($penjualan->outlet?->desc)<div class="tiny">{{ $penjualan->outlet->desc }}</div>@endif
        @if ($penjualan->outlet?->npwp)<div class="tiny">NPWP : {{ $penjualan->outlet->npwp }}</div>@endif
    </div>

    <div class="meta">
        <div class="row"><span class="key">No</span><span class="val bold">{{ $penjualan->code }}</span></div>
        <div class="row"><span class="key">Tgl</span><span class="val">{{ optional($penjualan->created_at)->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span class="key">Kasir</span><span class="val">{{ $penjualan->kasir?->name ?? '—' }} · {{ $penjualan->cashierShift?->name ?? 'Kasir' }}</span></div>
        @if ($penjualan->customer?->name)<div class="row"><span class="key">Customer</span><span class="val">{{ $penjualan->customer->name }}</span></div>@endif
    </div>

    <hr class="rule-dash">

    @foreach ($lines as $line)
        @php $lineDiscount = $line['store'] + $line['promo']; @endphp
        <div class="item">
            <div class="item-name">{{ $line['item']->product?->name ?? 'Produk' }}</div>
            <div class="row">
                <span class="key item-calc">{{ $line['qty'] }} x {{ number_format($line['unit'], 0, ',', '.') }}</span>
                <span class="val">{{ number_format($line['gross'], 0, ',', '.') }}</span>
            </div>
            @if ($lineDiscount > 0)
                <div class="row item-disc"><span class="key">Disc.</span><span class="val">-{{ number_format($lineDiscount, 0, ',', '.') }}</span></div>
            @endif
        </div>
    @endforeach

    <hr class="rule-solid">

    <div class="summary">
        <span class="lbl small">Jumlah item</span><span class="num small">{{ $itemCount }}</span>
        <span class="lbl">Subtotal</span><span class="num">{{ number_format($subtotalGross, 0, ',', '.') }}</span>
        @if ($discountTotal > 0)<span class="lbl">Diskon</span><span class="num">-{{ number_format($discountTotal, 0, ',', '.') }}</span>@endif
        @if ($voucherTotal > 0)<span class="lbl">Voucher</span><span class="num">-{{ number_format($voucherTotal, 0, ',', '.') }}</span>@endif
        @if ($roundingAmount > 0)<span class="lbl">Pembulatan</span><span class="num">+{{ number_format($roundingAmount, 0, ',', '.') }}</span>@endif
        <span class="lbl grand">TOTAL</span><span class="num grand">Rp {{ number_format($grandTotal, 0, ',', '.') }}</span>
    </div>

    <hr class="rule-dash">

    <div class="summary">
        <span class="lbl">{{ $penjualan->paymentMethod?->name ?? $penjualan->payment_method_name ?? 'Tunai' }}</span><span class="num">{{ number_format($paidAmount, 0, ',', '.') }}</span>
        <span class="lbl bold">Kembali</span><span class="num bold">{{ number_format($changeAmount, 0, ',', '.') }}</span>
        @if ($penjualan->payment_reference)<span class="lbl small">Ref.</span><span class="num small wrap-ok">{{ $penjualan->payment_reference }}</span>@endif
    </div>
    @if (($discountTotal + $voucherTotal) > 0)
        <div class="center small block">Anda hemat Rp {{ number_format($discountTotal + $voucherTotal, 0, ',', '.') }}</div>
    @endif

    <hr class="rule-dash">

    @if ($penjualan->outlet?->footer)
        <div class="center footer-msg">{!! $penjualan->outlet->footer !!}</div>
    @else
        <div class="center footer-msg"><div>Harga sudah termasuk PPN</div><div class="bold">Terima kasih atas kunjungan Anda</div></div>
    @endif
    <div class="center printed-at">Dicetak {{ now()->format('d/m/Y H:i:s') }}</div>

    <div class="no-print">
        <a href="{{ route('outlet.show', $penjualan->outlet_id) }}">Kembali</a>
        <button type="button" class="primary" onclick="window.print(); return false;">Print {{ $paper }}mm</button>
        <div class="papers">
            <a href="{{ route('penjualan.print', [$penjualan, 'paper' => '58']) }}" class="{{ $paper === '58' ? 'active' : '' }}">Kertas 58mm</a>
            <a href="{{ route('penjualan.print', [$penjualan, 'paper' => '68']) }}" class="{{ $paper === '68' ? 'active' : '' }}">Kertas 68mm</a>
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