<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN PENGIRIMAN BARANG</div>
    <div class="report-periode">Periode: {{ \Carbon\Carbon::parse($mulai)->isoFormat('DD MMMM YYYY') }} &ndash;
        {{ \Carbon\Carbon::parse($selesai)->isoFormat('DD MMMM YYYY') }}</div>
    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Tanggal</th>
                <th style="width:11%">No Surat Jalan</th>
                <th style="width:11%">No Dokumen</th>
                <th style="width:9%">Tujuan</th>
                <th style="width:8%">Kode Barang</th>
                <th style="width:15%">Nama Barang</th>
                <th style="width:10%">Batch</th>
                <th style="width:5%">Qty</th>
                <th style="width:5%">Satuan</th>
                <th style="width:7%">Status</th>
                <th style="width:10%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $row['no'] }}</td>
                    <td class="tc">{{ $row['tanggal'] }}</td>
                    <td>{{ $row['no_sj'] }}</td>
                    <td>{{ $row['no_do'] }}</td>
                    <td>{{ $row['tujuan'] }}</td>
                    <td>{{ $row['kode_barang'] }}</td>
                    <td>{{ $row['nama_barang'] }}</td>
                    <td>{{ $row['batch'] }}</td>
                    <td class="tc">{{ $row['qty_kirim'] }}</td>
                    <td class="tc">{{ $row['satuan'] }}</td>
                    <td class="tc">{{ $row['status'] }}</td>
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
