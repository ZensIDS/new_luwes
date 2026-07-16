<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN STOK BARANG</div>
    <div class="report-periode">Per Tanggal: {{ now()->isoFormat('DD MMMM YYYY') }}</div>
    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Kode Barang</th>
                <th style="width:16%">Nama Barang</th>
                <th style="width:12%">Batch</th>
                <th style="width:9%">Expired Date</th>
                <th style="width:8%">Kategori</th>
                <th style="width:5%">Satuan</th>
                <th style="width:9%">Stok</th>
                <th style="width:6%">Min Stok</th>
                <th style="width:6%">Selisih</th>
                <th style="width:7%">Status Stok</th>
                <th style="width:7%">Status Expired</th>
                <th style="width:9%">Lokasi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($stocks as $i => $s)
                @php
                    $baseMin    = $s->product?->min_stock ?? 0;
                    $adj        = $activeAdjs->get($s->product_id);
                    $minStok    = $adj ? (int) ceil($baseMin * (1 + $adj->adjustment_percentage / 100)) : (int) $baseMin;
                    $selisih    = ($s->qty ?? 0) - $minStok;
                    $statusStok = ($s->qty ?? 0) > $minStok ? 'Aman' : (($s->qty ?? 0) > 0 ? 'Kritis' : 'Habis');
                    $statusExp =
                        $s->expired_at && \Carbon\Carbon::parse($s->expired_at)->isPast() ? 'Expired' : 'Belum Expired';
                @endphp
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $i + 1 }}</td>
                    <td>{{ $s->product?->code ?? '-' }}</td>
                    <td>{{ $s->product?->name ?? '-' }}</td>
                    <td>{{ $s->sku ?? '-' }}</td>
                    <td class="tc">
                        {{ $s->expired_at ? \Carbon\Carbon::parse($s->expired_at)->format('d/m/Y') : '-' }}
                    </td>
                    <td>{{ $s->product?->category?->name ?? '-' }}</td>
                    <td class="tc">{{ $s->product?->satuan ?? 'PCS' }}</td>
                    @php $k = $s->product?->konversiDisplay($s->qty ?? 0); @endphp
                    <td class="tc">{{ ($s->qty ?? 0) . ($k && $k !== '-' ? " ({$k})" : '') }}</td>
                    <td class="tc">{{ $minStok }}</td>
                    <td class="tc">{{ ($selisih >= 0 ? '+' : '') . $selisih }}</td>
                    <td class="tc">{{ $statusStok }}</td>
                    <td class="tc">{{ $s->expired_at ? $statusExp : '-' }}</td>
                    <td>{{ $s->location ?? '-' }}</td>
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
