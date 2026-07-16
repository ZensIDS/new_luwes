@extends('layouts.master')

@section('title', 'Tambah Customer')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah Customer</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('customer.store') }}" method="POST">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="">Nama</label>
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}"
                                    placeholder="Masukkan Nama">
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
                                <label for="">No Telp</label>
                                <input type="text" class="form-control" name="no_telp" value="{{ old('no_telp') }}"
                                    placeholder="Masukkan No Telp">
                                @error('no_telp')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            {{-- <div class="form-group"> --}}
                            {{-- <label for="">Email</label> --}}
                            {{-- <input type="email" class="form-control" name="email" value="{{ old('email') }}" --}}
                            {{-- placeholder="Masukkan Email"> --}}
                            {{-- @error('email') --}}
                            {{-- <div class="invalid-feedback text-danger"> --}}
                            {{-- {{ $message }} --}}
                            {{-- </div> --}}
                            {{-- @enderror --}}
                            {{-- </div> --}}
                            {{-- <div class="form-group"> --}}
                            {{-- <label for="">Password</label> --}}
                            {{-- <input type="password" class="form-control" name="password" value="{{ old('password') }}" --}}
                            {{-- placeholder="Masukkan Password"> --}}
                            {{-- @error('password') --}}
                            {{-- <div class="invalid-feedback text-danger"> --}}
                            {{-- {{ $message }} --}}
                            {{-- </div> --}}
                            {{-- @enderror --}}
                            {{-- </div> --}}
                            {{-- <div class="form-group"> --}}
                            {{-- <label for="">Confirm Password</label> --}}
                            {{-- <input type="password" class="form-control" name="confirm-password" --}}
                            {{-- value="{{ old('confirm-password') }}" placeholder="Confirm Password"> --}}
                            {{-- @error('confirm-password') --}}
                            {{-- <div class="invalid-feedback text-danger"> --}}
                            {{-- {{ $message }} --}}
                            {{-- </div> --}}
                            {{-- @enderror --}}
                            {{-- </div> --}}
                        </div><!-- /.box-body -->

                        <div class="box-footer">
                            <a href="{{ route('customer.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div><!-- /.box -->
            </div>
        </div>
    </section>
@endsection
