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
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td colspan="2">Kode Invoice</td>
                                    <td colspan="2">{{ $penjualan->code }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2">Customer</td>
                                        <td colspan="2">{{ $penjualan->customer?->name ?? 'Umum' }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2">Kas/Metode Pembayaran</td>
                                    <td colspan="2">
                                        {{ $penjualan->kas?->name ?? $penjualan->paymentMethod?->name ?? $penjualan->transaction?->payment?->name ?? 'Tunai' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2">Kasir</td>
                                    <td colspan="2">{{ $penjualan->kasir->name ?? '___customer' }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2">Outlet</td>
                                    <td colspan="2">{{ $penjualan->outlet->name ?? '___customer' }}</td>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th>Product</th>
                                    <th>Banyak</th>
                                    <th>Harga Jual</th>
                                    <th>Sub total</th>
                                </tr>
                                @php $totalCost = 0; @endphp
                                @foreach ($penjualan->items as $item)
                                    <tr>
                                        <td>{{ $item->serial_number ? $item->serial_number : $item->product?->code }} - {{ $item->product->name }}</td>
                                        <td>{{ $item->qty }}</td>
                                        <td>@currency($item->price)</td>
                                        <td>@currency($item->qty * $item->price)</td>
                                    </tr>
                                    @php $totalCost += $item->qty * $item->price; @endphp
                                @endforeach
                                @php $grandTotal = $penjualan->grand_total ?? ($totalCost - $penjualan->discount - $penjualan->voucher?->value); @endphp
                                <tr>
                                    <th colspan="4" class="text-sm text-right">Sub Total : @currency($totalCost)</th>
                                </tr>
                                <tr>
                                    <th colspan="4" class="text-sm text-right">Disc Toko : -@currency($penjualan->discount_total ?? $penjualan->discount)</th>
                                </tr>
                                <tr>
                                    <th colspan="4" class="text-sm text-right">Voucher : -@currency($penjualan->voucher_total ?? 0)</th>
                                </tr>
                                @if ($penjualan->vouchers->isNotEmpty())
                                    <tr>
                                        <th colspan="4" class="text-sm text-right">Kode Voucher: {{ $penjualan->vouchers->pluck('code')->join(', ') }}</th>
                                    </tr>
                                @endif
                                <tr>
                                    <th colspan="4" class="text-right">Grand Total : @currency($grandTotal)</th>
                                </tr>
                                {{-- <tr> --}}
                                    {{-- <th colspan="4" class="text-sm text-right">Di Bayar : @currency($penjualan->total)</th> --}}
                                {{-- </tr> --}}
                                <tr>
                                    <th colspan="4" class="text-sm text-right">Dibayar: @currency($penjualan->paid_amount ?? $penjualan->total)</th>
                                </tr>
                                <tr>
                                    <th colspan="4" class="text-sm text-right">Kembalian: @currency($penjualan->change_amount ?? 0)</th>
                                </tr>
                            </tbody>
                        </table>
                    </div><!-- /.box-body -->
                    <div class="box-footer">
                        <a href="{{ route('penjualan.index') }}" class="btn btn-default">Kembali</a>
                    </div>
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection
