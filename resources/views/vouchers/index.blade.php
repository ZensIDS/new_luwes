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
                        <a href="{{ route('voucher.create') }}" class="btn btn-md bg-green">Tambah</a>
                    </div><!-- /.box-header -->
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td>No</td>
                                    <td>Nama</td>
                                    <td>Kode</td>
                                    <td>Outlet</td>
                                    <td>Product scope</td>
                                    <td>Tipe / Nilai</td>
                                    <td>Status</td>
                                    <td>Aksi</td>
                                </tr>
                            </thead>
                            @foreach ($vouchers as $value)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $value->name }}</td>
                                    <td>{{ $value->code }}</td>
                                    <td>{{ $value->outlets->isNotEmpty() ? $value->outlets->pluck('name')->join(', ') : ($value->outlet?->name ?? 'All outlets') }}</td>
                                    <td>{{ $value->products->isNotEmpty() ? $value->products->pluck('name')->join(', ') : ($value->product?->name ?? 'All products') }}<br><small>Min. @currency($value->min_purchase)</small></td>
                                    <td>
                                        @if ($value->type == 'percentage')
                                            {{ $value->value }}%
                                        @else
                                            @currency($value->value)
                                        @endif
                                    </td>
                                    <td>{{ $value->redemptions_count ? 'Used' : ($value->isActive() ? 'Available' : 'Inactive') }}</td>
                                    <td>
                                        <a class="btn btn-warning" href="{{ route('voucher.edit', $value->id) }}">Edit</a>
                                        <a class="btn btn-info" href="{{ route('voucher.show', $value->id) }}">Lihat</a>
                                        <form action="{{ route('voucher.destroy', $value->id) }}" method="post"
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
