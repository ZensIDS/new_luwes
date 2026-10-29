<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $penjualan->code }}</title>
    <script src="{{ asset('js/qz-tray.js') }}"></script>
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
        //   shift     = geser isi nota ke kanan (mm) saat DICETAK. Printer dot matrix (mis. Epson TM-U220) mulai
        //               mencetak sedikit di kiri tepi kertas, sehingga huruf kiri terpotong bila tanpa geseran.
        //               Bisa disetel langsung di halaman nota (tersimpan per perangkat).
        //   heavy     = true: pakai font monospace tebal (Lucida Console/Consolas/Courier New) supaya jelas di
        //               printer dot matrix; huruf tipis ber-serif/sans biasa sering 'hilang' per baris jarum.
        //   font      = ukuran huruf dalam pt
        $paperProfiles = [
            '58' => ['shift' => 0, 'printable' => '48mm', 'base' => '7.5pt', 'small' => '6.75pt', 'title' => '10.5pt', 'tiny' => '6pt', 'item' => '7pt', 'total' => '9.5pt', 'logo' => '28mm'],
            '68' => ['shift' => 7, 'heavy' => true, 'printable' => '58mm', 'base' => '8.5pt', 'small' => '8pt', 'title' => '11pt', 'tiny' => '7.5pt', 'item' => '8.5pt', 'total' => '10.5pt', 'logo' => '34mm'],
            '80' => ['shift' => 0, 'printable' => '72mm', 'base' => '9pt', 'small' => '7.5pt', 'title' => '13.5pt', 'tiny' => '6.75pt', 'item' => '8pt', 'total' => '11.5pt', 'logo' => '40mm'],
        ];
        $requestedPaper = request('paper');
        $paper = is_string($requestedPaper) && isset($paperProfiles[$requestedPaper]) ? $requestedPaper : $defaultPaper;
        $profile = $paperProfiles[$paper];
        $paperWidth = $paper . 'mm';
        $printable = $profile['printable'];
        $shiftMm = (float) $profile['shift'];
        $heavy = (bool) ($profile['heavy'] ?? false);
        $printableMm = (float) $printable;
        $baseFont = $profile['base'];
        $smallFont = $profile['small'];
        $titleFont = $profile['title'];
        $tinyFont = $profile['tiny'];
        $itemNameFont = $profile['item'];
        $totalFont = $profile['total'];
        $logoMaxWidth = $profile['logo'];
        // Hasil cetak ulang (dari tombol Re-Print / shortcut F4 di halaman Penjualan).
        $isReprint = request()->boolean('reprint');
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
        $feeAmount = (float) ($penjualan->payment_fee_amount ?? 0);
        $feePercent = (float) ($penjualan->payment_fee_percent ?? 0);
        $feeLabel = 'Biaya ' . ($penjualan->paymentMethod?->name ?? $penjualan->payment_method_name ?? '') . ' (' . rtrim(rtrim(number_format($feePercent, 2, ',', '.'), '0'), ',') . '%)';
        $changeAmount = (float) ($penjualan->change_amount ?? max(0, $paidAmount - $grandTotal - $feeAmount));

        // ---- Data untuk cetak teks langsung via QZ Tray (ESC/POS, memakai font bawaan printer) ----
        $fmtRaw = fn ($n) => number_format((float) $n, 0, ',', '.');
        $footerHtml = (string) ($penjualan->outlet?->footer ?? '');
        if ($footerHtml !== '') {
            $footerText = html_entity_decode(strip_tags(preg_replace('/<br\s*\/?>|<\/(p|div|li)>/i', "\n", $footerHtml)));
            $footerRaw = array_values(array_filter(array_map('trim', explode("\n", $footerText)), 'strlen'));
        } else {
            $footerRaw = ['Harga sudah termasuk PPN', 'Terima kasih atas kunjungan Anda'];
        }
        $metaRaw = [
            ['No', (string) $penjualan->code],
            ['Tgl', (string) optional($penjualan->created_at)->format('d/m/Y H:i')],
            ['Kasir', ($penjualan->kasir?->name ?? '-') . ' - ' . ($penjualan->cashierShift?->name ?? 'Kasir') . ($isReprint ? ' (Reprint)' : '')],
        ];
        if ($penjualan->customer?->name) {
            $metaRaw[] = ['Customer', (string) $penjualan->customer->name];
        }
        $summaryRaw = [['Jumlah item', (string) $itemCount, 0], ['Subtotal', $fmtRaw($subtotalGross), 0]];
        if ($discountTotal > 0) { $summaryRaw[] = ['Diskon', '-' . $fmtRaw($discountTotal), 0]; }
        if ($voucherTotal > 0) { $summaryRaw[] = ['Voucher', '-' . $fmtRaw($voucherTotal), 0]; }
        $summaryRaw[] = ['TOTAL', 'Rp ' . $fmtRaw($grandTotal), 1];
        if ($feeAmount > 0) {
            $summaryRaw[] = [trim($feeLabel), $fmtRaw($feeAmount), 0];
            $summaryRaw[] = ['TOTAL BAYAR', 'Rp ' . $fmtRaw($grandTotal + $feeAmount), 1];
        }
        $payRaw = [
            [(string) ($penjualan->paymentMethod?->name ?? $penjualan->payment_method_name ?? 'Tunai'), $fmtRaw($paidAmount), 0],
            ['Kembali', $fmtRaw($changeAmount), 1],
        ];
        if ($penjualan->payment_reference) {
            $payRaw[] = ['Ref.', (string) $penjualan->payment_reference, 0];
        }
        $rawReceipt = [
            'store' => (string) ($penjualan->outlet?->name ?? 'LUWES'),
            'header' => array_values(array_filter([
                $penjualan->outlet?->alamat,
                $penjualan->outlet?->desc,
                $penjualan->outlet?->npwp ? 'NPWP : ' . $penjualan->outlet->npwp : null,
            ])),
            'meta' => $metaRaw,
            'items' => $lines->map(fn ($l) => [
                'name' => (string) ($l['item']->product?->name ?? 'Produk'),
                'qty' => $l['qty'],
                'unit' => $fmtRaw($l['unit']),
                'gross' => $fmtRaw($l['gross']),
                'disc' => ($l['store'] + $l['promo']) > 0 ? $fmtRaw($l['store'] + $l['promo']) : null,
            ])->values()->all(),
            'summary' => $summaryRaw,
            'pay' => $payRaw,
            'saved' => ($discountTotal + $voucherTotal) > 0 ? $fmtRaw($discountTotal + $voucherTotal) : null,
            'footer' => $footerRaw,
            'printedAt' => now()->format('d/m/Y H:i:s'),
        ];
        $rawDefaultCols = $paper === '58' ? 32 : ($paper === '68' ? 36 : 40);
    @endphp
    <style>
        @page { size: {{ $paperWidth }} auto; margin: 0; }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        :root { --shift: {{ $shiftMm }}mm; --print-w: {{ $printable }}; --fs: 9pt; }
        body {
            width: var(--print-w);
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
        /* Profil dot matrix (Epson TM-U220): satu font monospace dengan versi TEBAL ASLI (Consolas / Courier New;
           Lucida Console tidak punya bold asli sehingga dihindari), satu ukuran untuk hampir semua teks,
           serta jarak vertikal kelipatan 1pt (= 1 baris jarum 1/72 inci) supaya tepi huruf tidak bergelombang. */
        body.heavy {
            font-family: Consolas, 'Courier New', 'DejaVu Sans Mono', monospace;
            font-weight: bold;
            font-size: var(--fs);
            letter-spacing: .05pt;
            line-height: 12pt;
            font-kerning: none;
            font-variant-ligatures: none;
            padding: 6pt 0 24pt;
        }
        body.heavy .small, body.heavy .tiny, body.heavy .meta, body.heavy .item-name, body.heavy .item-calc,
        body.heavy .item-disc, body.heavy .footer-msg, body.heavy .printed-at { font-size: var(--fs); line-height: 12pt; letter-spacing: .05pt; }
        body.heavy .store-name { font-size: calc(var(--fs) * 1.2); line-height: 14pt; letter-spacing: 0; }
        body.heavy .summary .grand { font-size: var(--fs); line-height: 14pt; padding: 1pt 0; }
        body.heavy .item { line-height: 12pt; margin-bottom: 3pt; }
        body.heavy .item-name { max-height: 24pt; }
        body.heavy .row, body.heavy .summary { padding-top: 0; padding-bottom: 0; row-gap: 0; }
        body.heavy .header-info { margin-top: 3pt; }
        body.heavy .meta { margin-top: 6pt; }
        body.heavy .block { margin-top: 6pt; }
        body.heavy .printed-at { margin-top: 6pt; }
        /* Garis pemisah dicetak sebagai karakter "-" (bukan border CSS): garis setebal ~1pt sering jatuh di antara
           baris jarum dan tidak tercetak, sedangkan karakter teks selalu tercetak. */
        body.heavy .rule-dash, body.heavy .rule-solid { border: 0; margin: 3pt 0; height: 12pt; line-height: 12pt; overflow: hidden; white-space: nowrap; letter-spacing: .05pt; }
        body.heavy .rule-solid { margin: 0 0 3pt; }
        body.heavy .rule-dash::before, body.heavy .rule-solid::before { content: '------------------------------------------------------------------------------------------------'; }
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
        .no-print .adjust { margin-top: 10px; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; text-align: left; }
        .no-print .adjust label { display: flex; align-items: center; justify-content: space-between; margin-top: 6px; }
        .no-print .adjust input { width: 80px; padding: 4px 6px; font: inherit; border: 1px solid #777; border-radius: 4px; text-align: right; }
        .no-print .adjust button { margin-top: 8px; padding: 6px; }
        .no-print .tips { margin-top: 10px; padding: 8px 10px; background: #fff8e1; border: 1px solid #f0d98a; border-radius: 4px; font-size: 12px; line-height: 1.5; text-align: left; }
        @media screen { body { padding-left: 0; padding-right: 0; } }
        @media print {
            body { margin: 0 0 0 var(--shift); }
            .no-print { display: none !important; }
        }
    </style>
</head>

<body class="{{ $heavy ? 'heavy' : '' }}">
    <div class="center">
        <div class="store-name">{{ $penjualan->outlet?->name ?? 'LUWES' }}</div>
        @if ($penjualan->outlet?->alamat)<div class="tiny header-info">{{ $penjualan->outlet->alamat }}</div>@endif
        @if ($penjualan->outlet?->desc)<div class="tiny">{{ $penjualan->outlet->desc }}</div>@endif
        @if ($penjualan->outlet?->npwp)<div class="tiny">NPWP : {{ $penjualan->outlet->npwp }}</div>@endif
    </div>

    <div class="meta">
        <div class="row"><span class="key">No</span><span class="val bold">{{ $penjualan->code }}</span></div>
        <div class="row"><span class="key">Tgl</span><span class="val">{{ optional($penjualan->created_at)->format('d/m/Y H:i') }}</span></div>
        <div class="row"><span class="key">Kasir</span><span class="val">{{ $penjualan->kasir?->name ?? '—' }} · {{ $penjualan->cashierShift?->name ?? 'Kasir' }}@if ($isReprint) (Reprint)@endif</span></div>
        @if ($penjualan->customer?->name)<div class="row"><span class="key">Customer</span><span class="val">{{ $penjualan->customer->name }}</span></div>@endif
    </div>

    <div class="rule-dash"></div>

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

    <div class="rule-solid"></div>

    <div class="summary">
        <span class="lbl small">Jumlah item</span><span class="num small">{{ $itemCount }}</span>
        <span class="lbl">Subtotal</span><span class="num">{{ number_format($subtotalGross, 0, ',', '.') }}</span>
        @if ($discountTotal > 0)<span class="lbl">Diskon</span><span class="num">-{{ number_format($discountTotal, 0, ',', '.') }}</span>@endif
        @if ($voucherTotal > 0)<span class="lbl">Voucher</span><span class="num">-{{ number_format($voucherTotal, 0, ',', '.') }}</span>@endif
        <span class="lbl grand">TOTAL</span><span class="num grand">Rp {{ number_format($grandTotal, 0, ',', '.') }}</span>
        @if ($feeAmount > 0)
            <span class="lbl small">{{ trim($feeLabel) }}</span><span class="num small">{{ number_format($feeAmount, 0, ',', '.') }}</span>
            <span class="lbl grand">TOTAL BAYAR</span><span class="num grand">Rp {{ number_format($grandTotal + $feeAmount, 0, ',', '.') }}</span>
        @endif
    </div>

    <div class="rule-dash"></div>

    <div class="summary">
        <span class="lbl">{{ $penjualan->paymentMethod?->name ?? $penjualan->payment_method_name ?? 'Tunai' }}</span><span class="num">{{ number_format($paidAmount, 0, ',', '.') }}</span>
        <span class="lbl bold">Kembali</span><span class="num bold">{{ number_format($changeAmount, 0, ',', '.') }}</span>
        @if ($penjualan->payment_reference)<span class="lbl small">Ref.</span><span class="num small wrap-ok">{{ $penjualan->payment_reference }}</span>@endif
    </div>
    @if (($discountTotal + $voucherTotal) > 0)
        <div class="center small block">Anda hemat Rp {{ number_format($discountTotal + $voucherTotal, 0, ',', '.') }}</div>
    @endif

    <div class="rule-dash"></div>

    @if ($penjualan->outlet?->footer)
        <div class="center footer-msg">{!! $penjualan->outlet->footer !!}</div>
    @else
        <div class="center footer-msg"><div>Harga sudah termasuk PPN</div><div class="bold">Terima kasih atas kunjungan Anda</div></div>
    @endif
    <div class="center printed-at">Dicetak {{ now()->format('d/m/Y H:i:s') }}</div>

    <div class="no-print">
        <a href="{{ route('outlet.show', $penjualan->outlet_id) }}">Kembali</a>
        <button type="button" class="primary" onclick="window.printReceipt(this); return false;">Print {{ $paper }}mm</button>
        <div class="papers">
            <a href="{{ route('penjualan.print', array_filter(['penjualan' => $penjualan->id, 'paper' => '58', 'reprint' => $isReprint ? 1 : null])) }}" class="{{ $paper === '58' ? 'active' : '' }}">Kertas 58mm</a>
            <a href="{{ route('penjualan.print', array_filter(['penjualan' => $penjualan->id, 'paper' => '68', 'reprint' => $isReprint ? 1 : null])) }}" class="{{ $paper === '68' ? 'active' : '' }}">Kertas 68mm</a>
            <a href="{{ route('penjualan.print', array_filter(['penjualan' => $penjualan->id, 'paper' => '80', 'reprint' => $isReprint ? 1 : null])) }}" class="{{ $paper === '80' ? 'active' : '' }}">Kertas 80mm</a>
        </div>
        <div class="adjust">
            <b>Penyesuaian posisi cetak ({{ $paper }}mm)</b>
            <label>Geser ke kanan (mm) <input type="number" id="adj-shift" step="0.5" min="0" max="20"></label>
            <label>Lebar isi nota (mm) <input type="number" id="adj-width" step="0.5" min="30" max="80"></label>
            @if ($heavy)
                <label>Ukuran huruf (pt) <input type="number" id="adj-fs" step="0.5" min="7" max="12"></label>
            @endif
            <button type="button" id="adj-save">Simpan penyesuaian</button>
            <button type="button" id="adj-reset">Kembalikan default</button>
            <div style="margin-top:6px;color:#555;">Huruf kiri terpotong &rarr; naikkan geser. Sisi kanan terpotong &rarr; kurangi geser atau lebar.</div>
        </div>
        <div class="adjust" id="raw-panel">
            <b>Cetak teks langsung (QZ Tray)</b>
            <label><span>Aktifkan</span> <input type="checkbox" id="raw-on" style="width:auto"></label>
            <label>Nama printer <input type="text" id="raw-printer" list="raw-printers" placeholder="(kosong = printer default)" style="width:170px;text-align:left"></label>
            <datalist id="raw-printers"></datalist>
            <label>Lebar printer (karakter/baris) <input type="number" id="raw-cols" step="1" min="20" max="64"></label>
            <label>Geser ke kanan (spasi) <input type="number" id="raw-indent" step="1" min="0" max="20"></label>
            <label>Tingkat tebal (1-5 kali cetak) <input type="number" id="raw-pass" step="1" min="1" max="5"></label>
            <label><span>Mode tebal printer (emphasized)</span> <input type="checkbox" id="raw-bold" style="width:auto"></label>
            <label><span>Potong kertas di akhir</span> <input type="checkbox" id="raw-cut" style="width:auto"></label>
            <button type="button" id="raw-find">Cari printer</button>
            <button type="button" id="raw-save">Simpan</button>
            <div id="raw-msg" style="margin-top:6px;color:#555;">Butuh aplikasi QZ Tray aktif di PC ini. Bila tidak aktif, otomatis memakai cetak browser.</div>
        </div>
        <div class="tips">
            <b>Supaya pas di printer thermal:</b><br>
            1. Di dialog print pilih printer POS-nya, ukuran kertas <b>{{ $paper }}mm</b> (atau Roll Paper {{ $paper }}mm).<br>
            2. Margins: <b>None</b>, Scale: <b>100%</b> (jangan "Fit to page").<br>
            3. Matikan <b>Headers and footers</b>.
        </div>
    </div>

    <script>
        // Cetak teks langsung ke printer (ESC/POS) lewat QZ Tray: printer memakai font bawaannya sendiri,
        // jadi huruf tegas & utuh, tidak lewat driver/bitmap browser. Kalau QZ Tray tidak aktif -> window.print().
        (function () {
            var RAW = @json($rawReceipt);
            var KEY = 'receipt-raw-{{ $paper }}';
            var def = { on: true, printer: '', cols: {{ $rawDefaultCols }}, cut: false, indent: 2, bold: false, pass: 3 };
            var CERT_URL = @json(route('qz.certificate'));
            var SIGN_URL = @json(route('qz.sign'));
            var CSRF = @json(csrf_token());
            var ESC = '\x1B';

            function load() {
                try {
                    var s = JSON.parse(window.localStorage.getItem(KEY) || 'null');
                    if (s) return { on: s.on !== false, printer: s.printer || '', cols: +s.cols || def.cols, cut: !!s.cut, bold: typeof s.bold === 'boolean' ? s.bold : def.bold, pass: isFinite(s.pass) && s.pass !== null && s.pass !== '' ? Math.max(1, Math.min(5, +s.pass)) : def.pass, indent: isFinite(s.indent) && s.indent !== null && s.indent !== '' ? +s.indent : def.indent };
                } catch (e) {}
                return { on: def.on, printer: def.printer, cols: def.cols, cut: def.cut, bold: def.bold, pass: def.pass, indent: def.indent };
            }
            function clean(s) {
                return String(s == null ? '' : s).replace(/[\u2013\u2014\u00b7]/g, '-').replace(/[^\x20-\x7E]/g, '?');
            }
            function rep(ch, n) { return new Array(Math.max(0, n) + 1).join(ch); }
            function lr(l, r, c) {
                l = clean(l); r = clean(r);
                if (l.length + r.length + 1 > c) l = l.substr(0, Math.max(0, c - r.length - 1));
                return l + rep(' ', c - l.length - r.length) + r;
            }
            function wrap(s, c) {
                var words = clean(s).split(/\s+/), out = [], cur = '';
                words.forEach(function (w) {
                    while (w.length > c) { if (cur) { out.push(cur); cur = ''; } out.push(w.substr(0, c)); w = w.substr(c); }
                    if (!w) return;
                    if (!cur) cur = w;
                    else if ((cur + ' ' + w).length <= c) cur += ' ' + w;
                    else { out.push(cur); cur = w; }
                });
                if (cur) out.push(cur);
                return out;
            }
            function build(o) {
                // cols = total karakter yang muat di printer; indent = spasi kosong di kiri (menggeser isi ke kanan).
                var ind = Math.max(0, Math.min(20, o.indent | 0)), c = Math.max(16, o.cols - ind);
                var pad = rep(' ', ind), out = [], dash = rep('-', c);
                // Overstrike: baris yang sama dicetak (pass) kali di posisi yang sama (jarak baris 0), lalu baris terakhir
                // mengembalikan jarak normal. Makin besar pass, makin tebal & gelap.
                var passes = Math.max(1, Math.min(5, o.pass | 0 || 1));
                function line(t) {
                    if (passes <= 1 || !t.trim()) { out.push(pad + t + '\n'); return; }
                    out.push(ESC + '3\x00');
                    for (var i = 0; i < passes - 1; i++) out.push(pad + t + '\n');
                    out.push(ESC + '2');
                    out.push(pad + t + '\n');
                }
                function ctr(t) { t = clean(t); return rep(' ', Math.floor((c - t.length) / 2)) + t; }
                function ctrWrap(t) { wrap(t, c).forEach(function (w) { line(ctr(w)); }); }
                // Tebal: emphasized (ESC E) + double-strike (ESC G) = tiap titik ditembak dua kali, hasil jauh lebih gelap.
                function strong() { out.push(ESC + 'E\x01' + ESC + 'G\x01'); }
                function normal() { out.push(ESC + 'E\x00' + ESC + 'G\x00'); if (o.bold) strong(); }
                out.push(ESC + '@');
                strong();
                ctrWrap(RAW.store);
                normal();
                RAW.header.forEach(ctrWrap);
                line('');
                RAW.meta.forEach(function (m) { line(lr(m[0], m[1], c)); });
                line(dash);
                RAW.items.forEach(function (it) {
                    wrap(it.name, c).slice(0, 2).forEach(line);
                    line(lr(it.qty + ' x ' + it.unit, it.gross, c));
                    if (it.disc) line(lr('  Disc.', '-' + it.disc, c));
                });
                line(dash);
                RAW.summary.forEach(function (r) {
                    if (r[2]) strong();
                    line(lr(r[0], r[1], c));
                    if (r[2]) normal();
                });
                line(dash);
                RAW.pay.forEach(function (r) {
                    if (r[2]) strong();
                    line(lr(r[0], r[1], c));
                    if (r[2]) normal();
                });
                if (RAW.saved) { line(''); ctrWrap('Anda hemat Rp ' + RAW.saved); }
                line('');
                RAW.footer.forEach(ctrWrap);
                line(ctr('Dicetak ' + RAW.printedAt));
                out.push(ESC + 'd\x05');
                if (o.cut) out.push(ESC + 'm');
                return out;
            }
            var secured = false;
            function setupSecurity() {
                if (secured) return;
                secured = true;
                // Sertifikat & tanda tangan dari server sendiri. Bila endpoint belum disiapkan, otomatis jalan tanpa tanda tangan.
                qz.security.setCertificatePromise(function (resolve) {
                    fetch(CERT_URL, { credentials: 'same-origin', cache: 'no-store' })
                        .then(function (r) { return r.ok ? r.text() : ''; })
                        .then(function (t) { if (t) resolve(t); else resolve(); }, function () { resolve(); });
                });
                qz.security.setSignatureAlgorithm('SHA512');
                qz.security.setSignaturePromise(function (toSign) {
                    return function (resolve) {
                        fetch(SIGN_URL, {
                            method: 'POST', credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': CSRF },
                            body: 'request=' + encodeURIComponent(toSign)
                        }).then(function (r) { return r.ok ? r.text() : ''; })
                          .then(function (t) { if (t) resolve(t); else resolve(); }, function () { resolve(); });
                    };
                });
            }
            function connect() {
                if (typeof qz === 'undefined') return Promise.reject(new Error('QZ Tray tidak termuat'));
                setupSecurity();
                return qz.websocket.isActive() ? Promise.resolve() : qz.websocket.connect({ retries: 0, delay: 0 });
            }
            function print() {
                var o = load();
                if (!o.on) return Promise.reject(new Error('dimatikan'));
                return connect()
                    .then(function () { return o.printer || qz.printers.getDefault(); })
                    .then(function (name) { return qz.print(qz.configs.create(name), build(o)); });
            }

            window.ReceiptRaw = { print: print, load: load };

            // Tombol Print manual: coba cetak teks langsung, kalau gagal pakai dialog print browser.
            window.printReceipt = function (btn) {
                var label = btn ? btn.textContent : '';
                print().then(function () {
                    if (btn) { btn.textContent = 'Terkirim ke printer ✓'; setTimeout(function () { btn.textContent = label; }, 2000); }
                }, function () { window.print(); });
            };

            // Tekan Enter di halaman nota = klik Print (diabaikan saat mengetik di kolom pengaturan).
            var enterLock = false;
            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' || e.repeat || e.ctrlKey || e.altKey || e.metaKey) return;
                var t = e.target, tag = t && t.tagName ? t.tagName.toLowerCase() : '';
                if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
                e.preventDefault();
                if (enterLock) return;
                enterLock = true;
                setTimeout(function () { enterLock = false; }, 1500);
                window.printReceipt(document.querySelector('.no-print .primary'));
            });

            // Panel pengaturan
            var elOn = document.getElementById('raw-on'), elPr = document.getElementById('raw-printer'),
                elCols = document.getElementById('raw-cols'), elIndent = document.getElementById('raw-indent'), elBold = document.getElementById('raw-bold'), elPass = document.getElementById('raw-pass'), elCut = document.getElementById('raw-cut'),
                elMsg = document.getElementById('raw-msg');
            if (!elOn) return;
            var cur = load();
            elOn.checked = cur.on; elPr.value = cur.printer; elCols.value = cur.cols; elIndent.value = cur.indent; elBold.checked = cur.bold; elPass.value = cur.pass; elCut.checked = cur.cut;
            document.getElementById('raw-save').addEventListener('click', function () {
                var v = { on: elOn.checked, printer: elPr.value.trim(), cols: Math.max(20, Math.min(64, parseInt(elCols.value, 10) || def.cols)), cut: elCut.checked, bold: elBold.checked, pass: Math.max(1, Math.min(5, parseInt(elPass.value, 10) || 1)), indent: Math.max(0, Math.min(20, parseInt(elIndent.value, 10) || 0)) };
                try { window.localStorage.setItem(KEY, JSON.stringify(v)); } catch (e) {}
                elMsg.textContent = 'Tersimpan ✓';
            });
            document.getElementById('raw-find').addEventListener('click', function () {
                elMsg.textContent = 'Mencari printer...';
                connect().then(function () { return qz.printers.find(); }).then(function (list) {
                    var dl = document.getElementById('raw-printers');
                    dl.innerHTML = '';
                    [].concat(list).forEach(function (n) { var op = document.createElement('option'); op.value = n; dl.appendChild(op); });
                    elMsg.textContent = 'Ditemukan ' + [].concat(list).length + ' printer. Klik kolom Nama printer untuk memilih.';
                }, function () { elMsg.textContent = 'QZ Tray tidak terdeteksi. Pastikan aplikasinya berjalan.'; });
            });
        })();
    </script>

    <script>
        // Penyesuaian posisi cetak per ukuran kertas, tersimpan di perangkat ini.
        (function () {
            var key = 'receipt-adjust-{{ $paper }}';
            var def = { shift: {{ $shiftMm }}, width: {{ $printableMm }}, fs: {{ $heavy ? 9 : 'null' }} };
            var root = document.documentElement;
            var elShift = document.getElementById('adj-shift');
            var elWidth = document.getElementById('adj-width');
            var elFs = document.getElementById('adj-fs');
            function apply(v) {
                root.style.setProperty('--shift', v.shift + 'mm');
                root.style.setProperty('--print-w', v.width + 'mm');
                if (v.fs) root.style.setProperty('--fs', v.fs + 'pt');
                if (elFs && v.fs) elFs.value = v.fs;
                if (elShift) elShift.value = v.shift;
                if (elWidth) elWidth.value = v.width;
            }
            var cur = def;
            try {
                var saved = JSON.parse(window.localStorage.getItem(key) || 'null');
                if (saved && isFinite(saved.shift) && isFinite(saved.width)) cur = { shift: +saved.shift, width: +saved.width, fs: (isFinite(saved.fs) && saved.fs) ? +saved.fs : def.fs };
            } catch (e) {}
            apply(cur);
            function read() {
                var sh = parseFloat(elShift.value), w = parseFloat(elWidth.value);
                var f = elFs ? parseFloat(elFs.value) : NaN;
                return { shift: isFinite(sh) ? Math.max(0, Math.min(20, sh)) : def.shift, width: isFinite(w) ? Math.max(30, Math.min(80, w)) : def.width, fs: isFinite(f) ? Math.max(7, Math.min(12, f)) : def.fs };
            }
            if (elShift && elWidth) {
                ['input', 'change'].forEach(function (ev) {
                    elShift.addEventListener(ev, function () { apply(read()); });
                    elWidth.addEventListener(ev, function () { apply(read()); });
                    if (elFs) elFs.addEventListener(ev, function () { apply(read()); });
                });
                document.getElementById('adj-save').addEventListener('click', function () {
                    var v = read();
                    try { window.localStorage.setItem(key, JSON.stringify(v)); } catch (e) {}
                    apply(v);
                    this.textContent = 'Tersimpan ✓';
                });
                document.getElementById('adj-reset').addEventListener('click', function () {
                    try { window.localStorage.removeItem(key); } catch (e) {}
                    apply(def);
                });
            }
        })();
    </script>

    @if (request('auto'))
        <script>
            (function () {
                var printed = false;
                function doPrint() {
                    if (printed) return;
                    printed = true;
                    setTimeout(function () {
                        // Cetak teks langsung (QZ Tray); bila gagal / tidak aktif, pakai dialog print browser.
                        var back = @json(route('outlet.show', $penjualan->outlet_id));
                        window.ReceiptRaw.print().then(function () { window.location.href = back; }, function () { window.print(); });
                    }, 150);
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