@extends('layouts.master')

@php
    $isEdit = $income->exists;
    $currentOutletId = old('outlet_id', $income->outlet_id);
@endphp

@section('title', $isEdit ? 'Edit Other Income' : 'Tambah Other Income')

@section('container')
    <section class="content-header">
        <h1>{{ $isEdit ? 'Edit' : 'Tambah' }} Other Income <small>Input manual</small></h1>
    </section>

    <section class="content">
        <div class="box box-primary">
            <form action="{{ $action }}" method="POST">
                @csrf
                @if ($method === 'PUT')
                    @method('PUT')
                @endif

                <div class="box-body">
                    <div class="row">
                        <div class="col-md-6 form-group {{ $errors->has('tanggal') ? 'has-error' : '' }}">
                            <label>Tanggal <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="tanggal"
                                value="{{ old('tanggal', optional($income->tanggal)->format('Y-m-d')) }}">
                            @error('tanggal')<span class="help-block">{{ $message }}</span>@enderror
                        </div>

                        <div class="col-md-6 form-group {{ $errors->has('other_income_category_id') ? 'has-error' : '' }}">
                            <label>Kategori <span class="text-danger">*</span></label>
                            <select class="form-control" name="other_income_category_id">
                                <option value="">Pilih kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}"
                                        {{ (int) old('other_income_category_id', $income->other_income_category_id) === $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}{{ $category->trashed() ? ' (dihapus)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('other_income_category_id')<span class="help-block">{{ $message }}</span>@enderror
                            <span class="help-block">Kategori Listing Product dibuat otomatis oleh sistem dan tidak bisa dipilih di sini.</span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group {{ $errors->has('jumlah') ? 'has-error' : '' }}">
                            <label>Jumlah <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-addon">Rp</span>
                                <input type="text" inputmode="numeric" data-currency-input data-currency-decimals="0"
                                    class="form-control" name="jumlah" placeholder="Masukkan jumlah"
                                    value="{{ old('jumlah', $isEdit ? $income->jumlah + 0 : '') }}">
                            </div>
                            @error('jumlah')<span class="help-block">{{ $message }}</span>@enderror
                        </div>

                        <div class="col-md-6 form-group {{ $errors->has('outlet_id') ? 'has-error' : '' }}">
                            <label>Outlet <small class="text-muted">(opsional)</small></label>
                            @if ($scoped)
                                {{-- staff-outlet / kasir: terkunci ke outlet sendiri (diisi server) --}}
                                <input type="text" class="form-control" disabled
                                    value="{{ $outlets->first()?->name ?? 'Belum ada outlet' }}">
                            @else
                                <select class="form-control" name="outlet_id">
                                    <option value="">Umum (tanpa outlet)</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" {{ (int) $currentOutletId === $outlet->id ? 'selected' : '' }}>
                                            {{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                            @error('outlet_id')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="form-group {{ $errors->has('keterangan') ? 'has-error' : '' }}">
                        <label>Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="3" maxlength="1000"
                            placeholder="Contoh: sewa rak bulan Oktober">{{ old('keterangan', $income->keterangan) }}</textarea>
                        @error('keterangan')<span class="help-block">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="box-footer">
                    <a href="{{ route('other-income.index') }}" class="btn btn-default">Kembali</a>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </section>
@endsection