<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
</head>

<body>
    @include('exports.pdf._header')
    <div class="report-title">LAPORAN STOK OPNAME &amp; ADJUSTMENT</div>
    <div class="report-periode">Periode: {{ \Carbon\Carbon::parse($mulai)->isoFormat('DD MMMM YYYY') }} &ndash;
        {{ \Carbon\Carbon::parse($selesai)->isoFormat('DD MMMM YYYY') }}</div>
    <table>
        <thead>
            <tr>
                <th style="width:3%">No</th>
                <th style="width:8%">Kode Barang</th>
                <th style="width:16%">Nama Barang</th>
                <th style="width:10%">Batch</th>
                <th style="width:9%">Expired</th>
                <th style="width:5%">Satuan</th>
                <th style="width:6%">Stok Sistem</th>
                <th style="width:6%">Stok Fisik</th>
                <th style="width:5%">Selisih</th>
                <th style="width:6%">Qty Adjust</th>
                <th style="width:10%">Alasan</th>
                <th style="width:7%">Status</th>
                <th style="width:9%">Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($adjustments as $i => $adj)
                @php
                    $selisih = ($adj->physical_qty ?? 0) - ($adj->system_qty ?? ($adj->stock?->qty ?? 0));
                @endphp
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $i + 1 }}</td>
                    <td>{{ $adj->product?->code ?? '-' }}</td>
                    <td>{{ $adj->product?->name ?? '-' }}</td>
                    <td>{{ $adj->stock?->sku ?? '-' }}</td>
                    <td class="tc">
                        {{ $adj->stock?->expired_date ? \Carbon\Carbon::parse($adj->stock->expired_date)->format('d/m/Y') : '-' }}
                    </td>
                    <td class="tc">{{ $adj->product?->satuan ?? 'PCS' }}</td>
                    <td class="tc">
                        @php $sysQty = $adj->system_qty ?? ($adj->stock?->qty ?? 0); $k = $adj->product?->konversiDisplay($sysQty); @endphp
                        {{ $sysQty }}@if($k && $k !== '-') <br><small>({{ $k }})</small>@endif
                    </td>
                    <td class="tc">
                        @php $physQty = $adj->physical_qty ?? 0; $k = $adj->product?->konversiDisplay($physQty); @endphp
                        {{ $physQty }}@if($k && $k !== '-') <br><small>({{ $k }})</small>@endif
                    </td>
                    <td class="tc">{{ ($selisih >= 0 ? '+' : '') . $selisih }}</td>
                    <td class="tc">
                        @php $adjQty = $adj->qty_adjustment ?? $selisih; $k = $adj->product?->konversiDisplay(abs($adjQty)); @endphp
                        {{ $adjQty }}@if($k && $k !== '-') <br><small>({{ $k }})</small>@endif
                    </td>
                    <td>{{ $adj->reason ?? '-' }}</td>
                    <td class="tc">{{ ucfirst($adj->status ?? '-') }}</td>
                    <td>{{ $adj->notes ?? '-' }}</td>
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
