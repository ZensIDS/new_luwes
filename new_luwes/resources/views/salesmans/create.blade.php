@extends('layouts.master')

@section('title', 'Tambah Salesman')

@section('container')
<section class="content">
    <div class="row">
        <!-- left column -->
        <div class="col-md-12">
            <!-- general form elements -->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Tambah Salesman</h3>
                </div><!-- /.box-header -->
                <!-- form start -->
                <form action="{{ route('salesman.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="box-body">
                        <div class="form-group">
                            <label for="">Nama Salesman</label>
                            <input type="text" class="form-control" name="name" value="{{ old('name') }}"
                                placeholder="Masukkan Nama Salesman">
                            @error('name')
                            <div class="invalid-feedback text-danger">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="">Alamat</label>
                            <input type="text" class="form-control" name="alamat" value="{{ old('alamat') }}"
                                placeholder="Masukkan Alamat">
                            @error('alamat')
                            <div class="invalid-feedback text-danger">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label for="">Nomor Telp</label>
                            <input type="number" class="form-control" name="no_telp" value="{{ old('no_telp') }}"
                                placeholder="Masukkan Nomor Telp">
                            @error('no_telp')
                            <div class="invalid-feedback text-danger">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                    </div><!-- /.box-body -->

                    <div class="box-footer">
                        <a href="{{ route('salesman.index') }}" class="btn btn-default">Kembali</a>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div><!-- /.box -->
        </div>
    </div>
</section>
@endsection
