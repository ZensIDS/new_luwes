@extends('layouts.master')

@php
    $brandType = old('disc_brand_type', $price->disc_brand_type ?: 'nominal');
    $additionalType = old('disc_tambahan_type', $price->disc_tambahan_type ?: 'nominal');
    $marginType = old('margin_type', $price->margin_type ?: 'percentage');
    $storeDiscType = old('disc_toko_type', $price->disc_toko_type ?: 'nominal');
    $taxType = old('pajak_type', $price->pajak_type ?: 'percentage');
    $adjustmentType = old('outlet_adjustment_type', $price->outlet_adjustment_type ?: 'nominal');
    // Panel "Daftarkan Produk Baru" otomatis terbuka lagi bila validasinya gagal.
    $openNewProduct = $errors->newProduct->any() && old('_new_product');
@endphp

@section('title', $price->exists ? 'Edit Aturan Harga Jual' : 'Tambah Aturan Harga Jual')

@section('container')
    <section class="content-header">
        <h1>
            <i class="fa fa-money"></i>
            {{ $price->exists ? 'Edit' : 'Tambah' }} Aturan Harga Jual POS
            <small>Harga per outlet dan produk</small>
        </h1>
    </section>

    <section class="content">
        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Periksa kembali input berikut:</strong>
                <ul style="margin-bottom:0;margin-top:8px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! $price->exists && isset($categories))
            <div style="margin-bottom:15px;">
                <button type="button" class="btn btn-primary" id="toggle-new-product-form"
                    aria-controls="new-product-panel" aria-expanded="{{ $openNewProduct ? 'true' : 'false' }}">
                    <i class="fa fa-plus-square"></i> Daftarkan Produk Baru
                </button>
                <span class="text-muted" style="margin-left:8px;">Barangnya belum ada di daftar produk? Daftarkan dulu di sini.</span>
            </div>
        {{-- Form daftar produk baru: tersembunyi sampai tombol "Daftarkan Produk Baru" diklik (bukan popup) --}}
        <div class="box box-success" id="new-product-panel" style="{{ $openNewProduct ? '' : 'display:none;' }}">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-plus-square"></i> Daftarkan Produk Baru</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" id="close-new-product-form" title="Tutup">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
            <form action="{{ route('outlet-prices.products.store') }}" method="POST" id="new-product-form" autocomplete="off">
                @csrf
                <input type="hidden" name="_new_product" value="1">
                <input type="hidden" name="outlet_id" id="new-product-outlet-id" value="">
                <div class="box-body">
                    @if ($openNewProduct)
                        <div class="alert alert-danger">
                            <strong>Produk belum tersimpan. Periksa kembali input berikut:</strong>
                            <ul style="margin-bottom:0;margin-top:8px">
                                @foreach ($errors->newProduct->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <p class="text-muted">
                        Cari dulu lewat pilihan <em>Produk</em> di form harga di bawah. Daftarkan di sini hanya jika
                        barangnya benar-benar belum ada. Barcode tidak boleh sama dengan produk lain.
                    </p>

                    <div class="row">
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('name') ? 'has-error' : '' }}">
                            <label>Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="Masukkan Nama">
                            @error('name', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('code') ? 'has-error' : '' }}">
                            <label>Barcode <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="product-code-input" autocomplete="off" name="code" value="{{ old('code') }}" placeholder="Scan / masukkan barcode">
                            @error('code', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group {{ $errors->newProduct->has('category_id') ? 'has-error' : '' }}">
                            <label>Category <span class="text-danger">*</span></label>
                            <select class="form-control np-select2" name="category_id" data-placeholder="Pilih Category" style="width:100%;">
                                <option value=""></option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-4 form-group {{ $errors->newProduct->has('satuan_besar') ? 'has-error' : '' }}">
                            <label>Satuan Besar</label>
                            <input type="text" class="form-control" id="np-satuan-besar" name="satuan_besar"
                                value="{{ old('satuan_besar') }}" placeholder="karton / box / lusin">
                            @error('satuan_besar', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-4 form-group {{ $errors->newProduct->has('satuan') ? 'has-error' : '' }}">
                            <label>Satuan Kecil</label>
                            <input type="text" class="form-control" id="np-satuan" name="satuan"
                                value="{{ old('satuan') }}" placeholder="Contoh: Pcs, Kg">
                            @error('satuan', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group {{ $errors->newProduct->has('konversi_qty') ? 'has-error' : '' }}">
                            <label>Isi Konversi</label>
                            <input type="text" class="form-control np-integer-only" id="np-konversi" name="konversi_qty"
                                value="{{ old('konversi_qty') }}" inputmode="numeric" placeholder="12">
                            <span class="help-block" id="np-konversi-hint">Contoh: 1 karton = 12 pcs, isi 12.</span>
                            @error('konversi_qty', 'newProduct')<span class="help-block text-danger">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-5 form-group {{ $errors->newProduct->has('harga_beli') ? 'has-error' : '' }}">
                            <label>Harga Beli <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-addon">Rp</span>
                                <input type="text" inputmode="numeric" data-currency-input data-currency-decimals="2"
                                    class="form-control" id="np-harga-beli" name="harga_beli"
                                    value="{{ old('harga_beli', 0) }}" placeholder="Harga sesuai nota supplier">
                            </div>
                            @error('harga_beli', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-3 form-group {{ $errors->newProduct->has('harga_beli_per') ? 'has-error' : '' }}">
                            <label>Harga beli per</label>
                            <select class="form-control" id="np-harga-per" name="harga_beli_per">
                                <option value="besar" {{ old('harga_beli_per', 'besar') === 'besar' ? 'selected' : '' }}>Satuan Besar</option>
                                <option value="kecil" {{ old('harga_beli_per', 'besar') === 'kecil' ? 'selected' : '' }}>Satuan Kecil</option>
                            </select>
                            @error('harga_beli_per', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-12">
                            <p class="help-block" id="np-hpp-hint" style="margin-top:0;"></p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 form-group {{ $errors->newProduct->has('supplier_ids') || $errors->newProduct->has('supplier_ids.*') ? 'has-error' : '' }}">
                            <label>Supplier</label>
                            <select class="form-control np-select2" name="supplier_ids[]" multiple data-placeholder="Pilih Supplier" style="width:100%;">
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}"
                                        {{ in_array($supplier->id, old('supplier_ids', [])) ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                            @error('supplier_ids', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                            @error('supplier_ids.*', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- Status Produk: di form utama (bukan di "field lainnya"), tepat setelah Supplier --}}
                    @php $npStatus = old('status_produk', 'sudah'); @endphp
                    <div class="row">
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('status_produk') ? 'has-error' : '' }}">
                            <label>Status Produk</label>
                            <select class="form-control" name="status_produk" id="np-status-produk">
                                @foreach ($statusProdukOptions as $statusValue => $statusLabel)
                                    <option value="{{ $statusValue }}" {{ $npStatus === $statusValue ? 'selected' : '' }}>
                                        {{ $statusLabel }}</option>
                                @endforeach
                            </select>
                            @error('status_produk', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('status_produk_note') ? 'has-error' : '' }}"
                            id="np-status-note-group" style="{{ $npStatus === 'tambahan_diskon' ? '' : 'display:none;' }}">
                            <label>Catatan Tambahan Diskon <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="status_produk_note" id="np-status-note"
                                value="{{ old('status_produk_note') }}" maxlength="255"
                                placeholder="Contoh: tambah 10.000 atau diskon 5%">
                            @error('status_produk_note', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        @include('products._listing-info', [
                            'select' => '#np-status-produk',
                            'mode' => 'trigger',
                            'nominal' => $listingNominal ?? null,
                        ])
                    </div>

                    <a href="#" id="toggle-new-product-extra"><i class="fa fa-caret-right"></i> Tampilkan field lainnya (opsional)</a>
                    <div id="new-product-extra" class="row" style="display:none; margin-top:10px;">
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('brand') ? 'has-error' : '' }}">
                            <label>Brand</label>
                            <input type="text" class="form-control" name="brand" value="{{ old('brand') }}" placeholder="Masukkan Brand">
                            @error('brand', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('min_stock') ? 'has-error' : '' }}">
                            <label>Minimum Stock</label>
                            <input type="text" class="form-control np-integer-only" name="min_stock" value="{{ old('min_stock', 0) }}" placeholder="0">
                            @error('min_stock', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('warna') ? 'has-error' : '' }}">
                            <label>Warna</label>
                            <input type="text" class="form-control" name="warna" value="{{ old('warna') }}" placeholder="Masukkan Warna">
                            @error('warna', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('ukuran') ? 'has-error' : '' }}">
                            <label>Ukuran</label>
                            <input type="text" class="form-control" name="ukuran" value="{{ old('ukuran') }}" placeholder="Masukkan Ukuran">
                            @error('ukuran', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('model') ? 'has-error' : '' }}">
                            <label>Model</label>
                            <input type="text" class="form-control" name="model" value="{{ old('model') }}" placeholder="Masukkan Model">
                            @error('model', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('lokasi') ? 'has-error' : '' }}">
                            <label>Lokasi</label>
                            <input type="text" class="form-control" name="lokasi" value="{{ old('lokasi') }}" placeholder="Contoh: Rak A, Gudang 1">
                            @error('lokasi', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="button" class="btn btn-default" id="cancel-new-product-form">Batal</button>
                    <button type="submit" class="btn btn-success" id="submit-new-product-form">
                        <i class="fa fa-save"></i> Simpan &amp; Atur Harga
                    </button>
                </div>
            </form>
        </div>
        @endif

        <form method="POST" action="{{ $action }}" id="price-rule-form">
            @csrf
            @if ($method === 'PUT')
                @method('PUT')
            @endif

            <div class="row">
                <div class="col-md-8">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-cube"></i> Produk dan Outlet</h3>
                            <p class="text-muted" style="margin:6px 0 0">Tentukan aturan harga yang akan digunakan POS untuk kombinasi ini.</p>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label for="outlet_id">Outlet <span class="text-danger">*</span></label>
                                    <select id="outlet_id" name="outlet_id" class="form-control select2 price-select"
                                        data-placeholder="Cari atau pilih outlet" style="width:100%" required
                                        {{ $price->exists ? 'disabled' : '' }}>
                                        <option value=""></option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}" data-type="{{ $outlet->jenis_outlet }}"
                                                {{ old('outlet_id', $price->outlet_id) == $outlet->id ? 'selected' : '' }}>
                                                {{ $outlet->name }}
                                                @if ($outlet->jenis_outlet)<small>({{ $outlet->jenis_outlet }})</small>@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($price->exists)
                                        <input type="hidden" name="outlet_id" value="{{ $price->outlet_id }}">
                                    @endif
                                    @error('outlet_id') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="product_id">Produk <span class="text-danger">*</span></label>
                                    <select id="product_id" name="product_id" class="form-control product-ajax"
                                        data-placeholder="Ketik kode atau nama produk" style="width:100%" required
                                        {{ $price->exists ? 'disabled' : '' }}>
                                        <option value=""></option>
                                        @if ($selectedProduct)
                                            <option value="{{ $selectedProduct->id }}" selected>
                                                {{ $selectedProduct->code }} — {{ $selectedProduct->name }}
                                            </option>
                                        @endif
                                    </select>
                                    @if ($price->exists)
                                        <input type="hidden" name="product_id" value="{{ $price->product_id }}">
                                    @endif
                                    @error('product_id') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="box box-default">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-sliders"></i> Komponen Harga</h3>
                            <p class="text-muted" style="margin:6px 0 0">Gunakan tipe <strong>Rp</strong> untuk nominal tetap atau <strong>%</strong> untuk persentase.</p>
                        </div>
                        <div class="box-body">
                            <div class="price-rule-card price-rule-brand">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-info">1</span> Diskon Reguler</h4>
                                        <p class="text-muted small">Diskon brand. Mengurangi HPP setelah pajak sebelum diskon tambahan dan margin.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_brand_type">Tipe Potongan</label>
                                        <select id="disc_brand_type" name="disc_brand_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $brandType === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $brandType === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_brand_value">Nilai Diskon Reguler</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="disc_brand_type">Rp</span>
                                            <input id="disc_brand_value" name="disc_brand_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="disc_brand_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('disc_brand_value', $price->disc_brand_value ?? 0) }}" placeholder="0">
                                        </div>
                                        @error('disc_brand_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-additional">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-success">2</span> Diskon Tambahan</h4>
                                        <p class="text-muted small">Potongan tambahan setelah Diskon Reguler dan sebelum margin.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_tambahan_type">Tipe Potongan</label>
                                        <select id="disc_tambahan_type" name="disc_tambahan_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $additionalType === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $additionalType === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_tambahan_value">Nilai Diskon Tambahan</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="disc_tambahan_type">Rp</span>
                                            <input id="disc_tambahan_value" name="disc_tambahan_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="disc_tambahan_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('disc_tambahan_value', $price->disc_tambahan_value ?? 0) }}" placeholder="0">
                                        </div>
                                        @error('disc_tambahan_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-margin">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-success">3</span> Margin</h4>
                                        <p class="text-muted small">Keuntungan dihitung dari Harga Dasar setelah dua diskon.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="margin_type">Tipe Margin</label>
                                        <select id="margin_type" name="margin_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $marginType === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $marginType === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="margin_value">Nilai Margin</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="margin_type">%</span>
                                            <input id="margin_value" name="margin_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="margin_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('margin_value', $price->margin_value ?? 0) }}" placeholder="0">
                                        </div>
                                        @error('margin_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-store">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-warning">4</span> Diskon Toko</h4>
                                        <p class="text-muted small">Dipotong dari Harga Aktif untuk mendapatkan Harga Netto.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_toko_type">Tipe Potongan</label>
                                        <select id="disc_toko_type" name="disc_toko_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $storeDiscType === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $storeDiscType === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_toko_value">Nilai Diskon Toko</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="disc_toko_type">Rp</span>
                                            <input id="disc_toko_value" name="disc_toko_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="disc_toko_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('disc_toko_value', $price->disc_toko_value ?? 0) }}" placeholder="0">
                                        </div>
                                        @error('disc_toko_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-8 col-sm-offset-4 form-group">
                                        <label for="target_netto">Atau isi Harga Netto yang diinginkan</label>
                                        <div class="input-group">
                                            <span class="input-group-addon">Rp</span>
                                            <input id="target_netto" type="text" inputmode="numeric" data-currency-input
                                                data-currency-decimals="0" class="form-control" autocomplete="off"
                                                placeholder="Contoh: 5000 (persen diskon toko dihitung otomatis)">
                                        </div>
                                        <p class="help-block" id="target-netto-hint" style="margin-bottom:0;"></p>
                                        <a href="#" id="target-use-nominal" style="display:none;"></a>
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-tax">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-danger">5</span> Pajak</h4>
                                        <p class="text-muted small">Ditambahkan ke HPP sebelum diskon dan margin dihitung.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="pajak_type">Tipe Pajak</label>
                                        <select id="pajak_type" name="pajak_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $taxType === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $taxType === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="pajak_value">Nilai Pajak</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="pajak_type">%</span>
                                            <input id="pajak_value" name="pajak_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="pajak_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('pajak_value', $price->pajak_value ?? 0) }}" placeholder="0">
                                        </div>
                                        @error('pajak_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-adjustment">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0">Penyesuaian Harga Outlet</h4>
                                        <p class="text-muted small">Tambahan harga untuk Beauty atau outlet lain. Default Rp0, otomatis Rp100 untuk outlet tertentu.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="outlet_adjustment_type">Tipe Penyesuaian</label>
                                        <select id="outlet_adjustment_type" name="outlet_adjustment_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $adjustmentType === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $adjustmentType === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="outlet_adjustment_value">Nilai Penyesuaian</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="outlet_adjustment_type">Rp</span>
                                            <input id="outlet_adjustment_value" name="outlet_adjustment_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="outlet_adjustment_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('outlet_adjustment_value', $price->outlet_adjustment_value ?? 0) }}" placeholder="0">
                                        </div>
                                        @error('outlet_adjustment_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="row" style="margin-top:15px">
                                <div class="col-md-6 form-group">
                                    <label for="effective_from">Berlaku Mulai</label>
                                    <input id="effective_from" name="effective_from" type="date" class="form-control"
                                        value="{{ old('effective_from', optional($price->effective_from)->format('Y-m-d')) }}">
                                    @error('effective_from') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="effective_until">Berlaku Sampai</label>
                                    <input id="effective_until" name="effective_until" type="date" class="form-control"
                                        value="{{ old('effective_until', optional($price->effective_until)->format('Y-m-d')) }}">
                                    @error('effective_until') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="form-group" style="margin-top:15px">
                                <label for="note">Catatan</label>
                                <textarea id="note" name="note" rows="3" maxlength="500" class="form-control"
                                    placeholder="Catatan untuk aturan harga ini (opsional, maks. 500 karakter)">{{ old('note', $price->note) }}</textarea>
                                @error('note') <span class="help-block text-danger">{{ $message }}</span> @enderror
                            </div>

                            <label class="price-active-toggle">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $price->exists ? $price->is_active : true) ? 'checked' : '' }}>
                                <span>
                                    <strong>Aktifkan aturan harga</strong>
                                    <small class="text-muted">Aturan aktif akan digunakan saat POS menghitung harga produk.</small>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="box box-success price-preview-box">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-calculator"></i> Preview Perhitungan</h3>
                        </div>
                        <div class="box-body">
                            <div class="form-group">
                                <label for="preview_hpp">HPP Terbaru</label>
                                <div class="input-group">
                                    <span class="input-group-addon">Rp</span>
                                    <input id="preview_hpp" name="hpp" type="text" inputmode="numeric" data-currency-input data-currency-decimals="0" min="0" step="1" class="form-control" value="{{ old('hpp', $previewHpp ?? 0) }}">
                                    <input type="hidden" name="hpp_changed" id="hpp_changed" value="0">
                                </div>
                                <p class="help-block">Diisi otomatis dari HPP terbaru produk di outlet ini. Jika angka ini diubah, HPP terbarunya ikut diperbarui saat aturan harga disimpan.</p>
                            </div>
                            <div class="price-preview-list">
                                <div><span>HPP</span><strong id="preview-hpp">Rp0</strong></div>
                                <div><span>Pajak</span><strong id="preview-tax">Rp0</strong></div>
                                <div><span>HPP Setelah Pajak</span><strong id="preview-after-tax">Rp0</strong></div>
                                <div><span>Diskon Reguler</span><strong id="preview-brand">Rp0</strong></div>
                                <div><span>Harga Akhir</span><strong id="preview-final">Rp0</strong></div>
                                <div><span>Diskon Tambahan</span><strong id="preview-additional">Rp0</strong></div>
                                <div><span>Harga Dasar</span><strong id="preview-base">Rp0</strong></div>
                                <div><span>Margin</span><strong id="preview-margin">Rp0</strong></div>
                                <div><span>Harga Aktif</span><strong id="preview-active">Rp0</strong></div>
                                <div><span>Diskon Toko</span><strong id="preview-store">Rp0</strong></div>
                                <div><span>Penyesuaian Outlet</span><strong id="preview-adjustment">Rp0</strong></div>
                            </div>
                            <div class="price-preview-total">
                                <span>Harga Netto POS</span>
                                <strong id="preview-net">Rp0</strong>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning small">
                        <i class="fa fa-info-circle"></i>
                        Perubahan master harga hanya berlaku untuk transaksi baru. Invoice lama tetap menggunakan snapshot harganya.
                    </div>
                </div>
            </div>

            <div class="box-footer" style="padding-left:0;padding-right:0">
                @if ($price->exists)
                    {{-- Kembali tetap memperbarui updated_at (POST ke form terpisah di bawah, bukan form harga) --}}
                    <button type="submit" form="outlet-price-back-form" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali</button>
                @else
                    <a class="btn btn-default" href="{{ route('outlet-prices.index', array_filter(['outlet_id' => $price->outlet_id ?: request('outlet_id')])) }}"><i class="fa fa-arrow-left"></i> Kembali</a>
                @endif
                <button type="submit" class="btn btn-primary pull-right"><i class="fa fa-save"></i> Simpan Aturan Harga</button>
            </div>
        </form>

        @if ($price->exists)
            <form id="outlet-price-back-form" method="POST" action="{{ route('outlet-prices.touch', $price) }}" style="display:none">
                @csrf
            </form>
        @endif
    </section>
