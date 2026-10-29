@extends('layouts.master')

@section('title', 'Payment')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            Data Payment
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <a href="{{ route('payment.create') }}" class="btn btn-md bg-green">Tambah</a>
                    </div><!-- /.box-header -->
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td>No</td>
                                    <td>Nama</td>
                                    <td>Nomor Rekening</td>
                                    <td>Nominal Fix</td>
                                    <td>Biaya (%)</td>
                                    <td>Aksi</td>
                                </tr>
                            </thead>
                            @foreach ($payments as $value)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $value->name }}</td>
                                    <td>{{ $value->bank_number }}</td>
                                    <td>{!! $value->is_fixed_amount ? '<span class="label label-success">Ya</span>' : '<span class="text-muted">-</span>' !!}</td>
                                    <td>{{ (float) $value->charge_percent > 0 ? rtrim(rtrim(number_format($value->charge_percent, 2, ',', '.'), '0'), ',') . '%' : '-' }}</td>
                                    <td>
                                        <a class="btn btn-warning" href="{{ route('payment.edit', $value->id) }}">Edit</a>
                                        <form action="{{ route('payment.destroy', $value->id) }}" method="post"
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