<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $penjualan->code }}</title>
    @php
        $paperWidth = request('paper') === '58' ? '58mm' : '80mm';
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
        body {
            width: {{ $paperWidth }};
            margin: 0 auto;
            padding: 4mm 3mm;
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #000;
            line-height: 1.4;
        }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .store-logo { max-width: 22mm; max-height: 16mm; margin-bottom: 2mm; }
        .store-name { font-size: 16px; font-weight: bold; letter-spacing: 1px; }
        .small { font-size: 10px; }
        hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; }
        .meta td { padding: 1px 0; }
        .meta td:last-child { text-align: right; padding-left: 4px; }
        .receipt-barcode { margin: 3mm 0 1mm; }
        .receipt-barcode svg { display: block; width: 100%; max-width: 62mm; height: 11mm; margin: 0 auto; }
        .receipt-code { font-size: 10px; letter-spacing: .5px; }
        .item-row td { padding: 2px 0; }
        .item-name { word-wrap: break-word; overflow-wrap: break-word; }
        .qty-price { font-size: 10px; color: #333; padding-left: 4px; }
        .price-col { text-align: right; white-space: nowrap; }
        .strike { color: #666; }
        .disc-row { font-size: 10px; padding-left: 4px; }
        .disc-value { text-align: right; }
        .totals td { padding: 2px 0; }
        .totals .label { text-align: left; }
        .totals .value { text-align: right; }
        .grand-total { font-size: 13px; font-weight: bold; }
        .footer-msg { margin-top: 8px; }
        .no-print { margin-top: 12px; }
        .no-print a, .no-print button {
            display: block;
            width: 100%;
            margin-top: 5px;
            padding: 8px;
            border: 1px solid #777;
            background: #fff;
            color: #000;
            font: inherit;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }
        @media print {
            body { width: {{ $paperWidth }}; padding: 4mm 3mm; }
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
        @if ($penjualan->outlet?->alamat)
            <div class="small">{{ $penjualan->outlet->alamat }}</div>
        @endif
        @if ($penjualan->outlet?->desc)
            <div class="small">{{ $penjualan->outlet->desc }}</div>
        @endif
    </div>

    <hr>

    <table class="meta small">
        <tr>
            <td>No. Struk</td>
            <td>{{ $penjualan->code }}</td>
        </tr>
        <tr>
            <td>Tanggal</td>
            <td>{{ optional($penjualan->created_at)->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td>Kasir</td>
            <td>{{ $penjualan->kasir?->name ?? 'Kasir' }}</td>
        </tr>
        @if ($penjualan->customer?->name)
            <tr>
                <td>Customer</td>
                <td>{{ $penjualan->customer->name }}</td>
            </tr>
        @endif
    </table>

    <div class="center receipt-barcode">
        {!! DNS1D::getBarcodeSVG((string) $penjualan->code, 'C128', 1, 28, 'black', false, false) !!}
        <div class="receipt-code">{{ $penjualan->code }}</div>
    </div>

    <hr>

    <table>
        @foreach ($items as $item)
            @php
                $lineSubtotal = (float) ($item->subtotal ?? ((float) $item->qty * (float) $item->price));
                $unitPrice = (float) $item->price;
                $referencePrice = (float) ($item->harga_aktif ?? $item->base_price ?? $unitPrice);
                $promotionDiscount = (float) ($item->promotion_discount ?? 0);
            @endphp
            <tr class="item-row">
                <td colspan="2" class="item-name">{{ $item->product?->name ?? 'Produk' }}</td>
            </tr>
            <tr class="item-row qty-price">
                <td>
                    {{ $item->qty }} x
                    @if ($referencePrice > $unitPrice)
                        <span class="strike"><del>@currency($referencePrice)</del></span>
                    @endif
                    @currency($unitPrice)
                </td>
                <td class="price-col">@currency($lineSubtotal)</td>
            </tr>
            @if ($promotionDiscount > 0)
                <tr class="disc-row">
                    <td>Diskon Item ({{ $item->qty }}x)</td>
                    <td class="disc-value">-@currency($promotionDiscount)</td>
                </tr>
            @endif
        @endforeach
    </table>

    <hr>

    <table class="totals">
        <tr>
            <td class="label">Jumlah Item</td>
            <td class="value">{{ $itemCount }}</td>
        </tr>
        <tr>
            <td class="label">Subtotal</td>
            <td class="value">@currency($subtotalBeforePromotion)</td>
        </tr>
        @if ($promotionTotal > 0)
            <tr>
                <td class="label">Diskon Promo</td>
                <td class="value">-@currency($promotionTotal)</td>
            </tr>
        @endif
        @if ($voucherTotal > 0)
            <tr>
                <td class="label">Voucher</td>
                <td class="value">-@currency($voucherTotal)</td>
            </tr>
        @endif
        <tr class="grand-total">
            <td class="label">TOTAL</td>
            <td class="value">@currency($grandTotal)</td>
        </tr>
    </table>

    <hr>

    <table class="totals">
        <tr>
            <td class="label">{{ $penjualan->paymentMethod?->name ?? $penjualan->payment_method_name ?? 'Tunai' }}</td>
            <td class="value">@currency($paidAmount)</td>
        </tr>
        <tr>
            <td class="label">Kembali</td>
            <td class="value">@currency($changeAmount)</td>
        </tr>
        @if ($penjualan->payment_reference)
            <tr class="small">
                <td class="label">Ref.</td>
                <td class="value">{{ $penjualan->payment_reference }}</td>
            </tr>
        @endif
    </table>

    @if ($penjualan->outlet?->footer)
        <div class="center footer-msg">{!! $penjualan->outlet->footer !!}</div>
    @else
        <div class="center footer-msg">
            <div>Terima kasih telah berbelanja</div>
            <div style="margin-top:6px;">*** SELAMAT BELANJA KEMBALI ***</div>
        </div>
    @endif

    <div class="no-print">
        <a href="{{ route('outlet.show', $penjualan->outlet_id) }}">Kembali</a>
        <button type="button" onclick="window.print(); return false;">Print {{ $paperWidth }}</button>
        @if ($paperWidth === '80mm')
            <a href="{{ route('penjualan.print', [$penjualan, 'paper' => '58']) }}">Print 58mm</a>
        @else
            <a href="{{ route('penjualan.print', [$penjualan, 'paper' => '80']) }}">Print 80mm</a>
        @endif
    </div>

    @if (request('auto'))
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 250);
            });
            window.addEventListener('afterprint', function () {
                window.location.href = @json(route('outlet.show', $penjualan->outlet_id));
            });
        </script>
    @endif
</body>

</html>
