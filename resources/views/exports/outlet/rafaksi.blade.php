<table>
    <thead>
        <tr><th colspan="8">LAPORAN RAFAKSI OUTLET</th></tr>
        <tr>
            <th>Barcode</th><th>Nama Product</th><th>Qty Product Terjual</th><th>Harga Akhir/Unit</th>
            <th>Diskon Rafaksi Product/Unit</th><th>Total Diskon</th><th>Total Penjualan</th><th>Keterangan Rafaksi</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['barcode'] }}</td><td>{{ $row['product'] }}</td><td>{{ $row['qty'] }}</td>
                <td>{{ $row['unit_price'] }}</td><td>{{ $row['discount_per_unit'] }}</td>
                <td>{{ $row['discount_total'] }}</td><td>{{ $row['subtotal'] }}</td><td>{{ $row['keterangan'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th colspan="2">Rekap</th><th>{{ $summary['qty'] }}</th><th colspan="2"></th><th>{{ $summary['total_diskon'] }}</th><th>{{ $summary['total_penjualan'] }}</th><th></th></tr>
    </tfoot>
</table>
