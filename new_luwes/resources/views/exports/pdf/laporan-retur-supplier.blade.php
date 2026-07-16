<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN RETUR KE SUPPLIER KESELURUHAN</div>
    <div class="report-periode">Periode: {{ \Carbon\Carbon::parse($mulai)->isoFormat('DD MMMM YYYY') }} &ndash; {{ \Carbon\Carbon::parse($selesai)->isoFormat('DD MMMM YYYY') }}</div>

    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Tanggal</th>
                <th style="width:11%">Kode Retur</th>
                <th style="width:10%">Kode PO</th>
                <th style="width:11%">Supplier</th>
                <th style="width:7%">Kode Barang</th>
                <th style="width:14%">Nama Barang</th>
                <th style="width:10%">Batch</th>
                <th style="width:9%">Qty</th>
                <th style="width:5%">Satuan</th>
                <th style="width:12%">Alasan</th>
                <th style="width:7%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $item)
                @php
                    $retur = $item->retur;
                    $k     = $item->product?->konversiDisplay($item->qty) ?? '-';
                @endphp
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $i + 1 }}</td>
                    <td class="tc">{{ \Carbon\Carbon::parse($retur->tanggal)->isoFormat('DD MMM YY') }}</td>
                    <td>{{ $retur->code }}</td>
                    <td>{{ $item->stock->pembelian->code ?? '-' }}</td>
                    <td>{{ $retur->supplier->name ?? '-' }}</td>
                    <td>{{ $item->product->code ?? '-' }}</td>
                    <td>{{ $item->product->name ?? '-' }}</td>
                    <td class="tc">{{ $item->sku ?? '-' }}</td>
                    <td class="tc">{{ $item->qty . ($k && $k !== '-' ? " ({$k})" : '') }}</td>
                    <td class="tc">{{ $item->product->satuan ?? 'PCS' }}</td>
                    <td>{{ $item->alasan }}</td>
                    <td class="tc">{{ $retur->status === 'retur' ? 'Proses' : 'Complete' }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="tc">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
