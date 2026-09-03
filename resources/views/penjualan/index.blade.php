@extends('layouts.master')

@section('title', 'Penjualan')

@section('container')
    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <a href="{{ route('penjualan.create') }}" class="btn btn-primary"><i class="fa fa-plus"></i> Buat Penjualan Baru</a>
                        <a href="{{ route('owner-stocks.index') }}" class="btn btn-default"><i class="fa fa-cubes"></i> Lihat Stock Toko</a>
                    </div>
                    <div class="box-body table-responsive text-nowrap">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode Invoice</th>
                                    {{-- <td>Customer</td> --}}
                                    {{-- <td>Kas/Metode Pembayaran</td> --}}
                                    <th>Outlet</th>
                                    <th>Kasir</th>
                                    <th>Salesman</th>
                                    <th>Detail</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($penjualan as $value)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $value->code }}</td>
                                    {{-- <td>{{ $value->customer->name }}</td> --}}
                                    {{-- <td>{{ $value->kas?->name ?? $value->transaction?->payment?->name }}</td> --}}
                                    <td>{{ $value->outlet->name ?? '___customer' }}</td>
                                    <td>{{ $value->kasir->name ?? '___customer' }}</td>
                                    <td>{{ $value->salesman?->name }}</td>
                                    <td>
                                        <div class="table-responsive text-nowrap">
                                            <table class="table table-sm table-bordered">
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Banyak</th>
                                                    <th>Harga Jual</th>
                                                    <th>Sub total</th>
                                                </tr>
                                                @php $totalCost = 0; @endphp
                                                @foreach ($value->items as $item)
                                                    <tr>
                                                        <td>{{ $item->serial_number ? $item->serial_number : $item->product?->code }} - {{ $item->product->name }}</td>
                                                        <td>{{ $item->qty }}</td>
                                                        <td>@currency($item->price)</td>
                                                        <td>@currency($item->qty * $item->price)</td>
                                                    </tr>
                                                @php $totalCost += $item->qty * $item->price; @endphp
                                                @endforeach
                                                <tr>
                                                    <th>Disc Toko : @currency($value->discount_total ?? $value->discount)</th>
                                                    <th>Voucher : @currency($value->voucher_total ?? 0)</th>
                                                    <th colspan="3" class="text-right">Subtotal : @currency($value->subtotal ?? $totalCost)</th>
                                                </tr>
                                                <tr>
                                                    <th colspan="4" class="text-right">Grand Total : @currency($value->grand_total ?? ($totalCost - $value->discount - $value->voucher?->value))</th>
                                                </tr>
                                            </table>
                                        </div>
                                    </td>
                                    <td>
                                        <a class="btn btn-info" href="{{ route('penjualan.show', $value->id) }}">Show</a>
                                        <a class="btn btn-warning" href="{{ route('penjualan.print', $value->id) }}">Print</a>
                                        <form action="{{ route('penjualan.destroy', $value->id) }}" method="post"
                                            style="display: inline;">
                                            @method('delete')
                                            @csrf
                                            <button class="border-0 btn btn-danger"
                                                onclick="return confirm('Are you sure?')">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        @if ($penjualan->isEmpty())
                            <div class="alert alert-info text-center" style="margin-top:15px;margin-bottom:0">
                                Belum ada penjualan. <a href="{{ route('penjualan.create') }}">Buka Kasir POS untuk membuat transaksi pertama.</a>
                            </div>
                        @endif
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection
