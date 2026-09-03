@extends('layouts.master')

@section('title', 'Voucher')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            Data Voucher
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <a href="{{ route('voucher.index') }}" class="btn btn-md bg-primary">Kembali</a>
                    </div><!-- /.box-header -->
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <tr>
                                <td>Nama</td>
                                <td>{{ $voucher->name }}</td>
                            </tr>
                            <tr>
                                <td>Kode</td>
                                <td>{{ $voucher->code }}</td>
                            </tr>
                            @if ($voucher->product)
                                <tr>
                                    <td>Nama Produk</td>
                                    <td>{{ $voucher->product->name }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td>Outlet</td>
                                <td>{{ $voucher->outlet?->name ?? 'Semua outlet' }}</td>
                            </tr>
                            <tr>
                                <td>Tanggal</td>
                                <td>
                                    {{ $voucher->start_at?->format('d F Y H:i') ?? 'Tanpa batas' }} -
                                    {{ $voucher->end_at?->format('d F Y H:i') ?? 'Tanpa batas' }}
                                </td>
                            </tr>
                            <tr>
                                <td>Deskripsi</td>
                                <td>{{ $voucher->desc }}</td>
                            </tr>
                            <tr>
                                <td>Nominal</td>
                                <td>
                                    @if ($voucher->type == 'percentage')
                                        {{ $voucher->value }}%
                                    @else
                                        @currency($voucher->value)
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td>Limit</td>
                                <td>{{ $voucher->limit }}x</td>
                            </tr>
                            <tr>
                                <td>Minimal Pembelian</td>
                                <td>@currency($voucher->min_purchase)</td>
                            </tr>
                            <tr>
                                <td>Maksimal Potongan</td>
                                <td>{{ $voucher->max_discount_amount !== null ? 'Rp ' . number_format($voucher->max_discount_amount, 0, ',', '.') : 'Tanpa batas' }}</td>
                            </tr>
                            <tr>
                                <td>Redemption</td>
                                <td>{{ $voucher->redemptions_count ?? $voucher->redemptions()->count() }}x</td>
                            </tr>
                        </table>
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection
