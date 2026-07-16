<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        .info-table { width: 100%; margin: 8px 0; border-collapse: collapse; }
        .info-table td { font-size: 8.5px; padding: 1.5px 3px; vertical-align: top; border: none; }
        .info-table .label { font-weight: bold; width: 110px; }
        .info-table .colon { width: 8px; }
        .main-table th { background-color: #8EAADB; color: #000; border: 0.5px solid #555; }
        .main-table td { border: 0.5px solid #aaa; }
        .summary-box { margin-top: 10px; width: 55%; border-collapse: collapse; border: 0.5px solid #aaa; }
        .summary-box td { padding: 2px 5px; font-size: 8.5px; border: none; }
        .summary-box .label { font-weight: bold; width: 110px; }
        .summary-box .colon { width: 8px; }
        .signature { margin-top: 30px; display: table; width: 100%; }
        .sig-col { display: table-cell; width: 50%; text-align: center; font-size: 8.5px; }
        .sig-line { margin-top: 48px; border-top: 1px solid #000; width: 75%; margin-left: auto; margin-right: auto; }
    </style>
</head>
<body>

@include('exports.pdf._header')

<div class="report-title">KARTU STOK BARANG</div>

<table class="info-table">
    <tr>
        <td class="label">Barcode</td>
        <td class="colon">:</td>
        <td>{{ $stock->product->code ?? '-' }}</td>
        <td class="label">No Batch / SKU</td>
        <td class="colon">:</td>
        <td>{{ $stock->sku ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Nama Barang</td>
        <td class="colon">:</td>
        <td>{{ $stock->product->name ?? '-' }}</td>
        <td class="label">Lokasi Penyimpanan</td>
        <td class="colon">:</td>
        <td>{{ $stock->product->lokasi ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Satuan</td>
        <td class="colon">:</td>
        <td>{{ $stock->product->satuan ?? 'PCS' }}</td>
        <td class="label">Expired Date</td>
        <td class="colon">:</td>
        <td>{{ $stock->expired_at ? \Carbon\Carbon::parse($stock->expired_at)->isoFormat('DD MMMM YYYY') : '-' }}</td>
    </tr>
    <tr>
        <td class="label">Supplier</td>
        <td class="colon">:</td>
        <td colspan="3">{{ $stock->pembelian->supplier->name ?? '-' }}</td>
    </tr>
</table>

<table class="main-table">
    <thead>
        <tr>
            <th style="width:3%">No</th>
            <th style="width:13%">Tanggal</th>
            <th style="width:8%">Stok Awal</th>
            <th style="width:8%">Masuk</th>
            <th style="width:8%">Keluar</th>
            <th style="width:8%">Stok Akhir</th>
            <th style="width:13%">Harga Satuan (Rp)</th>
            <th style="width:14%">Nilai Persediaan</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($transactions as $i => $t)
            <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                <td class="tc">{{ $i + 1 }}</td>
                <td class="tc">{{ $t['tanggal'] }}</td>
                <td class="tr">
                    {{ $t['stok_awal'] }}
                    @php $k = $stock->product->konversiDisplay($t['stok_awal']); @endphp
                    @if($k !== '-') <br><small>({{ $k }})</small>@endif
                </td>
                <td class="tr">
                    {{ $t['masuk'] }}
                    @php $k = $stock->product->konversiDisplay($t['masuk']); @endphp
                    @if($k !== '-') <br><small>({{ $k }})</small>@endif
                </td>
                <td class="tr">
                    {{ $t['keluar'] }}
                    @php $k = $stock->product->konversiDisplay($t['keluar']); @endphp
                    @if($k !== '-') <br><small>({{ $k }})</small>@endif
                </td>
                <td class="tr">
                    <strong>{{ $t['stok_akhir'] }}</strong>
                    @php $k = $stock->product->konversiDisplay($t['stok_akhir']); @endphp
                    @if($k !== '-') <br><small>({{ $k }})</small>@endif
                </td>
                <td class="tr">{{ number_format($t['harga'], 0, ',', '.') }}</td>
                <td class="tr"><strong>{{ number_format($t['nilai'], 0, ',', '.') }}</strong></td>
                <td><small>{{ $t['keterangan'] }}</small></td>
            </tr>
        @empty
            <tr>
                <td colspan="9" class="tc">Tidak ada transaksi</td>
            </tr>
        @endforelse
    </tbody>
</table>

@php
    $totalMasuk  = collect($transactions)->sum('masuk');
    $totalKeluar = collect($transactions)->sum('keluar');
    $stokAwal    = collect($transactions)->first()['stok_awal'] ?? 0;
    $stokAkhir   = collect($transactions)->last()['stok_akhir'] ?? 0;
    $nilaiAkhir  = collect($transactions)->last()['nilai'] ?? 0;
@endphp

<table class="summary-box">
    <tr>
        <td class="label">Stok Awal</td>
        <td class="colon">:</td>
        <td>{{ $stokAwal }}</td>
        <td class="label">Total Keluar</td>
        <td class="colon">:</td>
        <td>{{ $totalKeluar }}</td>
    </tr>
    <tr>
        <td class="label">Total Masuk</td>
        <td class="colon">:</td>
        <td>{{ $totalMasuk }}</td>
        <td class="label">Stok Akhir</td>
        <td class="colon">:</td>
        <td><strong>{{ $stokAkhir }}</strong></td>
    </tr>
    <tr>
        <td class="label">Nilai Persediaan</td>
        <td class="colon">:</td>
        <td colspan="4"><strong>Rp {{ number_format($nilaiAkhir, 0, ',', '.') }}</strong></td>
    </tr>
</table>

<div class="signature">
    <div class="sig-col">
        <div><strong>Dibuat Oleh</strong></div>
        <div>Staff Gudang</div>
        <div class="sig-line"></div>
        <div><strong>Nama</strong></div>
    </div>
    <div class="sig-col">
        <div><strong>Diperiksa</strong></div>
        <div>Supervisor Gudang</div>
        <div class="sig-line"></div>
        <div><strong>Nama</strong></div>
    </div>
</div>

</body>
</html>