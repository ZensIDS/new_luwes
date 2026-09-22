@extends('layouts.master')

@section('title', 'Detail Ganti Barang')

@section('container')
    <section class="content-header">
        <h1>Detail Ganti Barang <small>{{ $refundPenjualan->code }}</small></h1>
    </section>
    <section class="content">
        <div class="box">
            <div class="box-body">
                <dl class="dl-horizontal">
                    <dt>Kode</dt><dd>{{ $refundPenjualan->code }}</dd>
                    <dt>Invoice asal</dt><dd>{{ $refundPenjualan->penjualan?->code ?? '-' }}</dd>
                    <dt>Tanggal</dt><dd>{{ $refundPenjualan->created_at?->format('d/m/Y H:i') }}</dd>
                    <dt>Outlet</dt><dd>{{ $refundPenjualan->outlet?->name ?? '-' }}</dd>
                    <dt>Diproses oleh</dt><dd>{{ $refundPenjualan->user?->name ?? '-' }}</dd>
                </dl>

                <table class="table table-bordered">
                    <thead><tr><th>Jenis</th><th>Produk</th><th>Qty</th><th>Harga / unit</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        @foreach ($refundPenjualan->items as $item)
                            <tr>
                                <td>{{ $item->type === 'return' ? 'Diretur' : 'Pengganti' }}</td>
                                <td>{{ $item->product?->code }} - {{ $item->product?->name }}</td>
                                <td>{{ $item->qty }}</td>
                                <td>@currency($item->unit_price)</td>
                                <td>@currency($item->subtotal)</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th colspan="4" class="text-right">Nilai barang diretur</th><th>@currency($refundPenjualan->returned_total)</th></tr>
                        <tr><th colspan="4" class="text-right">Nilai barang pengganti</th><th>@currency($refundPenjualan->replacement_total)</th></tr>
                        <tr><th colspan="4" class="text-right">Selisih dibayar</th><th>@currency($refundPenjualan->difference)</th></tr>
                    </tfoot>
                </table>

                @if ($refundPenjualan->notes)
                    <p><strong>Catatan:</strong> {{ $refundPenjualan->notes }}</p>
                @endif
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    Invoice asal tetap tersimpan untuk audit. Perubahan stock hanya terjadi di Stock Toko.
                </div>
            </div>
            <div class="box-footer">
                <a href="{{ route('refundPenjualan.index') }}" class="btn btn-default">Kembali</a>
                <a href="{{ route('refundPenjualan.create') }}" class="btn btn-primary">Proses Lagi</a>
            </div>
        </div>
    </section>
@endsection
