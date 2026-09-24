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
        <td>{{ $product->code ?? '-' }}</td>
        <td class="label">Lokasi Penyimpanan</td>
        <td class="colon">:</td>
        <td>{{ $product->lokasi ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Nama Barang</td>
        <td class="colon">:</td>
        <td>{{ $product->name ?? '-' }}</td>
        <td class="label">Satuan</td>
        <td class="colon">:</td>
        <td>{{ $product->satuan ?? 'PCS' }}</td>
    </tr>
    <tr>
        <td class="label">Supplier</td>
        <td class="colon">:</td>
        <td colspan="4">{{ $suppliers ?? '-' }}</td>
    </tr>
</table>

<table class="main-table">
    <thead>
        <tr>
            <th style="width:3%">No</th>
            <th style="width:11%">Tanggal</th>
            <th style="width:11%">SKU</th>
            <th style="width:7%">Stok Awal</th>
            <th style="width:7%">Masuk</th>
            <th style="width:7%">Keluar</th>
            <th style="width:8%">Stok Akhir</th>
            <th style="width:10%">Harga Satuan (Rp)</th>
            <th style="width:11%">Nilai Persediaan</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($transactions as $i => $t)
            <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                <td class="tc">{{ $i + 1 }}</td>
                <td class="tc">{{ \Carbon\Carbon::parse($t['tanggal'])->isoFormat('DD MMM YYYY') }}</td>
                <td class="tc">{{ $t['sku'] }}</td>
                @foreach (['stok_awal', 'masuk', 'keluar', 'stok_akhir'] as $col)
                    <td class="tr">
                        @if ($col === 'stok_akhir')<strong>{{ $t[$col] }}</strong>@else{{ $t[$col] }}@endif
                        @php $k = $product->konversiDisplay($t[$col]); @endphp
                        @if ($k !== '-') <br><small>({{ $k }})</small>@endif
                    </td>
                @endforeach
                <td class="tr">{{ number_format($t['harga'], 0, ',', '.') }}</td>
                <td class="tr"><strong>{{ number_format($t['nilai'], 0, ',', '.') }}</strong></td>
                <td><small>{{ $t['keterangan'] }}</small></td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="tc">Tidak ada transaksi</td>
            </tr>
        @endforelse
    </tbody>
</table>

@php
    $totalMasuk  = collect($transactions)->sum('masuk');
    $totalKeluar = collect($transactions)->sum('keluar');
@endphp

<div style="margin-top:10px; font-size:9px;"><strong>Rincian Stok per SKU</strong></div>
<table class="main-table" style="width:70%; margin-top:3px;">
    <thead>
        <tr>
            <th>SKU</th>
            <th>Stok Fisik</th>
            <th>Reserved</th>
            <th>Saldo Kartu</th>
            <th>Selisih</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($summary['breakdown'] as $b)
            <tr>
                <td>{{ $b['sku'] }}</td>
                <td class="tr">{{ $b['qty'] }}</td>
                <td class="tr">{{ $b['qty_reserved'] }}</td>
                <td class="tr">{{ $b['saldo_kartu'] }}</td>
                <td class="tr"><strong>{{ ($b['selisih'] > 0 ? '+' : '') . $b['selisih'] }}</strong></td>
            </tr>
        @endforeach
        <tr>
            <td><strong>TOTAL</strong></td>
            <td class="tr"><strong>{{ $summary['total_qty'] }}</strong></td>
            <td class="tr"><strong>{{ $summary['total_reserved'] }}</strong></td>
            <td class="tr"><strong>{{ $summary['total_saldo_kartu'] }}</strong></td>
            <td class="tr"><strong>{{ ($summary['total_selisih'] > 0 ? '+' : '') . $summary['total_selisih'] }}</strong></td>
        </tr>
    </tbody>
</table>
<div style="font-size:7.5px; margin-top:3px;">
    Stok Fisik = stok gudang (sama dengan menu Stok &amp; Produk, reserved sudah termasuk). Saldo Kartu = total Masuk - Keluar yang tercatat.
    Selisih tidak nol berarti ada perubahan stok yang tidak tercatat di kartu.
</div>

<table class="summary-box">
    <tr>
        <td class="label">Total Masuk</td>
        <td class="colon">:</td>
        <td>{{ $totalMasuk }}</td>
        <td class="label">Total Keluar</td>
        <td class="colon">:</td>
        <td>{{ $totalKeluar }}</td>
    </tr>
    <tr>
        <td class="label">Stok Fisik (Gudang)</td>
        <td class="colon">:</td>
        <td colspan="4"><strong>{{ $summary['total_qty'] }}</strong></td>
    </tr>
    <tr>
        <td class="label">Nilai Persediaan</td>
        <td class="colon">:</td>
        <td colspan="4"><strong>Rp {{ number_format($summary['total_nilai'], 0, ',', '.') }}</strong></td>
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