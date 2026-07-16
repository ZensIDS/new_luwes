<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        .info-table { width: 100%; margin: 8px 0; border-collapse: collapse; }
        .info-table td { font-size: 8.5px; padding: 1.5px 3px; vertical-align: top; border: none; }
        .info-table .label { font-weight: bold; width: 110px; }
        .info-table .colon { width: 8px; }

        .badge { padding: 2px 7px; border-radius: 3px; font-size: 7.5px; font-weight: bold; }
        .badge-paid    { background-color: #28a745; color: #fff; }
        .badge-partial { background-color: #ffc107; color: #000; }
        .badge-unpaid  { background-color: #dc3545; color: #fff; }
        .badge-none    { background-color: #6c757d; color: #fff; }

        .section-title { font-size: 9px; font-weight: bold; margin: 10px 0 3px; border-bottom: 1px solid #333; padding-bottom: 2px; }

        .summary-wrap { width: 50%; margin-left: auto; margin-top: 6px; border-collapse: collapse; }
        .summary-wrap td { font-size: 8.5px; padding: 2px 4px; border: none; }
        .summary-wrap .label { font-weight: bold; }
        .summary-wrap .val { text-align: right; }
        .summary-wrap .total-row td { border-top: 1px solid #333; font-weight: bold; }

        .history-table th { background-color: #4472C4; color: #fff; font-size: 8px; padding: 3px; border: 0.5px solid #2F5597; text-align: center; }
        .history-table td { font-size: 8px; padding: 2px 4px; border: 0.5px solid #aaa; }

        .outstanding-box { margin-top: 6px; width: 50%; margin-left: auto; border-collapse: collapse; background-color: #FFF2CC; }
        .outstanding-box td { font-size: 9px; padding: 3px 6px; font-weight: bold; border: 0.5px solid #aaa; }

        .signature { margin-top: 30px; display: table; width: 100%; }
        .sig-col { display: table-cell; width: 33.33%; text-align: center; font-size: 8.5px; }
        .sig-line { margin-top: 48px; border-top: 1px solid #000; width: 75%; margin-left: auto; margin-right: auto; }
    </style>
</head>
<body>

@include('exports.pdf._header')

<div class="report-title">FAKTUR PEMBAYARAN PEMBELIAN</div>

@php
    $tx     = $pembelian->pembelianTransaction;
    $status = $tx?->status ?? 'none';
    $badgeClass = match($status) {
        'paid'    => 'badge-paid',
        'partial' => 'badge-partial',
        'unpaid'  => 'badge-unpaid',
        default   => 'badge-none',
    };
    $paidAmount  = $tx?->amount ?? 0;
    $grandTotal  = $pembelian->total ?? 0;
    $outstanding = $grandTotal - $paidAmount;
@endphp

<table class="info-table" style="margin-top:8px;">
    <tr>
        <td class="label">No PO</td>
        <td class="colon">:</td>
        <td><strong>{{ $pembelian->code }}</strong></td>
        <td class="label">Status Pembayaran</td>
        <td class="colon">:</td>
        <td><span class="badge {{ $badgeClass }}">{{ strtoupper($status) }}</span></td>
    </tr>
    <tr>
        <td class="label">No Pembelian</td>
        <td class="colon">:</td>
        <td>{{ $pembelian->code_gr ?? '-' }}</td>
        <td class="label">Tgl Pembayaran</td>
        <td class="colon">:</td>
        <td>{{ $tx?->payment_date ? \Carbon\Carbon::parse($tx->payment_date)->isoFormat('DD MMMM YYYY') : '-' }}</td>
    </tr>
    <tr>
        <td class="label">Supplier</td>
        <td class="colon">:</td>
        <td>{{ $pembelian->supplier?->name ?? '-' }}</td>
        <td class="label">Metode Pembayaran</td>
        <td class="colon">:</td>
        <td>{{ $tx ? ucfirst(str_replace('_', ' ', $tx->payment_method ?? '-')) : '-' }}</td>
    </tr>
    <tr>
        <td class="label">Tgl Terima</td>
        <td class="colon">:</td>
        <td>{{ $pembelian->receipt_date ? \Carbon\Carbon::parse($pembelian->receipt_date)->isoFormat('DD MMMM YYYY') : '-' }}</td>
        <td class="label">Referensi</td>
        <td class="colon">:</td>
        <td>{{ $tx?->payment_reference ?? '-' }}</td>
    </tr>
    @if($tx?->notes)
    <tr>
        <td class="label">Catatan</td>
        <td class="colon">:</td>
        <td colspan="3">{{ $tx->notes }}</td>
    </tr>
    @endif
</table>

{{-- ITEMS --}}
<div class="section-title">Detail Barang</div>
<table>
    <thead>
        <tr>
            <th style="width:4%">No</th>
            <th style="width:10%">Kode</th>
            <th style="width:30%">Nama Barang</th>
            <th style="width:8%">Satuan</th>
            <th style="width:8%">Qty</th>
            <th style="width:8%">Qty Terima</th>
            <th style="width:16%">Harga Beli (Rp)</th>
            <th style="width:16%">Subtotal (Rp)</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($pembelian->pembelianProducts as $i => $item)
            <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                <td class="tc">{{ $i + 1 }}</td>
                <td>{{ $item->product?->code ?? '-' }}</td>
                <td>{{ $item->product?->name ?? '-' }}</td>
                <td class="tc">{{ $item->product?->satuan ?? $item->product?->unit ?? 'PCS' }}</td>
                @php
                    $kQty = $item->product?->konversiDisplay($item->qty);
                    $kRcv = $item->qty_diterima ? $item->product?->konversiDisplay($item->qty_diterima) : null;
                @endphp
                <td class="tc">{{ $item->qty . ($kQty && $kQty !== '-' ? " ({$kQty})" : '') }}</td>
                <td class="tc">{{ $item->qty_diterima ? ($item->qty_diterima . ($kRcv && $kRcv !== '-' ? " ({$kRcv})" : '')) : '-' }}</td>
                <td class="tr">{{ number_format($item->harga_beli, 0, ',', '.') }}</td>
                <td class="tr">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="tc">Tidak ada data</td></tr>
        @endforelse
    </tbody>
</table>

{{-- SUMMARY --}}
<table class="summary-wrap">
    <tr>
        <td class="label">Grand Total</td>
        <td class="val">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
    </tr>
    <tr>
        <td class="label">Total Dibayar</td>
        <td class="val">Rp {{ number_format($paidAmount, 0, ',', '.') }}</td>
    </tr>
    <tr class="total-row">
        <td class="label">Outstanding</td>
        <td class="val" style="{{ $outstanding > 0 ? 'color:#c00;' : 'color:#28a745;' }}">
            Rp {{ number_format($outstanding, 0, ',', '.') }}
        </td>
    </tr>
</table>

{{-- PAYMENT HISTORY --}}
@if (!empty($paymentHistory))
    <div class="section-title">Riwayat Pembayaran</div>
    <table class="history-table">
        <thead>
            <tr>
                <th style="width:4%">No</th>
                <th style="width:15%">Tanggal</th>
                <th style="width:20%">Jumlah (Rp)</th>
                <th style="width:15%">Metode</th>
                <th style="width:18%">Referensi</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($paymentHistory as $i => $h)
                <tr class="{{ $i % 2 == 1 ? 'alt' : '' }}">
                    <td class="tc">{{ $i + 1 }}</td>
                    <td class="tc">
                        {{ \Carbon\Carbon::parse($h['payment_date'])->isoFormat('DD MMM YYYY') }}
                    </td>
                    <td class="tr">{{ number_format($h['amount'], 0, ',', '.') }}</td>
                    <td class="tc">{{ ucfirst(str_replace('_', ' ', $h['payment_method'] ?? '-')) }}</td>
                    <td>{{ $h['payment_reference'] ?? '-' }}</td>
                    <td>{{ $h['notes'] ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- SIGNATURE --}}
<div class="signature">
    <div class="sig-col">
        <div><strong>Dibuat Oleh</strong></div>
        <div>Staff Keuangan</div>
        <div class="sig-line"></div>
        <div><strong>Nama</strong></div>
    </div>
    <div class="sig-col">
        <div><strong>Diperiksa</strong></div>
        <div>Supervisor</div>
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
