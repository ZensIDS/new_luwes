<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-size: 7px
        }

        table {
            font-size: 6.5px
        }

        th {
            padding: 3px 2px
        }

        td {
            padding: 2px 2px
        }

        .tc {
            text-align: center
        }

        .danger {
            color: #b00;
            font-weight: bold
        }

        .adj {
            font-weight: bold
        }
    </style>
</head>

<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN MINIMAL STOCK GUDANG &amp; ALOKASI PO</div>
    <div class="report-periode">Per tanggal {{ $asOf }} | Periode penjualan {{ $windowStart }} s/d
        {{ $windowEnd }}</div>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Barcode</th>
                <th>Produk</th>
                <th>Kategori</th>
                <th>Avg Jual/Bln</th>
                <th>Penjualan Pertama</th>
                <th>Min Manual</th>
                <th>Min Dasar</th>
                <th>Adj %</th>
                <th>Min Final</th>
                <th>Stok Gudang</th>
                <th>Saran PO</th>
                <th>Status</th>
                <th>Sumber</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td class="tc">{{ $i + 1 }}</td>
                    <td>{{ $row['barcode'] }}</td>
                    <td>{{ $row['product'] }}</td>
                    <td>{{ $row['category'] }}</td>
                    <td class="tc">{{ number_format($row['average_sold'], 2, ',', '.') }}</td>
                    <td class="tc">
                        {{ $row['first_sale'] ? \Carbon\Carbon::parse($row['first_sale'])->format('d/m/Y') : '-' }}</td>
                    <td class="tc">{{ $row['manual_min_stock'] }}</td>
                    <td class="tc">{{ $row['base_min_stock'] }}</td>
                    <td class="tc {{ $row['adjustment_pct'] > 0 ? 'adj' : '' }}">
                        {{ $row['adjustment_pct'] > 0 ? rtrim(rtrim(number_format($row['adjustment_pct'], 2, ',', '.'), '0'), ',') : '-' }}
                    </td>
                    <td class="tc adj">{{ $row['min_stock'] }}</td>
                    <td class="tc">{{ $row['stock_qty'] }}</td>
                    <td class="tc">{{ $row['suggested_po_qty'] }}</td>
                    <td class="tc {{ $row['status'] === 'out_of_stock' ? 'danger' : '' }}">
                        {{ $row['status'] === 'out_of_stock' ? 'Out of Stock' : 'Normal' }}</td>
                    <td>{{ $row['calculation_status'] }}</td>
            </tr>@empty<tr>
                    <td colspan="14" class="tc">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    <p>Min Dasar: hasil hitung 3 bulan (jumlah semua outlet) bila produk sudah punya 3 bulan data penjualan, selain itu
        memakai Min Manual. Min Final = Min Dasar + adjustment aktif (Adj %). Faktor PO per outlet: 1 PO/bulan = 2,5; 2
        = 1,875; 3 = 1,25; 4 atau lebih = 0,625. Status Out of Stock bila stok gudang &lt; Min Final. Saran PO = Min
        Final - stok gudang (bila Out of Stock).</p>
</body>

</html>
