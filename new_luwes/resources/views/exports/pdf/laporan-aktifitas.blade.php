<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN AKTIVITAS GUDANG</div>
    <div class="report-periode">Periode: {{ \Carbon\Carbon::parse($mulai)->isoFormat('DD MMMM YYYY') }} &ndash;
        {{ \Carbon\Carbon::parse($selesai)->isoFormat('DD MMMM YYYY') }}</div>
    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Tanggal</th>
                <th style="width:10%">Jenis Aktivitas</th>
                <th style="width:12%">No Dokumen</th>
                <th style="width:8%">Kode Barang</th>
                <th style="width:16%">Nama Barang</th>
                <th style="width:5%">Qty</th>
                <th style="width:5%">Satuan</th>
                <th style="width:8%">Lokasi</th>
                <th style="width:6%">PIC</th>
                <th style="width:7%">Status</th>
                <th style="width:12%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $i => $m)
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $i + 1 }}</td>
                    <td class="tc">{{ $m->created_at->isoFormat('DD MMM YYYY') }}</td>
                    <td class="tc">{{ $m->jenis }}</td>
                    <td>{{ $m->doc_code }}</td>
                    <td>{{ $m->product?->code ?? '-' }}</td>
                    <td>{{ $m->product?->name ?? '-' }}</td>
                    @php $k = $m->product?->konversiDisplay($m->qty); @endphp
                    <td class="tc">{{ $m->qty . ($k && $k !== '-' ? " ({$k})" : '') }}</td>
                    <td class="tc">{{ $m->product?->satuan ?? 'PCS' }}</td>
                    <td class="tc">{{ $m->lokasi }}</td>
                    <td class="tc">{{ $m->pic }}</td>
                    <td class="tc">{{ $m->status }}</td>
                    <td>{{ $m->notes }}</td>
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
