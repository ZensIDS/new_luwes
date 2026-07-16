<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN RETUR OUTLET KESELURUHAN</div>
    <div class="report-periode">Periode: {{ \Carbon\Carbon::parse($mulai)->isoFormat('DD MMMM YYYY') }} &ndash; {{ \Carbon\Carbon::parse($selesai)->isoFormat('DD MMMM YYYY') }}</div>

    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Tanggal</th>
                <th style="width:10%">Kode Retur</th>
                <th style="width:11%">Outlet</th>
                <th style="width:10%">No. DO</th>
                <th style="width:7%">Kode Barang</th>
                <th style="width:14%">Nama Barang</th>
                <th style="width:9%">Batch</th>
                <th style="width:7%">Expired</th>
                <th style="width:8%">Qty</th>
                <th style="width:5%">Satuan</th>
                <th style="width:11%">Alasan</th>
                <th style="width:7%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $item)
                @php
                    $retur   = $item->retur;
                    $k       = $item->product?->konversiDisplay($item->qty) ?? '-';
                    $expired = $item->stock?->expired_at
                        ? \Carbon\Carbon::parse($item->stock->expired_at)->format('d/m/Y')
                        : '-';
                @endphp
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $i + 1 }}</td>
                    <td class="tc">{{ \Carbon\Carbon::parse($retur->tanggal)->isoFormat('DD MMM YY') }}</td>
                    <td>{{ $retur->code }}</td>
                    <td>{{ $retur->outlet->name ?? '-' }}</td>
                    <td>{{ $retur->deliveryOrder->code ?? '-' }}</td>
                    <td>{{ $item->product->code ?? '-' }}</td>
                    <td>{{ $item->product->name ?? '-' }}</td>
                    <td class="tc">{{ $item->sku ?? '-' }}</td>
                    <td class="tc">{{ $expired }}</td>
                    <td class="tc">{{ $item->qty . ($k && $k !== '-' ? " ({$k})" : '') }}</td>
                    <td class="tc">{{ $item->product->satuan ?? 'PCS' }}</td>
                    <td>{{ $item->alasan }}</td>
                    <td class="tc">{{ $retur->status === 'complete' ? 'Terkirim' : 'Proses' }}</td>
                </tr>
            @empty
                <tr><td colspan="13" class="tc">Tidak ada data</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
