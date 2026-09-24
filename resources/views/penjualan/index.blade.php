@extends('layouts.master')

@section('title', 'Penjualan')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            Data Penjualan
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body table-responsive text-nowrap">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td>No</td>
                                    <td>Kode Invoice</td>
                                    {{-- <td>Customer</td> --}}
                                    {{-- <td>Kas/Metode Pembayaran</td> --}}
                                    <td>Outlet</td>
                                    <td>Kasir</td>
                                    <td>Salesman</td>
                                    <td>Detail</td>
                                    <td>Aksi</td>
                                </tr>
                            </thead>
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
                                                    <th>Diskon : @currency($value->discount)</th>
                                                    <th>Vocuher : @currency($value->voucher?->value)</th>
                                                    <th colspan="3" class="text-right">Total : @currency($totalCost)</th>
                                                </tr>
                                                <tr>
                                                    <th colspan="4" class="text-right">Grand Total : @currency($totalCost - $value->discount - $value->voucher?->value)</th>
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
                        </table>
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection
