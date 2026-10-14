@extends('layouts.master')

@section('title', 'Kategori Other Income')

@section('container')
    <section class="content-header">
        <h1>Kategori Other Income <small>Kelola kategori dan Nominal Listing</small></h1>
    </section>

    <section class="content">
        {{-- Nominal Listing (kategori sistem Listing Product) --}}
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-lock"></i> Listing Product (kategori sistem)</h3>
            </div>
            @if ($listing)
                @php $nominalErrors = $errors->getBag('nominal'); @endphp
                <form action="{{ route('other-income.categories.listing-nominal') }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="box-body">
                        <p class="text-muted">
                            Kategori ini terkunci: nama tidak bisa diubah dan tidak bisa dihapus atau dinonaktifkan.
                            Income Listing lahir otomatis dari produk berstatus Listing (sekali per produk).
                            Mengubah nominal <strong>tidak mengubah income lama</strong>; hanya produk berikutnya yang memakai nominal baru.
                        </p>
                        <div class="row">
                            <div class="col-md-6 form-group {{ $nominalErrors->has('nominal') ? 'has-error' : '' }}">
                                <label>Nominal Listing
                                    @if ($listing->nominal)
                                        <span class="label label-success">Rp {{ number_format($listing->nominal, 0, ',', '.') }}</span>
                                    @else
                                        <span class="label label-danger">Belum diatur</span>
                                    @endif
                                </label>
                                <div class="input-group">
                                    <span class="input-group-addon">Rp</span>
                                    <input type="text" inputmode="numeric" data-currency-input data-currency-decimals="0"
                                        class="form-control" name="nominal" placeholder="Masukkan nominal Listing"
                                        value="{{ old('nominal', $listing->nominal ? $listing->nominal + 0 : '') }}">
                                </div>
                                @if ($nominalErrors->has('nominal'))
                                    <span class="help-block">{{ $nominalErrors->first('nominal') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">Simpan Nominal</button>
                    </div>
                </form>
            @else
                <div class="box-body">
                    <div class="alert alert-danger" style="margin-bottom:0;">
                        Kategori Listing Product belum tersedia. Jalankan <code>php artisan migrate</code> terlebih dahulu.
                    </div>
                </div>
            @endif
        </div>

        {{-- Tambah kategori manual --}}
        @php $createErrors = $errors->getBag('create'); @endphp
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Tambah Kategori</h3>
            </div>
            <form action="{{ route('other-income.categories.store') }}" method="POST" class="form-inline">
                @csrf
                <div class="box-body">
                    <div class="form-group {{ $createErrors->has('name') ? 'has-error' : '' }}">
                        <input type="text" class="form-control" name="name" maxlength="100"
                            value="{{ old('_rename_id') ? '' : old('name') }}" placeholder="Nama kategori, contoh: Sewa">
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fa fa-plus"></i> Tambah</button>
                    @if ($createErrors->has('name'))
                        <span class="text-danger" style="margin-left:8px;">{{ $createErrors->first('name') }}</span>
                    @endif
                </div>
            </form>
        </div>

        {{-- Daftar kategori manual --}}
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title">Kategori Manual</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="width:50px;">No</th>
                            <th>Nama</th>
                            <th style="width:120px;">Jumlah Income</th>
                            <th style="width:110px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            @php $renameErrors = $errors->getBag('rename' . $category->id); @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <form action="{{ route('other-income.categories.update', $category) }}" method="POST" class="form-inline">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="_rename_id" value="{{ $category->id }}">
                                        <div class="form-group {{ $renameErrors->has('name') ? 'has-error' : '' }}">
                                            <input type="text" class="form-control input-sm" name="name" maxlength="100"
                                                value="{{ (int) old('_rename_id') === $category->id ? old('name') : $category->name }}">
                                        </div>
                                        <button type="submit" class="btn btn-default btn-sm">Simpan nama</button>
                                        @if ($renameErrors->has('name'))
                                            <span class="text-danger" style="margin-left:6px;">{{ $renameErrors->first('name') }}</span>
                                        @endif
                                    </form>
                                </td>
                                <td>{{ number_format($category->incomes_count, 0, ',', '.') }}</td>
                                <td>
                                    <form action="{{ route('other-income.categories.destroy', $category) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm"
                                            onclick="return confirm('Hapus kategori ini? Income lama tetap tampil dengan nama kategorinya.')">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted">Belum ada kategori manual.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection