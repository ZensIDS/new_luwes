<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label Harga</title>
    @php
        $labelName = strtoupper($selectedOutlet?->name ?? 'LUWES');
        $labelStrip = str_repeat($labelName . ' • ', 9);
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

        .label-outer { width: 80mm; padding: 2.5mm; page-break-inside: avoid; }
        .frame { display: grid; grid-template-columns: 3mm 1fr 3mm; grid-template-rows: 3mm 1fr 3mm; border: 1px solid #000; }
        .strip { background: #fff; color: #000; overflow: hidden; white-space: nowrap; font-size: 5.5px; font-weight: 800; letter-spacing: .5px; }
        .strip-top, .strip-bottom { grid-column: 1 / 4; display: flex; align-items: center; border-bottom: 1px solid #000; }
        .strip-bottom { border-bottom: none; border-top: 1px solid #000; }
        .strip-left, .strip-right { display: flex; align-items: center; justify-content: center; border-right: 1px solid #000; }
        .strip-right { border-right: none; border-left: 1px solid #000; }
        .strip-left span, .strip-right span { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; }
        .label-content { padding: 3mm 4mm 3.5mm; text-align: center; }
        .product-name { font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: .2px; line-height: 1.15; max-height: 2.3em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; margin-bottom: 2mm; }
        .old-price-wrap { display: inline-flex; align-items: center; gap: 5px; margin-bottom: 1.5mm; border: 1.5px solid #000; padding: .8mm 3mm; }
        .discount-badge { font-size: 9px; font-weight: 900; letter-spacing: 1px; }
        .old-price { font-size: 14px; font-weight: 800; text-decoration: line-through; text-decoration-thickness: 1.8px; }
        .net-price { font-size: 26px; font-weight: 900; line-height: 1.05; letter-spacing: .2px; margin-bottom: 2mm; }
        .net-price.no-discount { margin-top: 6mm; }
        .net-price .rp { font-size: 13px; font-weight: 700; vertical-align: 3px; margin-right: 1px; }
        hr.separator { border: none; border-top: 1px dashed #000; margin: 0 0 2mm; }
        .product-code { font-family: 'Courier New', monospace; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; }
        .cut-line { width: 80mm; text-align: center; font-size: 8px; color: #555; padding: 1.5mm 0; letter-spacing: 1px; }

        @page { size: 80mm auto; margin: 0; }
        @media print {
            body { width: 80mm; margin: 0 auto; padding: 0; background: #fff; }
            .toolbar { display: none !important; }
        }
    </style>
</head>

<body>
    @if (! $printing)
        <div class="toolbar">
            <h2>Cetak label harga produk</h2>
            <p class="muted">Harga coret dihitung dari HPP setelah pajak + margin. Label thermal memakai format 80mm dan menampilkan barcode sebagai tulisan saja.</p>
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
                                        <small class="muted">HPP: @currency($product->print_price_hpp_after_tax) + Margin: @currency($product->print_price_margin)</small>
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
        @foreach ($printItems as $product)
            @for ($index = 0; $index < $product->print_qty; $index++)
                <div class="label-outer">
                    <div class="frame">
                        <div class="strip strip-top"><span>{{ $labelStrip }}</span></div>
                        <div class="strip strip-left"><span>{{ $labelName }} • </span></div>
                        <div class="label-content">
                            <div class="product-name" title="{{ $product->name }}">{{ $product->name }}</div>
                            @if ($product->print_has_discount)
                                <div class="old-price-wrap">
                                    <span class="discount-badge">DISKON</span>
                                    <span class="old-price">Rp {{ number_format($product->print_price_strike, 0, ',', '.') }}</span>
                                </div>
                            @endif
                            <div class="net-price {{ $product->print_has_discount ? '' : 'no-discount' }}"><span class="rp">Rp</span>{{ number_format($product->print_price_net, 0, ',', '.') }}</div>
                            <hr class="separator">
                            <div class="product-code">{{ $product->code }}</div>
                        </div>
                        <div class="strip strip-right"><span>{{ $labelName }} • </span></div>
                        <div class="strip strip-bottom"><span>{{ $labelStrip }}</span></div>
                    </div>
                </div>
                <div class="cut-line">- - - - - - - - - - - - - - - - - - - - - - - -</div>
            @endfor
        @endforeach
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>

</html>
