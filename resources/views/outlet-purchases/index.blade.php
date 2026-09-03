@extends('layouts.master')

@section('title', 'Belanja Langsung → Stock Toko')

@section('container')
<section class="content-header"><h1>Belanja Langsung <small>Penambahan Stock Toko</small></h1></section>
<section class="content"><div class="box box-primary">
    <div class="box-header">
        <div class="alert alert-info">
            Gunakan halaman ini hanya jika outlet membeli barang langsung dari supplier.
            Barang dari Gudang tidak diinput di sini; gunakan <strong>Delivery Order (Outbound)</strong>.
            Setiap transaksi yang disimpan otomatis menambah saldo <strong>Stock Toko</strong> dan kartu stok.
        </div>
        <a href="{{ route('owner-stocks.index') }}" class="btn btn-default"><i class="fa fa-cubes"></i> Lihat Stock Toko</a>
        <a href="{{ route('outlet-purchases.create') }}" class="btn btn-success"><i class="fa fa-plus"></i> Tambah Belanja Langsung</a>
    </div>
    <div class="box-body table-responsive"><table class="table table-bordered table-striped">
        <thead><tr><th>Kode</th><th>Tanggal</th><th>Outlet</th><th>Supplier</th><th>Subtotal</th><th>Aksi</th></tr></thead>
        <tbody>@forelse($purchases as $purchase)<tr>
            <td>{{ $purchase->code }}</td><td>{{ optional($purchase->purchase_date)->format('Y-m-d') }}</td><td>{{ $purchase->outlet?->name }}</td><td>{{ $purchase->supplier?->name }}</td><td>@currency($purchase->subtotal)</td>
            <td><a href="{{ route('outlet-purchases.show', $purchase) }}" class="btn btn-xs btn-info">Lihat</a></td>
        </tr>@empty<tr><td colspan="6" class="text-center">Belum ada pembelian langsung.</td></tr>@endforelse</tbody>
    </table>{{ $purchases->links() }}</div>
</div></section>
@endsection
