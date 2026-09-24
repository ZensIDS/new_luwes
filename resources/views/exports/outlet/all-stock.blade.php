<table>
    <thead>
        <tr><th colspan="11">LAPORAN ALL STOCK OUTLET</th></tr>
        <tr>
            <th>Outlet</th><th>Barcode</th><th>Produk</th><th>Kategori</th><th>Qty</th><th>Satuan</th>
            <th>HPP</th><th>Pajak</th><th>HPP + Pajak</th><th>Persediaan Sebelum Pajak</th><th>Persediaan Setelah Pajak</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['outlet'] }}</td><td>{{ $row['barcode'] }}</td><td>{{ $row['product'] }}</td><td>{{ $row['category'] }}</td><td>{{ $row['qty'] }}</td><td>{{ $row['satuan'] }}</td>
                <td>{{ $row['hpp'] }}</td><td>{{ $row['tax'] }}</td><td>{{ $row['hpp_after_tax'] }}</td><td>{{ $row['inventory_before_tax'] }}</td><td>{{ $row['inventory_after_tax'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th colspan="9">Total Persediaan</th><th>{{ $summary['total_persediaan_sebelum_pajak'] }}</th><th>{{ $summary['total_persediaan_setelah_pajak'] }}</th></tr>
    </tfoot>
</table>
