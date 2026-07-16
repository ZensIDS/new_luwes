@extends('layouts.master')

@section('title', 'Tambah Category')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah Category</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('category.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="box-body">
                            {{-- <div class="form-group"> --}}
                            {{-- <label>Outlet</label> --}}
                            {{-- <select class="form-control select2" name="outlet_id" data-placeholder="Pilih Outlet" --}}
                            {{-- style="width: 100%;"> --}}
                            {{-- <option value="" selected disabled>Pilih Outlet</option> --}}
                            {{-- @foreach ($outlets as $outlet) --}}
                            {{-- <option value="{{ $outlet->id }}" --}}
                            {{-- {{ old('outlet_id') == $outlet->id ? 'selected' : '' }}> --}}
                            {{-- {{ $outlet->name }} --}}
                            {{-- </option> --}}
                            {{-- @endforeach --}}
                            {{-- </select> --}}
                            {{-- @error('outlet_id') --}}
                            {{-- <div class="invalid-feedback text-danger"> --}}
                            {{-- {{ $message }} --}}
                            {{-- </div> --}}
                            {{-- @enderror --}}
                            {{-- </div> --}}
                            <div class="form-group">
                                <label for="">Nama Category</label>
                                <input type="text" class="form-control" name="name" value="{{ old('name') }}"
                                    placeholder="Masukkan Nama Category">
                                @error('name')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            {{-- <div class="form-group"> --}}
                            {{-- <label>Tipe</label> --}}
                            {{-- <select class="form-control select2" name="type" data-placeholder="Pilih Tipe" --}}
                            {{-- style="width: 100%;"> --}}
                            {{-- <option value="" selected disabled>Pilih Tipe</option> --}}
                            {{-- <option value="product">Product</option> --}}
                            {{-- <option value="pengeluaran">Pengeluaran</option> --}}
                            {{-- </select> --}}
                            {{-- @error('type') --}}
                            {{-- <div class="invalid-feedback text-danger"> --}}
                            {{-- {{ $message }} --}}
                            {{-- </div> --}}
                            {{-- @enderror --}}
                            {{-- </div> --}}
                            <input type="hidden" name="type" value="{{ $type }}">
                        </div><!-- /.box-body -->

                        <div class="box-footer">
                            <a href="{{ $type == 'product' ? route('category.product.index') : route('category.pengeluaran.index') }}"
                                class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div><!-- /.box -->
            </div>
        </div>
    </section>
@endsection
