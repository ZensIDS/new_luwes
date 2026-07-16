<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN PICKING &amp; PACKING</div>
    <div class="report-periode">Periode: {{ \Carbon\Carbon::parse($mulai)->isoFormat('DD MMMM YYYY') }} &ndash;
        {{ \Carbon\Carbon::parse($selesai)->isoFormat('DD MMMM YYYY') }}</div>
    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:7%">Tanggal</th>
                <th style="width:10%">Kode Picking</th>
                <th style="width:10%">Kode DO</th>
                <th style="width:8%">Tujuan</th>
                <th style="width:7%">Kode Barang</th>
                <th style="width:13%">Nama Barang</th>
                <th style="width:7%">Lokasi</th>
                <th style="width:4%">Ord</th>
                <th style="width:4%">Pick</th>
                <th style="width:4%">Pack</th>
                <th style="width:6%">Status</th>
                <th style="width:5%">Picker</th>
                <th style="width:5%">Packer</th>
                <th style="width:9%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $row['no'] }}</td>
                    <td class="tc">{{ $row['tanggal'] }}</td>
                    <td>{{ $row['kode_picking'] }}</td>
                    <td>{{ $row['kode_do'] }}</td>
                    <td>{{ $row['tujuan'] }}</td>
                    <td>{{ $row['kode_barang'] }}</td>
                    <td>{{ $row['nama_barang'] }}</td>
                    <td>{{ $row['lokasi'] }}</td>
                    <td class="tc">{{ $row['qty_order'] }}</td>
                    <td class="tc">{{ $row['qty_pick'] }}</td>
                    <td class="tc">{{ $row['qty_pack'] }}</td>
                    <td class="tc">{{ $row['status'] }}</td>
                    <td class="tc">{{ $row['picker'] }}</td>
                    <td class="tc">{{ $row['packer'] }}</td>
                    <td>{{ $row['keterangan'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="15" class="tc">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
