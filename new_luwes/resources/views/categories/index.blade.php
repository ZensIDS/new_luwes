@extends('layouts.master')

@section('title', 'Category')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            Data Category
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <div class="row">
                            <div class="col-xs-6">
                                <a href="{{ route('category.product.create') }}" class="btn btn-primary btn-sm">
                                    <i class="fa fa-plus"></i> Tambah
                                </a>
                                <a href="{{ $type === 'product' ? route('category.product.export') : route('category.pengeluaran.export') }}" class="btn btn-success btn-sm">
                                    <i class="fa fa-download"></i> Export
                                </a>
                                <a href="{{ route('category.export.template') }}" class="btn btn-default btn-sm">
                                    <i class="fa fa-file-excel-o"></i> Template
                                </a>
                                <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#modalImport">
                                    <i class="fa fa-upload"></i> Import
                                </button>
                            </div>
                        </div>
                    </div><!-- /.box-header -->
                    {{-- Modal Import --}}
                    <div class="modal fade" id="modalImport" tabindex="-1">
                        <div class="modal-dialog modal-sm">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    <h4 class="modal-title">Import Category</h4>
                                </div>
                                <form action="{{ route('category.import') }}" method="POST" enctype="multipart/form-data">
                                    @csrf
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>File Excel</label>
                                            <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
                                            <p class="help-block">
                                                Download <a href="{{ route('category.export.template') }}">template</a> terlebih dahulu.
                                            </p>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-default btn-sm" data-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fa fa-upload"></i> Import
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td>No</td>
                                    <td>Nama</td>
                                    {{-- <td>Tipe</td> --}}
                                    {{-- <td>Name Outlet</td> --}}
                                    <td>Aksi</td>
                                </tr>
                            </thead>
                            @foreach ($categories as $value)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $value->name }}</td>
                                    {{-- <td>{{ $value->type }}</td> --}}
                                    {{-- <td>{{ $value->outlet?->name }}</td> --}}
                                    <td>
                                        <a class="btn btn-warning" href="{{ $type == 'product' ? route('category.product.edit', $value->id) : route('category.pengeluaran.edit', $value->id) }}">Edit</a>
                                        <form action="{{ route('category.destroy', $value->id) }}" method="post"
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
