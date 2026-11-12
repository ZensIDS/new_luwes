@extends('layouts.master')

@section('title', 'Edit Kresek')

@section('container')
    <section class="content">
        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Edit Kresek</h3></div>
                    <form action="{{ route('kresek.update', $kresek) }}" method="POST">
                        @csrf
                        @if ($kresek ?? null)
                            @method('PUT')
                        @endif
                        <div class="box-body">
                            <div class="form-group">
                                <label for="name">Nama</label>
                                <input id="name" type="text" class="form-control" name="name" value="{{ old('name', $kresek->name ?? '') }}" maxlength="255" placeholder="Contoh: Kantong plastik No 1" required autofocus>
                                @error('name')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group">
                                <label for="price">Harga</label>
                                <div class="input-group">
                                    <span class="input-group-addon">Rp</span>
                                    <input id="price" type="text" inputmode="numeric" class="form-control" name="price" data-currency-input data-currency-decimals="0" autocomplete="off"
                                        value="{{ old('price', isset($kresek) ? (int) $kresek->price : 0) }}" required>
                                </div>
                                <p class="help-block">Boleh 0. Tidak ada stok untuk kresek.</p>
                                @error('price')<div class="text-danger">{{ $message }}</div>@enderror
                            </div>
                            <div class="checkbox">
                                <label>
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $kresek->is_active ?? true) ? 'checked' : '' }}>
                                    <b>Aktif</b> &mdash; tampil di pilihan kresek layar kasir
                                </label>
                            </div>
                        </div>
                        <div class="box-footer">
                            <a href="{{ route('kresek.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection