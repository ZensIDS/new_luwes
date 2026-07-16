<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN PERGERAKAN DAN KEBUTUHAN STOK</div>
    <div class="report-periode">Per Tanggal: {{ now()->isoFormat('DD MMMM YYYY') }}</div>
    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Kode Barang</th>
                <th style="width:17%">Nama Barang</th>
                <th style="width:7%">Stok</th>
                <th style="width:9%">Rata-rata Keluar/Bln</th>
                <th style="width:7%">Hari Tanpa Trx</th>
                <th style="width:9%">Kategori</th>
                <th style="width:8%">Status Stok</th>
                <th style="width:7%">Min Stok</th>
                <th style="width:8%">Saran Reorder</th>
                <th style="width:7%">Qty Reorder</th>
                <th style="width:12%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $i + 1 }}</td>
                    <td>{{ $row['kode_barang'] }}</td>
                    <td>{{ $row['nama_barang'] }}</td>
                    <td class="tc">{{ $row['stok'] }}</td>
                    <td class="tc">{{ $row['avg_keluar'] }}</td>
                    <td class="tc">{{ $row['hari_tanpa'] }}</td>
                    <td class="tc">{{ $row['kategori'] }}</td>
                    <td class="tc">{{ $row['status_stok'] }}</td>
                    <td class="tc">{{ $row['min_stok'] }}</td>
                    <td class="tc">{{ $row['saran_reorder'] }}</td>
                    <td class="tc">{{ $row['qty_reorder'] }}</td>
                    <td>{{ $row['keterangan'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" class="tc">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
