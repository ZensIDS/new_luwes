<table>
    <thead>
        <tr><th colspan="12">LAPORAN RAFAKSI OUTLET</th></tr>
        <tr>
            <th>Tanggal</th><th>Invoice</th><th>Outlet</th><th>Kasir</th><th>Barcode</th><th>Produk</th>
            <th>Qty Terjual</th><th>Harga Satuan</th><th>Diskon/Satuan</th><th>Total Diskon</th>
            <th>Subtotal</th><th>Keterangan Rafaksi</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d/m/Y H:i') }}</td><td>{{ $row['invoice'] }}</td><td>{{ $row['outlet'] }}</td><td>{{ $row['kasir'] }}</td>
                <td>{{ $row['barcode'] }}</td><td>{{ $row['product'] }}</td><td>{{ $row['qty'] }}</td><td>{{ $row['unit_price'] }}</td>
                <td>{{ $row['discount_per_unit'] }}</td><td>{{ $row['discount_total'] }}</td><td>{{ $row['subtotal'] }}</td><td>{{ $row['keterangan'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th colspan="6">Rekap</th><th>{{ $summary['qty'] }}</th><th></th><th></th><th>{{ $summary['total_diskon'] }}</th><th>{{ $summary['total_penjualan'] }}</th></tr>
    </tfoot>
</table>