@endsection

@section('page-script')
    @if (! $price->exists && isset($categories))
    {{-- Dipasang sebelum script panel supaya submit duplikat ditahan lebih dulu --}}
    @include('products._barcode-check', ['input' => '#new-product-form #product-code-input', 'url' => route('product.check-barcode'), 'ignoreId' => null])
    <script>
        $(function () {
            var $panel = $('#new-product-panel');
            var $toggleBtn = $('#toggle-new-product-form');
            var select2Ready = false;

            function initNewProductSelect2() {
                if (select2Ready) { return; }
                select2Ready = true;
                $('.np-select2').each(function () {
                    $(this).select2({
                        placeholder: $(this).data('placeholder'),
                        allowClear: true,
                        width: '100%'
                    });
                });
            }

            function openPanel() {
                $panel.slideDown(150, function () {
                    initNewProductSelect2();
                    $panel.find('input[name="name"]').trigger('focus');
                });
                $toggleBtn.attr('aria-expanded', 'true');
            }

            function closePanel() {
                $panel.slideUp(150);
                $toggleBtn.attr('aria-expanded', 'false');
            }

            $toggleBtn.on('click', function () {
                $panel.is(':visible') ? closePanel() : openPanel();
            });
            $('#close-new-product-form, #cancel-new-product-form').on('click', closePanel);

            $('#toggle-new-product-extra').on('click', function (e) {
                e.preventDefault();
                $('#new-product-extra').slideToggle(150);
                $(this).find('i').toggleClass('fa-caret-right fa-caret-down');
            });

            // Hanya angka untuk Isi Konversi & Minimum Stock
            $(document).on('input', '.np-integer-only', function () {
                var cleaned = $(this).val().replace(/[^0-9]/g, '');
                if ($(this).val() !== cleaned) { $(this).val(cleaned); }
            });

            // ---- Harga beli per satuan besar -> HPP per satuan kecil (konversi otomatis) ----
            // Parser angka format Indonesia (sama dengan IndonesianNumber::parse di server)
            function parseId(value) {
                var text = String(value == null ? '' : value).trim().replace(/[^\d,.]/g, '');
                if (!text) { return 0; }
                if (text.indexOf(',') !== -1) {
                    text = text.replace(/\./g, '').replace(',', '.');
                } else if (text.indexOf('.') !== -1) {
                    var parts = text.split('.');
                    var last = parts[parts.length - 1];
                    text = last.length <= 2 ? parts.slice(0, -1).join('') + '.' + last : parts.join('');
                }
                var n = Number(text);
                return isFinite(n) ? n : 0;
            }
            function fmt(n) {
                return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
            }
            function refreshHarga() {
                var besar = $.trim($('#np-satuan-besar').val());
                var kecil = $.trim($('#np-satuan').val());
                var konv = parseId($('#np-konversi').val());
                var harga = parseId($('#np-harga-beli').val());
                var per = $('#np-harga-per').val();
                var $hint = $('#np-hpp-hint');

                $('#np-harga-per option[value="besar"]').text('Satuan Besar' + (besar ? ' (' + besar + ')' : ''));
                $('#np-harga-per option[value="kecil"]').text('Satuan Kecil' + (kecil ? ' (' + kecil + ')' : ''));
                $('#np-konversi-hint').text(besar && konv > 0
                    ? '1 ' + besar + ' = ' + konv + ' ' + (kecil || 'satuan kecil')
                    : 'Contoh: 1 karton = 12 pcs, isi 12.');

                if (per === 'besar') {
                    if (!besar || konv < 1) {
                        $hint.attr('class', 'help-block text-warning')
                            .text('Isi Satuan Besar dan Isi Konversi supaya HPP per satuan kecil bisa dihitung otomatis.');
                    } else if (harga > 0) {
                        $hint.attr('class', 'help-block text-success')
                            .text('HPP per ' + (kecil || 'satuan kecil') + ' = Rp' + fmt(harga / konv)
                                + ' (Rp' + fmt(harga) + ' ÷ ' + fmt(konv) + ')');
                    } else {
                        $hint.attr('class', 'help-block').text('');
                    }
                } else {
                    $hint.attr('class', 'help-block').text(harga > 0
                        ? 'Harga beli disimpan apa adanya sebagai HPP per ' + (kecil || 'satuan kecil') + '.'
                        : '');
                }
            }
            $('#np-satuan-besar, #np-satuan, #np-konversi, #np-harga-beli, #np-harga-per')
                .on('input change keyup', refreshHarga);
            refreshHarga();

            // Catatan Tambahan Diskon: isian langsung (tanpa modal), hanya tampil untuk status Tambahan diskon
            function refreshStatusNote() {
                var isDiskon = $('#np-status-produk').val() === 'tambahan_diskon';
                $('#np-status-note-group').toggle(isDiskon);
                if (!isDiskon) { $('#np-status-note').val(''); }
            }
            $('#np-status-produk').on('change', refreshStatusNote);
            refreshStatusNote();

            // Outlet diambil dari pilihan outlet di form harga; cegah klik ganda saat menyimpan
            $('#new-product-form').on('submit', function () {
                $('#new-product-outlet-id').val($('#outlet_id').val() || '');
                $('#submit-new-product-form').prop('disabled', true);
            });

            // Validasi gagal: panel sudah terbuka dari server
            if ($panel.is(':visible')) {
                initNewProductSelect2();
                @if ($errors->newProduct->hasAny(['brand', 'min_stock', 'warna', 'ukuran', 'model', 'lokasi']))
                    $('#new-product-extra').show();
                    $('#toggle-new-product-extra i').removeClass('fa-caret-right').addClass('fa-caret-down');
                @endif
                $('html, body').animate({ scrollTop: $panel.offset().top - 60 }, 200);
            }
        });
    </script>
    @endif
    <style>
        .price-rule-card {
            border: 1px solid #e5e7eb;
            border-left: 4px solid #3c8dbc;
            border-radius: 4px;
            padding: 16px 14px 4px;
            margin-bottom: 14px;
            background: #fff;
        }
        .price-rule-additional { border-left-color: #605ca8; }
        .price-rule-margin { border-left-color: #00a65a; }
        .price-rule-store { border-left-color: #f39c12; }
        .price-rule-tax { border-left-color: #dd4b39; }
        .price-rule-adjustment { border-left-color: #777; }
        .price-rule-card h4 { font-weight: 600; }
        .price-rule-card .small { line-height: 1.45; }
        .price-active-toggle { display:flex; gap:10px; align-items:flex-start; cursor:pointer; padding:12px 14px; border:1px solid #d2d6de; border-radius:4px; }
        .price-active-toggle input { margin-top:3px; transform:scale(1.2); }
        .price-active-toggle span { display:flex; flex-direction:column; gap:3px; }
        .price-preview-box { position:sticky; top:15px; }
        .price-preview-list > div { display:flex; justify-content:space-between; padding:9px 0; border-bottom:1px solid #f0f0f0; }
        .price-preview-list span { color:#666; }
        .price-preview-total { display:flex; justify-content:space-between; margin-top:14px; padding:14px; background:#00a65a; color:#fff; border-radius:4px; font-size:16px; }
        .price-preview-total strong { font-size:18px; }
        .select2-container { width:100% !important; }
    </style>
    <script>
        $(function () {
            $('.price-select, .price-type').each(function () {
                if ($(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2('destroy');
                }
                $(this).select2({
                    width: '100%',
                    placeholder: $(this).data('placeholder') || 'Pilih tipe',
                    allowClear: !$(this).prop('required'),
                    minimumResultsForSearch: $(this).hasClass('price-type') ? Infinity : 0
                });
            });

            // Produk dicari di server (20 per halaman), bukan dimuat semua ke halaman.
            $('#product_id').select2({
                width: '100%',
                placeholder: $('#product_id').data('placeholder'),
                allowClear: false,
                minimumInputLength: 2,
                ajax: {
                    url: '{{ route('outlet-prices.products.search') }}',
                    dataType: 'json',
                    delay: 300,
                    cache: true,
                    data: function (params) {
                        return { q: params.term, page: params.page || 1 };
                    },
                    processResults: function (data) {
                        return { results: data.results, pagination: data.pagination };
                    }
                },
                language: {
                    inputTooShort: function () { return 'Ketik minimal 2 huruf untuk mencari produk...'; },
                    searching: function () { return 'Mencari...'; },
                    noResults: function () { return 'Produk tidak ditemukan'; },
                    loadingMore: function () { return 'Memuat produk lainnya...'; }
                }
            });

            function money(value) {
                return 'Rp' + Math.round(value || 0).toLocaleString('id-ID');
            }

            function round(value) {
                return Math.round(value || 0);
            }

            function number(id) {
                return window.parseIdNumber ? window.parseIdNumber($(id).val()) : (Number.parseFloat($(id).val()) || 0);
            }

            function discount(base, type, value) {
                const amount = type === 'percentage' ? base * value / 100 : value;
                return Math.min(Math.max(0, base), Math.max(0, amount));
            }

            function addition(base, type, value) {
                return type === 'percentage' ? base * value / 100 : Math.max(0, value);
            }

            // ===== Harga Netto yang diinginkan -> persen Diskon Toko dihitung otomatis =====
            const OUTLET_ADJUSTMENT_DEFAULTS = @json($outletAdjustmentDefaults ?? new \stdClass);
            const IS_CREATE_MODE = @json(! $price->exists);
            let applyingTarget = false;
            let adjustmentAutoFilled = false;
            let targetNominal = null;

            function targetNetto() {
                const raw = $.trim($('#target_netto').val());
                return raw === '' ? null : Math.max(0, round(number('#target_netto')));
            }

            function percentText(n) {
                return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
            }

            function nettoFromPreAdjustment(price) {
                const adjustment = round(addition(price, $('#outlet_adjustment_type').val(), number('#outlet_adjustment_value')));
                return round(price) + adjustment;
            }

            function setTargetHint(kind, text) {
                $('#target-netto-hint').attr('class', 'help-block text-' + kind).text(text);
            }

            function clearTarget() {
                $('#target_netto').val('');
                $('#target-netto-hint').attr('class', 'help-block').text('');
                $('#target-use-nominal').hide();
                targetNominal = null;
            }

            function setStoreDiscount(type, valueText) {
                if ($('#disc_toko_type').val() !== type) {
                    $('#disc_toko_type').val(type).trigger('change.select2');
                    syncPrefixes();
                }
                $('#disc_toko_value').val(valueText);
            }

            // Hitung persen Diskon Toko supaya Harga Netto = target (netto sudah termasuk penyesuaian outlet).
            function applyTargetNetto(hargaAktif) {
                const target = targetNetto();
                const $nominalLink = $('#target-use-nominal').hide();
                targetNominal = null;
                if (target === null || applyingTarget) { return; }
                applyingTarget = true;
                try {
                    if (hargaAktif <= 0) {
                        setStoreDiscount('percentage', '0');
                        setTargetHint('warning', 'Harga Aktif masih Rp0, belum ada yang bisa didiskon. Lengkapi HPP dan margin dulu.');
                        return;
                    }

                    const maxNetto = nettoFromPreAdjustment(hargaAktif);   // netto tanpa diskon toko
                    if (target >= maxNetto) {
                        setStoreDiscount('percentage', '0');
                        setTargetHint(target > maxNetto ? 'warning' : 'success',
                            target > maxNetto
                                ? 'Netto ' + money(target) + ' lebih tinggi dari netto tanpa diskon toko (' + money(maxNetto) + '). Diskon toko diset 0%.'
                                : 'Netto ' + money(target) + ' sama dengan netto tanpa diskon toko, diskon toko 0%.');
                        return;
                    }

                    // Cari harga sebelum penyesuaian (P) yang menghasilkan netto = target.
                    const adjType = $('#outlet_adjustment_type').val();
                    const adjValue = number('#outlet_adjustment_value');
                    const guess = adjType === 'percentage' ? target / (1 + adjValue / 100) : target - adjValue;
                    let bestPrice = Math.min(hargaAktif, Math.max(0, round(guess)));
                    let bestDiff = Math.abs(nettoFromPreAdjustment(bestPrice) - target);
                    for (let d = -3; d <= 3; d++) {
                        const candidate = round(guess) + d;
                        if (candidate < 0 || candidate > hargaAktif) { continue; }
                        const diff = Math.abs(nettoFromPreAdjustment(candidate) - target);
                        if (diff < bestDiff) { bestPrice = candidate; bestDiff = diff; }
                    }
                    const discountAmount = hargaAktif - bestPrice;

                    // Persen disimpan 2 desimal (kolom decimal 15,2): coba sekitar nilai eksak, ambil yang paling pas.
                    const exactPct = discountAmount / hargaAktif * 100;
                    const basePct = Math.round(exactPct * 100) / 100;
                    let pct = basePct;
                    let pctNetto = null;
                    [0, -0.01, 0.01, -0.02, 0.02].forEach(function (delta) {
                        const candidate = Math.min(100, Math.max(0, Math.round((basePct + delta) * 100) / 100));
                        const store = round(hargaAktif * candidate / 100);
                        const netto = nettoFromPreAdjustment(hargaAktif - store);
                        if (pctNetto === null || Math.abs(netto - target) < Math.abs(pctNetto - target)) {
                            pct = candidate;
                            pctNetto = netto;
                        }
                    });

                    setStoreDiscount('percentage', String(pct));
                    const storeAmount = round(hargaAktif * pct / 100);

                    if (pctNetto === target) {
                        setTargetHint('success', 'Diskon Toko = ' + percentText(pct) + '% (' + money(storeAmount)
                            + ' dari Harga Aktif ' + money(hargaAktif) + ') → Netto ' + money(target));
                    } else {
                        targetNominal = discountAmount;
                        setTargetHint('warning', 'Dengan persen 2 desimal, netto jadi ' + money(pctNetto) + ' (selisih '
                            + money(Math.abs(pctNetto - target)) + ' dari target). ');
                        $nominalLink.text('Pakai nominal ' + money(discountAmount) + ' agar netto tepat ' + money(target)).show();
                    }
                } finally {
                    applyingTarget = false;
                }
            }

            $('#target-use-nominal').on('click', function (e) {
                e.preventDefault();
                if (targetNominal === null) { return; }
                const amount = targetNominal;
                clearTarget();
                $('#disc_toko_type').val('nominal').trigger('change');
                $('#disc_toko_value').val(String(amount));
                window.initCurrencyInputs?.($('#disc_toko_value')[0]);
                updatePreview();
            });

            // Isi manual Diskon Toko = user memilih cara sendiri, kalkulator netto dimatikan.
            $('#disc_toko_value').on('input', clearTarget);
            $('#disc_toko_type').on('change', clearTarget);

            // ===== Penyesuaian Outlet otomatis (mis. outlet ID 2 = Rp100) =====
            function applyOutletAdjustmentDefault(initial) {
                if (!IS_CREATE_MODE) { return; }
                const defaultValue = OUTLET_ADJUSTMENT_DEFAULTS[$('#outlet_id').val()];
                const $value = $('#outlet_adjustment_value');

                if (defaultValue !== undefined) {
                    if (initial && number('#outlet_adjustment_value') > 0) { return; } // jangan timpa isian yang sudah ada
                    $('#outlet_adjustment_type').val('nominal').trigger('change.select2');
                    syncPrefixes();
                    $value.val(String(defaultValue));
                    window.initCurrencyInputs?.($value[0]);
                    adjustmentAutoFilled = true;
                } else if (adjustmentAutoFilled) {
                    $value.val('0');
                    window.initCurrencyInputs?.($value[0]);
                    adjustmentAutoFilled = false;
                }
                updatePreview();
            }

            $('#outlet_adjustment_value').on('input', function () { adjustmentAutoFilled = false; });
            $('#outlet_adjustment_type').on('change', function () { adjustmentAutoFilled = false; });
            $('#outlet_id').on('change', function () { applyOutletAdjustmentDefault(false); });

            function updatePreview() {
                const hpp = round(number('#preview_hpp'));
                const tax = round(addition(hpp, $('#pajak_type').val(), number('#pajak_value')));
                const hppSetelahPajak = round(hpp + tax);
                const brand = round(discount(hppSetelahPajak, $('#disc_brand_type').val(), number('#disc_brand_value')));
                const hargaAkhir = Math.max(0, round(hppSetelahPajak - brand));
                const additional = round(discount(hargaAkhir, $('#disc_tambahan_type').val(), number('#disc_tambahan_value')));
                const hargaDasar = Math.max(0, round(hargaAkhir - additional));
                const margin = $('#margin_type').val() === 'percentage'
                    ? round(hargaDasar * number('#margin_value') / 100)
                    : round(number('#margin_value'));
                const hargaAktif = round(hargaDasar + margin);
                applyTargetNetto(hargaAktif);
                const store = round(discount(hargaAktif, $('#disc_toko_type').val(), number('#disc_toko_value')));
                const hargaSebelumPenyesuaian = Math.max(0, round(hargaAktif - store));
                const adjustment = round(addition(hargaSebelumPenyesuaian, $('#outlet_adjustment_type').val(), number('#outlet_adjustment_value')));
                const hargaNetto = round(hargaSebelumPenyesuaian + adjustment);

                $('#preview-hpp').text(money(hpp));
                $('#preview-tax').text(money(tax));
                $('#preview-after-tax').text(money(hppSetelahPajak));
                $('#preview-brand').text(money(brand));
                $('#preview-final').text(money(hargaAkhir));
                $('#preview-additional').text(money(additional));
                $('#preview-base').text(money(hargaDasar));
                $('#preview-margin').text(money(margin));
                $('#preview-active').text(money(hargaAktif));
                $('#preview-store').text(money(store));
                $('#preview-adjustment').text(money(adjustment));
                $('#preview-net').text(money(hargaNetto));
            }

            function syncPrefixes() {
                $('.price-prefix').each(function () {
                    const type = $('#' + $(this).data('for')).val();
                    $(this).text(type === 'percentage' ? '%' : 'Rp');
                });
            }

            function updatePrefixes() {
                syncPrefixes();
                updatePreview();
            }

            $('.price-type').on('change', function () {
                window.initCurrencyInputs?.();
                updatePrefixes();
            });
            $('#price-rule-form input').on('input', updatePreview);

            // HPP terakhir yang dimuat dari server; dibandingkan saat submit
            // untuk menentukan apakah HPP terbaru perlu diperbarui.
            let loadedHpp = Number(@json((float) ($previewHpp ?? 0)));
            $('#price-rule-form').on('submit', function () {
                const changed = Math.round(number('#preview_hpp')) !== Math.round(loadedHpp);
                $('#hpp_changed').val(changed ? '1' : '0');
            });
            $('#outlet_id, #product_id').on('change', function () {
                const outletId = $('#outlet_id').val();
                const productId = $('#product_id').val();
                if (!outletId || !productId) {
                    updatePreview();
                    return;
                }
                $.get('{{ route('outlet-prices.preview-hpp') }}', { outlet_id: outletId, product_id: productId })
                    .done(function (data) {
                        loadedHpp = Number(data.hpp) || 0;
                        $('#preview_hpp').val(data.hpp).trigger('input');
                    });
                updatePreview();
            });
            updatePrefixes();
            applyOutletAdjustmentDefault(true);
        });
    </script>
@endsection