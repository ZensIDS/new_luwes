@extends('layouts.master')

@section('title', 'Tambah Slider')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah Slider</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('slider.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label for="">Gambar</label>
                                <input type="file" class="form-control" name="pic" value="{{ old('pic') }}"
                                    placeholder="Masukkan Gambar">
                                @error('pic')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="">Deskripsi</label>
                                <input type="text" class="form-control" name="desc" value="{{ old('desc') }}"
                                    placeholder="Masukkan Deskripsi">
                                @error('desc')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Status</label>
                                <select class="form-control select2" name="status" data-placeholder="Pilih Status"
                                    style="width: 100%;">
                                    <option value="" selected disabled>Pilih Status</option>
                                    <option value="active">Aktif</option>
                                    <option value="non-active">Non Aktif</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label>Tipe</label>
                                <select class="form-control select2" name="type" data-placeholder="Pilih Tipe"
                                    style="width: 100%;">
                                    <option value="" selected disabled>Pilih Tipe</option>
                                    <option value="default">Default</option>
                                    <option value="link">Link</option>
                                </select>
                                @error('type')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                        </div><!-- /.box-body -->

                        <div class="box-footer">
                            <a href="{{ route('slider.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div><!-- /.box -->
            </div>
        </div>
    </section>
@endsection
