<!doctype html><html><head><meta charset="utf-8"><style>
body{font-size:7.5px}table{font-size:7px}th{padding:3px 2px}td{padding:2px 3px}.tr{text-align:right}.tc{text-align:center}
</style></head><body>
@include('exports.pdf._header')
<div class="report-title">LAPORAN ALL STOCK OUTLET</div><div class="report-periode">Stock berdasarkan barcode produk, bukan SKU | Per tanggal {{ now()->format('d/m/Y') }}</div>
<table><thead><tr><th>No</th><th>Outlet</th><th>Barcode</th><th>Produk</th><th>Kategori</th><th>Qty</th><th>Satuan</th><th>HPP</th><th>Pajak</th><th>HPP + Pajak</th><th>Persediaan Sebelum Pajak</th><th>Persediaan Setelah Pajak</th></tr></thead><tbody>
@forelse($rows as $i => $row)<tr><td class="tc">{{ $i + 1 }}</td><td>{{ $row['outlet'] }}</td><td>{{ $row['barcode'] }}</td><td>{{ $row['product'] }}</td><td>{{ $row['category'] }}</td><td class="tc">{{ $row['qty'] }}</td><td class="tc">{{ $row['satuan'] }}</td><td class="tr">{{ number_format($row['hpp'], 0, ',', '.') }}</td><td class="tr">{{ number_format($row['tax'], 0, ',', '.') }}</td><td class="tr">{{ number_format($row['hpp_after_tax'], 0, ',', '.') }}</td><td class="tr">{{ number_format($row['inventory_before_tax'], 0, ',', '.') }}</td><td class="tr">{{ number_format($row['inventory_after_tax'], 0, ',', '.') }}</td></tr>@empty<tr><td colspan="12" class="tc">Tidak ada data</td></tr>@endforelse
</tbody><tfoot><tr><th colspan="10">Total Persediaan</th><th class="tr">{{ number_format($summary['total_persediaan_sebelum_pajak'], 0, ',', '.') }}</th><th class="tr">{{ number_format($summary['total_persediaan_setelah_pajak'], 0, ',', '.') }}</th></tr></tfoot></table>
</body></html>
