<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        .info-table { width: 100%; margin: 8px 0; border-collapse: collapse; }
        .info-table td { font-size: 8.5px; padding: 1.5px 3px; vertical-align: top; border: none; }
        .info-table .label { font-weight: bold; width: 110px; }
        .info-table .colon { width: 8px; }
        .badge { padding: 2px 6px; border-radius: 3px; font-size: 7.5px; font-weight: bold; color: #fff; }
        .badge-success { background-color: #28a745; }
        .badge-warning { background-color: #ffc107; color: #000; }
        .badge-info    { background-color: #17a2b8; }
        .section-title { font-size: 9px; font-weight: bold; margin: 10px 0 3px; border-bottom: 1px solid #333; padding-bottom: 2px; }
        .summary-box { margin-top: 10px; width: 55%; border-collapse: collapse; border: 0.5px solid #aaa; }
        .summary-box td { padding: 2px 5px; font-size: 8.5px; border: none; }
        .summary-box .label { font-weight: bold; width: 110px; }
        .summary-box .colon { width: 8px; }
        .signature { margin-top: 30px; display: table; width: 100%; }
        .sig-col { display: table-cell; width: 33.33%; text-align: center; font-size: 8.5px; }
        .sig-line { margin-top: 48px; border-top: 1px solid #000; width: 75%; margin-left: auto; margin-right: auto; }
    </style>
</head>
<body>

@include('exports.pdf._header')

<div class="report-title">DOKUMEN RETUR PEMBELIAN (GUDANG → SUPPLIER)</div>

@php
    $statusClass = $retur->status === 'complete' ? 'badge-success' : 'badge-warning';
    $statusLabel = $retur->status === 'complete' ? 'COMPLETE' : 'PROSES';
@endphp

<table class="info-table">
    <tr>
        <td class="label">Kode Retur</td>
        <td class="colon">:</td>
        <td><strong>{{ $retur->code }}</strong></td>
        <td class="label">Status</td>
        <td class="colon">:</td>
        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
    </tr>
    <tr>
        <td class="label">Tanggal</td>
        <td class="colon">:</td>
        <td>{{ $retur->tanggal->isoFormat('DD MMMM YYYY') }}</td>
        <td class="label">Operator</td>
        <td class="colon">:</td>
        <td>{{ $retur->user->name ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Supplier</td>
        <td class="colon">:</td>
        <td colspan="3">{{ $retur->supplier->name ?? '-' }}</td>
    </tr>
</table>

<div class="section-title">Detail Item Retur</div>

<table>
    <thead>
        <tr>
            <th style="width:4%">No</th>
            <th style="width:10%">Kode Barang</th>
            <th style="width:22%">Nama Barang</th>
            <th style="width:12%">Batch/SKU</th>
            <th style="width:11%">Qty</th>
            <th style="width:7%">Satuan</th>
            <th style="width:11%">Harga Satuan</th>
            <th style="width:11%">Subtotal</th>
            <th style="width:12%">Alasan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($retur->refundPembelianItems as $i => $item)
            @php $k = $item->product?->konversiDisplay($item->qty) ?? '-'; @endphp
            <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                <td class="tc">{{ $i + 1 }}</td>
                <td>{{ $item->product->code ?? '-' }}</td>
                <td>{{ $item->product->name ?? '-' }}</td>
                <td class="tc">{{ $item->sku ?? '-' }}</td>
                <td class="tc">{{ $item->qty . ($k && $k !== '-' ? " ({$k})" : '') }}</td>
                <td class="tc">{{ $item->product->satuan ?? 'PCS' }}</td>
                <td class="tr">Rp {{ number_format($item->harga ?? 0, 0, ',', '.') }}</td>
                <td class="tr">Rp {{ number_format($item->qty * ($item->harga ?? 0), 0, ',', '.') }}</td>
                <td>{{ $item->alasan }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="tc">Tidak ada data</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7" class="tr" style="font-weight:bold; border-top: 1px solid #333;">Total</td>
            <td class="tr" style="font-weight:bold; border-top: 1px solid #333;">Rp {{ number_format($retur->total ?? 0, 0, ',', '.') }}</td>
            <td style="border-top: 1px solid #333;"></td>
        </tr>
    </tfoot>
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
    <div class="sig-col">
        <div><strong>Disetujui</strong></div>
        <div>Manager</div>
        <div class="sig-line"></div>
        <div><strong>Nama</strong></div>
    </div>
</div>

</body>
</html>
