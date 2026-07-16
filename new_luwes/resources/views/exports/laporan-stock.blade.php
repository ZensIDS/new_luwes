<table>
    <thead>
        <tr>
            <th>Pembelian</th>
            <th>Product</th>
            <th>Subtotal</th>
            <th>Harga Beli</th>
            <th>Qty</th>
            {{-- <th>Expired At</th> --}}
        </tr>
    </thead>
    <tbody>
        @foreach ($stocks as $stock)
            <tr>
                <td>{{ $stock->pembelian->code }}</td>
                <td>{{ $stock->product->name }}</td>
                <td>@currency($stock->subtotal)</td>
                <td>@currency($stock->harga_beli)</td>
                <td>{{ $stock->qty }}</td>
                {{-- <td>{{ $stock->expired_at->format('h:i a / d-M-Y') }}</td> --}}
            </tr>
        @endforeach
    </tbody>
</table>
