<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN BARANG MASUK</div>
    <div class="report-periode">Periode: {{ \Carbon\Carbon::parse($mulai)->isoFormat('DD MMMM YYYY') }} &ndash;
        {{ \Carbon\Carbon::parse($selesai)->isoFormat('DD MMMM YYYY') }}</div>
    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Tanggal</th>
                <th style="width:14%">No Dokumen</th>
                <th style="width:11%">Supplier</th>
                <th style="width:8%">Kode Barang</th>
                <th style="width:18%">Nama Barang</th>
                <th style="width:12%">Batch</th>
                <th style="width:9%">Qty Masuk</th>
                <th style="width:6%">Satuan</th>
                <th style="width:10%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movements as $i => $m)
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $i + 1 }}</td>
                    <td class="tc">{{ $m->created_at->isoFormat('DD MMM YYYY') }}</td>
                    <td>{{ $m->doc_code }}</td>
                    <td>{{ $m->supplier_n }}</td>
                    <td>{{ $m->product?->code ?? '-' }}</td>
                    <td>{{ $m->product?->name ?? '-' }}</td>
                    <td>{{ $m->batch }}</td>
                    @php $k = $m->product?->konversiDisplay($m->qty_in ?? 0); @endphp
                    <td class="tc">{{ $m->qty_in . ($k && $k !== '-' ? " ({$k})" : '') }}</td>
                    <td class="tc">{{ $m->product?->satuan ?? 'PCS' }}</td>
                    <td>{{ $m->notes }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="tc">Tidak ada data</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>

</html>
