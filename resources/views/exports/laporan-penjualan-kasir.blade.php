<table>
    <thead>
        <tr>
            <th>Code</th>
            <th>Customer</th>
            <th>Kassa (akun)</th>
            <th>Disc Toko</th>
            <th>Promo Otomatis</th>
            <th>Voucher</th>
            <th>Grand Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($penjualans as $penjualan)
            <tr>
                <td>{{ $penjualan->code }}</td>
                <td>{{ $penjualan->customer->name }}</td>
                <td>{{ $penjualan->kasir->name ?? ''}}</td>
                <td>@currency($penjualan->discount_total ?? $penjualan->discount)</td>
                <td>@currency($penjualan->promotion_total ?? 0)</td>
                <td>@currency($penjualan->voucher_total ?? 0)</td>
                <td>@currency($penjualan->grand_total ?? $penjualan->total)</td>
            </tr>
        @endforeach
    </tbody>
</table>
