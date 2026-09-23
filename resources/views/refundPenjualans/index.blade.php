@extends('layouts.master')

@section('title', 'Ganti Barang Penjualan')

@section('container')
    <section class="content-header">
    <h1>Retur / Ganti Barang Penjualan <small>Scan barang tanpa wajib mencari nota</small></h1>
    </section>

    <section class="content">
        <div class="box">
            <div class="box-header with-border">
                <a href="{{ route('refundPenjualan.create') }}" class="btn btn-primary">
                    <i class="fa fa-plus"></i> Proses Ganti Barang
                </a>
                <a href="{{ route('penjualan.index') }}" class="btn btn-default">Daftar Penjualan</a>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Kode Retur</th>
                            <th>Nota Asal</th>
                            <th>Barang Diretur</th>
                            <th>Barang Pengganti</th>
                            <th>Selisih</th>
                            <th>Outlet / User</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($refundPenjualans as $refund)
                            <tr>
                                <td>{{ $refund->created_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ $refund->code }}</td>
                            <td>{{ $refund->penjualan?->code ?? 'Retur tanpa nota' }}</td>
                                <td>
                                    @foreach ($refund->items->where('type', 'return') as $item)
                                        {{ $item->product?->name ?? '-' }} × {{ $item->qty }}<br>
                                    @endforeach
                                    <small class="text-muted">@currency($refund->returned_total)</small>
                                </td>
                                <td>
                                    @foreach ($refund->items->where('type', 'replacement') as $item)
                                        {{ $item->product?->name ?? '-' }} × {{ $item->qty }}<br>
                                    @endforeach
                                    <small class="text-muted">@currency($refund->replacement_total)</small>
                                </td>
                                <td>
                                    @if ($refund->difference > 0)
                                        <span class="label label-warning">+ @currency($refund->difference)</span>
                                    @else
                                        <span class="label label-success">Tidak ada selisih</span>
                                    @endif
                                </td>
                                <td>{{ $refund->outlet?->name ?? '-' }}<br><small>{{ $refund->user?->name ?? '-' }}</small></td>
                                <td><a href="{{ route('refundPenjualan.show', $refund) }}" class="btn btn-xs btn-info">Detail</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">Belum ada proses ganti barang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
