<table>
    <thead>
        <tr><th colspan="13">LAPORAN PENJUALAN OUTLET</th></tr>
        <tr>
            <th>Tanggal</th><th>Invoice</th><th>Outlet</th><th>Kasir</th><th>Jenis</th>
            <th>Barcode</th><th>Produk</th><th>Qty</th><th>Harga Satuan</th>
            <th>Diskon/Satuan</th><th>Total Diskon</th><th>Subtotal</th><th>Payment Method</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d/m/Y H:i') }}</td>
                <td>{{ $row['invoice'] }}</td><td>{{ $row['outlet'] }}</td><td>{{ $row['kasir'] }}</td><td>{{ $row['type'] }}</td>
                <td>{{ $row['barcode'] }}</td><td>{{ $row['product'] }}</td><td>{{ $row['qty'] }}</td>
                <td>{{ $row['unit_price'] }}</td><td>{{ $row['discount_per_unit'] }}</td><td>{{ $row['discount_total'] }}</td>
                <td>{{ $row['subtotal'] }}</td><td>{{ $row['payment_method'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<table>
    <thead><tr><th colspan="2">REKAP PENJUALAN</th></tr></thead>
    <tbody>
        <tr><td>Total Penjualan</td><td>{{ $summary['total_penjualan'] }}</td></tr>
        @foreach ($paymentMethods as $method => $amount)
            <tr><td>{{ $method }}</td><td>{{ $amount }}</td></tr>
        @endforeach
        <tr><td>Bon</td><td>{{ $summary['bon'] }}</td></tr>
        <tr><td>Setoran Akhir</td><td>{{ $summary['setoran_akhir'] }}</td></tr>
        <tr><td>Jumlah Transaksi</td><td>{{ $summary['jumlah_transaksi'] }}</td></tr>
    </tbody>
</table>
