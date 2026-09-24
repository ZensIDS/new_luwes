<!doctype html><html><head><meta charset="utf-8"><style>
body{font-size:7.5px}table{font-size:7px}th{padding:3px 2px}td{padding:2px 3px}.tr{text-align:right}.tc{text-align:center}
</style></head><body>
@include('exports.pdf._header')
<div class="report-title">LAPORAN RETUR PENJUALAN OUTLET</div><div class="report-periode">Periode: {{ $mulai }} s/d {{ $selesai }}</div>
<table><thead><tr><th>Tanggal</th><th>Kode Retur</th><th>Invoice</th><th>Outlet</th><th>Petugas</th><th>Barcode Retur</th><th>Barang Retur</th><th>Qty</th><th>Barcode Pengganti</th><th>Barang Pengganti</th><th>Qty</th><th>Selisih</th><th>Keterangan</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d/m/Y H:i') }}</td><td>{{ $row['kode_retur'] }}</td><td>{{ $row['invoice'] }}</td><td>{{ $row['outlet'] }}</td><td>{{ $row['petugas'] }}</td><td>{{ $row['barcode_retur'] }}</td><td>{{ $row['barang_retur'] }}</td><td class="tc">{{ $row['qty_retur'] }}</td><td>{{ $row['barcode_pengganti'] }}</td><td>{{ $row['barang_pengganti'] }}</td><td class="tc">{{ $row['qty_pengganti'] }}</td><td class="tr">{{ number_format($row['selisih'], 0, ',', '.') }}</td><td>{{ $row['keterangan'] }}</td></tr>@empty<tr><td colspan="13" class="tc">Tidak ada data</td></tr>@endforelse
</tbody></table><h4>Rekap</h4><p>Jumlah transaksi retur: {{ $summary['jumlah_retur'] }} | Qty barang retur: {{ $summary['total_barang_retur'] }} | Qty barang pengganti: {{ $summary['total_barang_pengganti'] }} | Total selisih: Rp {{ number_format($summary['total_selisih'], 0, ',', '.') }}</p>
</body></html>
