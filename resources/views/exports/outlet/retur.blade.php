<table>
    <thead>
        <tr><th colspan="13">LAPORAN RETUR PENJUALAN OUTLET</th></tr>
        <tr>
            <th>Tanggal</th><th>Kode Retur</th><th>Invoice</th><th>Outlet</th><th>Petugas</th>
            <th>Barcode Barang Retur</th><th>Barang Retur</th><th>Qty Retur</th>
            <th>Barcode Pengganti</th><th>Barang Pengganti</th><th>Qty Pengganti</th><th>Selisih</th><th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d/m/Y H:i') }}</td><td>{{ $row['kode_retur'] }}</td><td>{{ $row['invoice'] }}</td><td>{{ $row['outlet'] }}</td><td>{{ $row['petugas'] }}</td>
                <td>{{ $row['barcode_retur'] }}</td><td>{{ $row['barang_retur'] }}</td><td>{{ $row['qty_retur'] }}</td>
                <td>{{ $row['barcode_pengganti'] }}</td><td>{{ $row['barang_pengganti'] }}</td><td>{{ $row['qty_pengganti'] }}</td><td>{{ $row['selisih'] }}</td><td>{{ $row['keterangan'] }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th colspan="7">{{ $summary['jumlah_retur'] }} transaksi retur</th><th>{{ $summary['total_barang_retur'] }}</th><th></th><th></th><th>{{ $summary['total_barang_pengganti'] }}</th><th>{{ $summary['total_selisih'] }}</th></tr>
    </tfoot>
</table>
