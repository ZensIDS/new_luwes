<!doctype html><html><head><meta charset="utf-8"><style>
body{font-size:7px}table{font-size:6.5px}th{padding:3px 2px}td{padding:2px 2px}.tc{text-align:center}.danger{color:#b00;font-weight:bold}
</style></head><body>
@include('exports.pdf._header')
<div class="report-title">LAPORAN MINIMAL STOCK &amp; ALOKASI PO OUTLET</div>
<div class="report-periode">Per tanggal {{ $asOf }} | Periode penjualan {{ $windowStart }} s/d {{ $windowEnd }}</div>
<table><thead><tr>
<th>No</th><th>Outlet</th><th>Barcode</th><th>Produk</th><th>Avg Jual/Bln</th><th>PO 3 Bln</th><th>PO/Bln</th><th>Faktor</th><th>Min Manual</th><th>Min Hitung</th><th>Stok</th><th>Saran PO</th><th>Status</th><th>Perhitungan</th>
</tr></thead><tbody>
@forelse($rows as $i => $row)<tr>
<td class="tc">{{ $i + 1 }}</td><td>{{ $row['outlet'] }}</td><td>{{ $row['barcode'] }}</td><td>{{ $row['product'] }}</td><td class="tc">{{ number_format($row['average_sold'], 2, ',', '.') }}</td><td class="tc">{{ $row['po_count'] }}</td><td class="tc">{{ number_format($row['po_per_month'], 2, ',', '.') }}</td><td class="tc">{{ $row['factor'] ?? '-' }}</td><td class="tc">{{ $row['manual_min_stock'] }}</td><td class="tc">{{ $row['min_stock'] }}</td><td class="tc">{{ $row['stock_qty'] }}</td><td class="tc">{{ $row['suggested_po_qty'] }}</td><td class="tc {{ $row['status'] === 'out_of_stock' ? 'danger' : '' }}">{{ $row['status'] }}</td><td>{{ $row['calculation_status'] }}</td>
</tr>@empty<tr><td colspan="14" class="tc">Tidak ada data</td></tr>@endforelse
</tbody></table>
<p>Rumus: rata-rata penjualan 3 bulan (A) x faktor PO. Faktor: 1 PO/bulan = 2,5; 2 = 1,875; 3 = 1,25; 4 atau lebih = 0,625. Saran PO = max(0, min stock - stok saat ini).</p>
</body></html>
