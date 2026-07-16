<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN PURCHASE REQUEST (PR)</div>
    <div class="report-periode">Periode: {{ \Carbon\Carbon::parse($mulai)->isoFormat('DD MMMM YYYY') }} &ndash;
        {{ \Carbon\Carbon::parse($selesai)->isoFormat('DD MMMM YYYY') }}</div>
    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Tanggal PR</th>
                <th style="width:11%">Kode PR</th>
                <th style="width:10%">Outlet</th>
                <th style="width:8%">Kode Barang</th>
                <th style="width:18%">Nama Barang</th>
                <th style="width:5%">QTY</th>
                <th style="width:6%">Satuan</th>
                <th style="width:8%">Status</th>
                <th style="width:11%">Kode PO</th>
                <th style="width:12%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $row['no'] }}</td>
                    <td class="tc">{{ $row['tanggal'] }}</td>
                    <td>{{ $row['kode_pr'] }}</td>
                    <td>{{ $row['outlet'] }}</td>
                    <td>{{ $row['kode_barang'] }}</td>
                    <td>{{ $row['nama_barang'] }}</td>
                    <td class="tc">{{ $row['qty'] }}</td>
                    <td class="tc">{{ $row['satuan'] }}</td>
                    <td class="tc">{{ $row['status'] }}</td>
                    <td>{{ $row['kode_po'] }}</td>
                    <td>{{ $row['keterangan'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="tc">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
