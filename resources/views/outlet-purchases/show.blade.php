@extends('layouts.master')

@section('title', 'Detail Pembelian Langsung')

@section('container')
<section class="content-header"><h1>Detail Pembelian Langsung</h1></section>
<section class="content"><div class="box box-primary"><div class="box-body">
    <dl class="dl-horizontal"><dt>Kode</dt><dd>{{ $purchase->code }}</dd><dt>Outlet</dt><dd>{{ $purchase->outlet?->name }}</dd><dt>Supplier</dt><dd>{{ $purchase->supplier?->name }}</dd><dt>Tanggal</dt><dd>{{ optional($purchase->purchase_date)->format('Y-m-d') }}</dd><dt>No. Nota</dt><dd>{{ $purchase->invoice_number ?: '-' }}</dd></dl>
    <table class="table table-bordered"><thead><tr><th>Produk</th><th>Qty</th><th>HPP</th><th>Batch</th><th>Subtotal</th></tr></thead><tbody>@foreach($purchase->items as $item)<tr><td>{{ $item->product?->code }} — {{ $item->product?->name }}</td><td>{{ $item->qty }}</td><td>@currency($item->harga_beli)</td><td>{{ $item->batch_number }}</td><td>@currency($item->subtotal)</td></tr>@endforeach</tbody><tfoot><tr><th colspan="4" class="text-right">Subtotal</th><th>@currency($purchase->subtotal)</th></tr></tfoot></table>
</div><div class="box-footer"><a class="btn btn-default" href="{{ route('outlet-purchases.index') }}">Kembali</a></div></div></section>
@endsection
