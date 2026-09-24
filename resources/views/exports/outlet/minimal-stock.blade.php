<table>
    <thead>
        <tr>
            <th colspan="17">LAPORAN MINIMAL STOCK OUTLET</th>
        </tr>
        <tr>
            <th>Outlet</th><th>Barcode</th><th>Produk</th><th>Kategori</th>
            <th>Periode Penjualan</th><th>Penjualan Rata-rata/Bulan</th>
            <th>Jumlah PO (3 Bulan)</th><th>PO/Bulan</th><th>Faktor</th>
            <th>Min Stock Manual</th><th>Min Stock Hasil Hitung</th><th>Stock Saat Ini</th>
            <th>Saran Qty PO</th><th>Status Stock</th><th>Status Perhitungan</th>
            <th>Penjualan Pertama</th><th>Per Tanggal</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['outlet'] }}</td>
                <td>{{ $row['barcode'] }}</td>
                <td>{{ $row['product'] }}</td>
                <td>{{ $row['category'] }}</td>
                <td>{{ $row['window_start'] }} s/d {{ $row['window_end'] }}</td>
                <td>{{ $row['average_sold'] }}</td>
                <td>{{ $row['po_count'] }}</td>
                <td>{{ number_format($row['po_per_month'], 2, '.', '') }}</td>
                <td>{{ $row['factor'] ?? '-' }}</td>
                <td>{{ $row['manual_min_stock'] }}</td>
                <td>{{ $row['min_stock'] }}</td>
                <td>{{ $row['stock_qty'] }}</td>
                <td>{{ $row['suggested_po_qty'] }}</td>
                <td>{{ $row['status'] }}</td>
                <td>{{ $row['calculation_status'] }}</td>
                <td>{{ $row['first_sale'] ? \Carbon\Carbon::parse($row['first_sale'])->format('d/m/Y') : '-' }}</td>
                <td>{{ $asOf }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr><th colspan="12">Total produk: {{ $summary['total_produk'] }} | Out of stock: {{ $summary['out_of_stock'] }}</th><th>{{ $summary['total_suggested_po'] }}</th></tr>
    </tfoot>
</table>
