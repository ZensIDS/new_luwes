<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Label Produk</title>
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
        .label-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 4mm; width: 100%; max-width: 194mm; margin: 0 auto; }
        .product-label { min-height: 38mm; padding: 3.5mm; border: 1px dashed #777; background: #fff; text-align: center; overflow: hidden; break-inside: avoid; }
        .product-name { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; height: 9mm; font-size: 12px; font-weight: 700; line-height: 4.5mm; }
        .old-price { margin-top: 1mm; color: #777; font-size: 10px; }
        .old-price del { text-decoration-thickness: 1px; }
        .net-price { margin-top: .5mm; font-size: 17px; font-weight: 700; }
        .barcode { width: 100%; height: 13mm; margin-top: 2mm; overflow: hidden; display: flex; justify-content: center; align-items: flex-start; }
        .barcode svg { display: block; width: auto; max-width: 100%; height: 13mm; shape-rendering: crispEdges; }
        .code { width: 100%; margin-top: 2mm; font-size: 10px; letter-spacing: 1px; text-align: center; }
        @page { size: A4 portrait; margin: 8mm; }
        @media print {
            body { padding: 0; background: #fff; }
            .toolbar { display: none !important; }
            .label-grid { max-width: none; }
        }
        @media (max-width: 700px) {
            .label-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>

<body>
    @if (! $printing)
        <div class="toolbar">
            <h2>Cetak label harga produk</h2>
            <p class="muted">
                Harga coret = HPP setelah pajak + margin. Harga jual = harga netto POS.
                Barcode dicetak sebagai kode saja.
            </p>
            <form method="GET" action="{{ route('cashier.print.products') }}" target="_blank">
                @if ($outlets->count() > 1)
                    <label>Outlet
                        <select name="outlet_id">
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}" {{ (int) $outletId === (int) $outlet->id ? 'selected' : '' }}>
                                    {{ $outlet->name }}
                                </option>
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
                    @if ($outletId)
                        <input type="hidden" name="outlet_id" value="{{ $outletId }}">
                    @endif
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
                                    <td>
                                        <input class="product-check" type="checkbox" name="product_ids[]"
                                            value="{{ $product->id }}">
                                    </td>
                                    <td>{{ $product->code }}</td>
                                    <td>{{ $product->name }}</td>
                                    <td><del>@currency($product->print_price_strike ?? 0)</del></td>
                                    <td>@currency($product->print_price_net ?? 0)</td>
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
        <div class="label-grid">
            @foreach ($printItems as $product)
                @for ($index = 0; $index < $product->print_qty; $index++)
                    <div class="product-label">
                        <div class="product-name" title="{{ $product->name }}">{{ $product->name }}</div>
                        <div class="old-price"><del>@currency($product->print_price_strike ?? 0)</del></div>
                        <div class="net-price">@currency($product->print_price_net ?? 0)</div>
                        <div class="barcode">{!! DNS1D::getBarcodeSVG((string) $product->code, 'C128', 1, 34, 'black', false, true) !!}</div>
                        <div class="code">{{ $product->code }}</div>
                    </div>
                @endfor
            @endforeach
        </div>
        <script>
            window.addEventListener('load', function () { window.print(); });
        </script>
    @endif
</body>

</html>
