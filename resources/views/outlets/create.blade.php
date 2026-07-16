@extends('layouts.master')

@section('title', 'Tambah Outlet')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah Outlet</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('outlet.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="">Nama Outlet</label>
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}"
                                    placeholder="Masukkan Nama Outlet">
                                @error('name')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="">Jenis Outlet</label>
                                <input type="text" class="form-control" name="jenis_outlet" value="{{ old('jenis_outlet') }}"
                                    placeholder="Masukkan Jenis Outlet (Toko / Beauty)">
                                @error('jenis_outlet')
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
                            {{-- <div class="form-group"> --}}
                                {{-- <label for="">NPWP</label> --}}
                                {{-- <input type="text" class="form-control" name="npwp" value="{{ old('npwp') }}" --}}
                                    {{-- placeholder="Masukkan NPWP"> --}}
                                {{-- @error('npwp') --}}
                                    {{-- <div class="invalid-feedback text-danger"> --}}
                                        {{-- {{ $message }} --}}
                                    {{-- </div> --}}
                                {{-- @enderror --}}
                            {{-- </div> --}}
                            {{-- <div class="form-group"> --}}
                                {{-- <label for="">Slogan</label> --}}
                                {{-- <input type="text" class="form-control" name="slogan" value="{{ old('slogan') }}" --}}
                                    {{-- placeholder="Masukkan Slogan"> --}}
                                {{-- @error('slogan') --}}
                                    {{-- <div class="invalid-feedback text-danger"> --}}
                                        {{-- {{ $message }} --}}
                                    {{-- </div> --}}
                                {{-- @enderror --}}
                            {{-- </div> --}}
                            {{-- <div class="form-group"> --}}
                                {{-- <label for="">Deskripsi</label> --}}
                                {{-- <input type="text" class="form-control" name="desc" value="{{ old('desc') }}" --}}
                                    {{-- placeholder="Masukkan Deskripsi"> --}}
                                {{-- @error('desc') --}}
                                    {{-- <div class="invalid-feedback text-danger"> --}}
                                        {{-- {{ $message }} --}}
                                    {{-- </div> --}}
                                {{-- @enderror --}}
                            {{-- </div> --}}
                            {{-- <div class="form-group"> --}}
                                {{-- <label for="">Footer</label> --}}
                                {{-- <input type="text" class="form-control" name="footer" value="{{ old('footer') }}" --}}
                                    {{-- placeholder="Masukkan Footer"> --}}
                                {{-- @error('footer') --}}
                                    {{-- <div class="invalid-feedback text-danger"> --}}
                                        {{-- {{ $message }} --}}
                                    {{-- </div> --}}
                                {{-- @enderror --}}
                            {{-- </div> --}}
                            {{-- <div class="form-group"> --}}
                                {{-- <label for="">Logo</label> --}}
                                {{-- <input type="file" class="form-control" name="logo" value="{{ old('logo') }}" --}}
                                    {{-- placeholder="Masukkan logo"> --}}
                                {{-- @error('logo') --}}
                                    {{-- <div class="invalid-feedback text-danger"> --}}
                                        {{-- {{ $message }} --}}
                                    {{-- </div> --}}
                                {{-- @enderror --}}
                            {{-- </div> --}}
                            <div class="form-group">
                                <label for="">Deskripsi</label>
                                <input type="text" class="form-control" name="desc"
                                    value="{{ old('desc') }}" placeholder="Masukkan Deskripsi">
                                @error('desc')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                        </div><!-- /.box-body -->

                        <div class="box-footer">
                            <a href="{{ route('outlet.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div><!-- /.box -->
            </div>
        </div>
    </section>
@endsection
