<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN PURCHASE ORDER (PO)</div>
    <div class="report-periode">Periode: {{ \Carbon\Carbon::parse($mulai)->isoFormat('DD MMMM YYYY') }} &ndash;
        {{ \Carbon\Carbon::parse($selesai)->isoFormat('DD MMMM YYYY') }}</div>
    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Tanggal PO</th>
                <th style="width:11%">Kode PO</th>
                <th style="width:9%">No PR</th>
                <th style="width:10%">Supplier</th>
                <th style="width:8%">Kode Barang</th>
                <th style="width:17%">Nama Barang</th>
                <th style="width:8%">QTY</th>
                <th style="width:5%">Satuan</th>
                <th style="width:9%">Harga Total</th>
                <th style="width:8%">QTY Terima</th>
                <th style="width:6%">Status</th>
                <th style="width:8%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $row['no'] }}</td>
                    <td class="tc">{{ $row['tanggal'] }}</td>
                    <td>{{ $row['kode_po'] }}</td>
                    <td>{{ $row['no_pr'] }}</td>
                    <td>{{ $row['supplier'] }}</td>
                    <td>{{ $row['kode_barang'] }}</td>
                    <td>{{ $row['nama_barang'] }}</td>
                    <td class="tc">{{ $row['qty'] }}</td>
                    <td class="tc">{{ $row['satuan'] }}</td>
                    <td class="tr">Rp {{ number_format($row['harga_total'], 0, ',', '.') }}</td>
                    <td class="tc">{{ $row['qty_diterima'] }}</td>
                    <td class="tc">{{ $row['status'] }}</td>
                    <td>{{ $row['keterangan'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="tc">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
