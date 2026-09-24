<!doctype html><html><head><meta charset="utf-8"><style>
body{font-size:7.5px}table{font-size:7px}th{padding:3px 2px}td{padding:2px 3px}.tr{text-align:right}.tc{text-align:center}
</style></head><body>
@include('exports.pdf._header')
<div class="report-title">LAPORAN RAFAKSI OUTLET</div><div class="report-periode">Periode: {{ $mulai }} s/d {{ $selesai }}{{ isset($promotion_id) && $promotion_id ? ' | Rafaksi terpilih: '.$promotion_id : '' }}</div>
<table><thead><tr><th>Tanggal</th><th>Invoice</th><th>Outlet</th><th>Kasir</th><th>Barcode</th><th>Produk</th><th>Qty</th><th>Harga</th><th>Diskon/Unit</th><th>Total Diskon</th><th>Subtotal</th><th>Keterangan</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ \Carbon\Carbon::parse($row['tanggal'])->format('d/m/Y H:i') }}</td><td>{{ $row['invoice'] }}</td><td>{{ $row['outlet'] }}</td><td>{{ $row['kasir'] }}</td><td>{{ $row['barcode'] }}</td><td>{{ $row['product'] }}</td><td class="tc">{{ $row['qty'] }}</td><td class="tr">{{ number_format($row['unit_price'], 0, ',', '.') }}</td><td class="tr">{{ number_format($row['discount_per_unit'], 0, ',', '.') }}</td><td class="tr">{{ number_format($row['discount_total'], 0, ',', '.') }}</td><td class="tr">{{ number_format($row['subtotal'], 0, ',', '.') }}</td><td>{{ $row['keterangan'] }}</td></tr>@empty<tr><td colspan="12" class="tc">Tidak ada data</td></tr>@endforelse
</tbody><tfoot><tr><th colspan="6">Rekap</th><th>{{ $summary['qty'] }}</th><th></th><th></th><th>{{ number_format($summary['total_diskon'], 0, ',', '.') }}</th><th>{{ number_format($summary['total_penjualan'], 0, ',', '.') }}</th><th></th></tr></tfoot></table>
</body></html>
