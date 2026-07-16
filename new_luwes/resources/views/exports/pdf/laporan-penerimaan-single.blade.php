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
        .badge-info    { background-color: #17a2b8; }
        .badge-warning { background-color: #ffc107; color: #000; }
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

<div class="report-title">DOKUMEN PENERIMAAN BARANG (PEMBELIAN)</div>

@php
    $receiptStatus = $pembelian->receipt_status ?? 'draft';
    $badgeClass = match($receiptStatus) {
        'completed' => 'badge-success',
        'validated' => 'badge-info',
        default     => 'badge-warning',
    };
@endphp

<table class="info-table">
    <tr>
        <td class="label">No PO</td>
        <td class="colon">:</td>
        <td><strong>{{ $pembelian->code }}</strong></td>
        <td class="label">Status</td>
        <td class="colon">:</td>
        <td><span class="badge {{ $badgeClass }}">{{ strtoupper($receiptStatus) }}</span></td>
    </tr>
    <tr>
        <td class="label">No Pembelian</td>
        <td class="colon">:</td>
        <td><strong>{{ $pembelian->code_gr ?? '-' }}</strong></td>
        <td class="label">Tgl Terima</td>
        <td class="colon">:</td>
        <td>{{ $pembelian->receipt_date ? \Carbon\Carbon::parse($pembelian->receipt_date)->isoFormat('DD MMMM YYYY HH:mm') : '-' }}</td>
    </tr>
    <tr>
        <td class="label">Supplier</td>
        <td class="colon">:</td>
        <td>{{ $pembelian->supplier?->name ?? '-' }}</td>
        <td class="label">PIC</td>
        <td class="colon">:</td>
        <td>{{ $pembelian->receipt_pic ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Keterangan</td>
        <td class="colon">:</td>
        <td colspan="3">{{ $pembelian->notes ?? '-' }}</td>
    </tr>
</table>

<div class="section-title">Detail Barang Diterima</div>

<table>
    <thead>
        <tr>
            <th style="width:4%">No</th>
            <th style="width:10%">Kode Barang</th>
            <th style="width:22%">Nama Barang</th>
            <th style="width:13%">No Batch / SKU</th>
            <th style="width:10%">Expired Date</th>
            <th style="width:7%">Satuan</th>
            <th style="width:7%">Qty PO</th>
            <th style="width:7%">Qty Terima</th>
            <th style="width:7%">Selisih</th>
            <th style="width:8%">Kondisi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($pembelian->pembelianProducts as $i => $item)
            @php
                $stock    = $pembelian->stocks->where('product_id', $item->product_id)->first();
                $qtyPo    = $item->qty ?? 0;
                $qtyTerima = $item->qty_diterima ?? $stock?->qty ?? 0;
                $selisih  = $qtyTerima - $qtyPo;
                $kPo      = $item->product?->konversiDisplay($qtyPo);
                $kTerima  = $item->product?->konversiDisplay($qtyTerima);
            @endphp
            <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                <td class="tc">{{ $i + 1 }}</td>
                <td>{{ $item->product?->code ?? '-' }}</td>
                <td>{{ $item->product?->name ?? '-' }}</td>
                <td class="tc">{{ $stock?->sku ?? '-' }}</td>
                <td class="tc">
                    {{ $stock?->expired_date ? \Carbon\Carbon::parse($stock->expired_date)->format('d/m/Y') : '-' }}
                </td>
                <td class="tc">{{ $item->product?->satuan ?? 'PCS' }}</td>
                <td class="tc">{{ $qtyPo . ($kPo && $kPo !== '-' ? " ({$kPo})" : '') }}</td>
                <td class="tc"><strong>{{ $qtyTerima . ($kTerima && $kTerima !== '-' ? " ({$kTerima})" : '') }}</strong></td>
                <td class="tc" style="{{ $selisih < 0 ? 'color:#c00;' : '' }}">
                    {{ ($selisih >= 0 ? '+' : '') . $selisih }}
                </td>
                <td class="tc">Baik</td>
            </tr>
        @empty
            <tr>
                <td colspan="10" class="tc">Tidak ada data</td>
            </tr>
        @endforelse
    </tbody>
</table>

@php
    $totalQtyPo    = $pembelian->pembelianProducts->sum('qty');
    $totalQtyTerima = $pembelian->pembelianProducts->sum('qty_diterima');
@endphp

<table class="summary-box">
    <tr>
        <td class="label">Total Qty PO</td>
        <td class="colon">:</td>
        <td>{{ $totalQtyPo }}</td>
    </tr>
    <tr>
        <td class="label">Total Qty Diterima</td>
        <td class="colon">:</td>
        <td><strong>{{ $totalQtyTerima }}</strong></td>
    </tr>
    <tr>
        <td class="label">Total Nilai</td>
        <td class="colon">:</td>
        <td><strong>Rp {{ number_format($pembelian->total ?? 0, 0, ',', '.') }}</strong></td>
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
    <div class="sig-col">
        <div><strong>Disetujui</strong></div>
        <div>Manager</div>
        <div class="sig-line"></div>
        <div><strong>Nama</strong></div>
    </div>
</div>

</body>
</html>
