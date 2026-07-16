@extends('layouts.master')

@section('title', 'Refund/Return Penjualan')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            Data Refund/Return Penjualan
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <a href="{{ route('refund.index') }}" class="btn btn-md bg-primary">Kembali</a>
                    </div><!-- /.box-header -->
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td colspan="2">Code</td>
                                    <td colspan="2">{{ $refund->code }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2">No Invoice</td>
                                    <td colspan="2">{{ $refund->penjualan->code }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2">Nama Customer</td>
                                    <td colspan="2">{{ $refund->customer->name }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2">Nama Outlet</td>
                                    <td colspan="2">{{ $refund->outlet->name }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2">Tanggal</td>
                                    <td colspan="2">{{ $refund->tanggal->format('d-M-Y') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="2">Total</td>
                                    <td colspan="2">@currency($refund->total)</td>
                                </tr>
                                <tr>
                                    <td>No</td>
                                    <td>Nama Product</td>
                                    <td>Qty</td>
                                    <td>Alasan</td>
                                </tr>
                            </thead>
                            @foreach ($refund->refundItems as $value)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $value->product->name }}</td>
                                    <td>{{ $value->qty }}</td>
                                    <td>{{ $value->alasan }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection
