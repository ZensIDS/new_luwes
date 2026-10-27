@extends('layouts.master')

@php
    // Diskon Tambahan yang disembunyikan sementara (nilai tetap tersimpan & ikut terkirim, hanya tidak tampil).
    // Untuk menampilkan lagi, kosongkan array ini: $hiddenAdditional = [];
    $hiddenAdditional = [3, 4];
    $brandType = old('disc_brand_type', $price->disc_brand_type ?: 'percentage');
    $additionalType = old('disc_tambahan_type', $price->disc_tambahan_type ?: 'percentage');
    $additionalType2 = old('disc_tambahan_2_type', $price->disc_tambahan_2_type ?: 'percentage');
    $additionalType3 = old('disc_tambahan_3_type', $price->disc_tambahan_3_type ?: 'percentage');
    $additionalType4 = old('disc_tambahan_4_type', $price->disc_tambahan_4_type ?: 'percentage');
    $marginType = old('margin_type', $price->margin_type ?: 'percentage');
    $storeDiscType = old('disc_toko_type', $price->disc_toko_type ?: 'percentage');
    $taxType = old('pajak_type', $price->pajak_type ?: 'percentage');
    $adjustmentType = old('outlet_adjustment_type', $price->outlet_adjustment_type ?: 'nominal');
    // Panel "Daftarkan Produk Baru" otomatis terbuka lagi bila validasinya gagal.
    $openNewProduct = ! $price->exists && $errors->newProduct->any() && old('_new_product');
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

        @if (isset($categories))
            @unless ($price->exists)
            <div style="margin-bottom:15px;">
                <button type="button" class="btn btn-primary" id="toggle-new-product-form"
                    aria-controls="new-product-panel" aria-expanded="{{ $openNewProduct ? 'true' : 'false' }}">
                    <i class="fa fa-plus-square"></i> Daftarkan Produk Baru
                </button>
                <span class="text-muted" style="margin-left:8px;">Barangnya belum ada di daftar produk? Daftarkan dulu di sini.</span>
            </div>
            @endunless
            {{-- Pesan hasil Edit Barang (AJAX): tampil di sini karena panel menutup setelah simpan --}}
            <div class="alert alert-success" id="np-flash" role="status" style="display:none;"></div>
        {{-- Form daftar produk baru: tersembunyi sampai tombol "Daftarkan Produk Baru" diklik (bukan popup) --}}
        <div class="box box-success" id="new-product-panel" style="{{ $openNewProduct ? '' : 'display:none;' }}">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-plus-square" id="np-title-icon"></i> <span id="np-title">Daftarkan Produk Baru</span></h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" id="close-new-product-form" title="Tutup">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            </div>
            <form action="{{ route('outlet-prices.products.store') }}" method="POST" id="new-product-form" autocomplete="off">
                @csrf
                <input type="hidden" name="_new_product" value="1">
                {{-- Mode Edit Barang: JS mengaktifkan _method=PUT dan mengganti action ke /outlet-prices/products/{id} --}}
                <input type="hidden" name="_method" value="PUT" id="np-method" disabled>
                <input type="hidden" name="outlet_id" id="new-product-outlet-id" value="">
                <div class="box-body">
                    <div class="alert alert-danger" id="np-ajax-errors" style="display:none;"></div>
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
                    <p class="text-muted np-create-only">
                        Cari dulu lewat pilihan <em>Produk</em> di form harga di bawah. Daftarkan di sini hanya jika
                        barangnya benar-benar belum ada. Barcode tidak boleh sama dengan produk lain.
                    </p>
                    <div class="alert alert-warning np-edit-only">
                        <i class="fa fa-info-circle"></i>
                        Mengubah data barang yang sedang dipilih di form harga. Perubahan langsung tersimpan ke data produk
                        (sama seperti menu Product), sedangkan isian form harga di bawah tidak ikut berubah.
                        Harga beli di sini adalah harga beli produk; outlet yang sudah punya batch stok tetap memakai
                        HPP batch-nya, jadi HPP outlet tersebut tidak ikut berubah.
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group {{ $errors->newProduct->has('name') ? 'has-error' : '' }}">
                            <label>Nama <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="Masukkan Nama" autocomplete="on">
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
                        {{-- Harga beli: isi SALAH SATU, pasangannya terisi otomatis lewat Isi Konversi.
                             harga_beli_per (hidden) = kolom yang terakhir diisi user. --}}
                        <input type="hidden" name="harga_beli_per" id="np-harga-per"
                            value="{{ old('harga_beli_per', 'besar') === 'kecil' ? 'kecil' : 'besar' }}">
                        <div class="col-md-4 form-group {{ $errors->newProduct->has('harga_beli') ? 'has-error' : '' }}">
                            <label>Harga Beli Satuan Kecil <span id="np-harga-kecil-label"></span></label>
                            <div class="input-group">
                                <span class="input-group-addon">Rp</span>
                                <input type="text" inputmode="decimal" data-currency-input data-currency-decimals="2"
                                    class="form-control" id="np-harga-beli" name="harga_beli"
                                    value="{{ old('harga_beli') }}" placeholder="Harga per satuan kecil" autocomplete="off">
                            </div>
                            @error('harga_beli', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-4 form-group {{ $errors->newProduct->has('harga_beli_besar') || $errors->newProduct->has('harga_beli_per') ? 'has-error' : '' }}">
                            <label>Harga Beli Satuan Besar <span id="np-harga-besar-label"></span></label>
                            <div class="input-group">
                                <span class="input-group-addon">Rp</span>
                                <input type="text" inputmode="decimal" data-currency-input data-currency-decimals="2"
                                    class="form-control" id="np-harga-beli-besar" name="harga_beli_besar"
                                    value="{{ old('harga_beli_besar') }}" placeholder="Harga sesuai nota supplier" autocomplete="off">
                            </div>
                            @error('harga_beli_besar', 'newProduct')<span class="help-block">{{ $message }}</span>@enderror
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
                            'wrapClass' => 'col-md-12 np-create-only',
                        ])
                        {{-- Mode edit: keterangan Listing mengikuti kondisi produk (diisi JS dari data produk) --}}
                        <div class="col-md-12 np-listing-edit" id="np-listing-edit-info" style="display:none;">
                            <div class="alert" id="np-listing-edit-alert" style="margin-bottom:15px;"></div>
                        </div>
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
                        <i class="fa fa-save"></i> <span id="np-submit-label">Simpan &amp; Atur Harga</span>
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
                            <div id="existing-rule-alert" class="alert alert-info" style="display:none"></div>
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
                                    @if (isset($categories))
                                        <button type="button" class="btn btn-default btn-xs" id="edit-product-btn" disabled
                                            style="margin-top:6px;" title="Edit data barang yang dipilih">
                                            <i class="fa fa-pencil"></i> Edit Barang
                                        </button>
                                        <span class="text-muted small" id="edit-product-hint" style="margin-left:6px;">Pilih produk dulu untuk mengedit datanya.</span>
                                    @endif
                                </div>
                            </div>

                            <div id="product-unit-info" class="callout callout-info" style="display:none;margin:5px 0 0;padding:10px 15px;">
                                <div class="row">
                                    <div class="col-sm-3 col-xs-6">
                                        <small class="text-muted">HPP Satuan Kecil</small><br>
                                        <strong id="pui-hpp-kecil">-</strong>
                                    </div>
                                    <div class="col-sm-3 col-xs-6">
                                        <small class="text-muted">HPP Satuan Besar</small><br>
                                        <strong id="pui-hpp-besar">-</strong>
                                    </div>
                                    <div class="col-sm-3 col-xs-6">
                                        <small class="text-muted">Satuan Kecil / Besar</small><br>
                                        <strong id="pui-satuan">-</strong>
                                    </div>
                                    <div class="col-sm-3 col-xs-6">
                                        <small class="text-muted">Konversi</small><br>
                                        <strong id="pui-konversi">-</strong>
                                    </div>
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
                                                value="{{ old('disc_brand_value', $price->exists ? ($price->disc_brand_value ?? 0) : '') }}" placeholder="0">
                                        </div>
                                        @error('disc_brand_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-additional">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-success">2</span> Diskon Tambahan 1</h4>
                                        <p class="text-muted small">Potongan tambahan setelah Diskon Reguler.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_tambahan_type">Tipe Potongan</label>
                                        <select id="disc_tambahan_type" name="disc_tambahan_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $additionalType === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $additionalType === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_tambahan_value">Nilai Diskon Tambahan 1</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="disc_tambahan_type">Rp</span>
                                            <input id="disc_tambahan_value" name="disc_tambahan_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="disc_tambahan_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('disc_tambahan_value', $price->exists ? ($price->disc_tambahan_value ?? 0) : '') }}" placeholder="0">
                                        </div>
                                        @error('disc_tambahan_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-additional">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-success">3</span> Diskon Tambahan 2</h4>
                                        <p class="text-muted small">Potongan bertingkat setelah Diskon Tambahan 1.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_tambahan_2_type">Tipe Potongan</label>
                                        <select id="disc_tambahan_2_type" name="disc_tambahan_2_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $additionalType2 === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $additionalType2 === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_tambahan_2_value">Nilai Diskon Tambahan 2</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="disc_tambahan_2_type">Rp</span>
                                            <input id="disc_tambahan_2_value" name="disc_tambahan_2_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="disc_tambahan_2_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('disc_tambahan_2_value', $price->exists ? ($price->disc_tambahan_2_value ?? 0) : '') }}" placeholder="0">
                                        </div>
                                        @error('disc_tambahan_2_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-additional"{{ in_array(3, $hiddenAdditional) ? ' style=display:none' : '' }}>
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-success">4</span> Diskon Tambahan 3</h4>
                                        <p class="text-muted small">Potongan bertingkat setelah Diskon Tambahan 2.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_tambahan_3_type">Tipe Potongan</label>
                                        <select id="disc_tambahan_3_type" name="disc_tambahan_3_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $additionalType3 === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $additionalType3 === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_tambahan_3_value">Nilai Diskon Tambahan 3</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="disc_tambahan_3_type">Rp</span>
                                            <input id="disc_tambahan_3_value" name="disc_tambahan_3_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="disc_tambahan_3_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('disc_tambahan_3_value', $price->exists ? ($price->disc_tambahan_3_value ?? 0) : '') }}" placeholder="0">
                                        </div>
                                        @error('disc_tambahan_3_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-additional"{{ in_array(4, $hiddenAdditional) ? ' style=display:none' : '' }}>
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-success">5</span> Diskon Tambahan 4</h4>
                                        <p class="text-muted small">Potongan bertingkat setelah Diskon Tambahan 3 dan sebelum margin.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_tambahan_4_type">Tipe Potongan</label>
                                        <select id="disc_tambahan_4_type" name="disc_tambahan_4_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $additionalType4 === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $additionalType4 === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_tambahan_4_value">Nilai Diskon Tambahan 4</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="disc_tambahan_4_type">Rp</span>
                                            <input id="disc_tambahan_4_value" name="disc_tambahan_4_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="disc_tambahan_4_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('disc_tambahan_4_value', $price->exists ? ($price->disc_tambahan_4_value ?? 0) : '') }}" placeholder="0">
                                        </div>
                                        @error('disc_tambahan_4_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-margin">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-success">{{ 6 - count($hiddenAdditional) }}</span> Margin</h4>
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
                                                value="{{ old('margin_value', $price->exists ? ($price->margin_value ?? 0) : '') }}" placeholder="0">
                                        </div>
                                        @error('margin_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-store">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-warning">7</span> Diskon Toko</h4>
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
                                                value="{{ old('disc_toko_value', $price->exists ? ($price->disc_toko_value ?? 0) : '') }}" placeholder="0">
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
                                        <h4 style="margin-top:0"><span class="label label-danger">8</span> Pajak</h4>
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
                                                value="{{ old('pajak_value', $price->exists ? ($price->pajak_value ?? 0) : '') }}" placeholder="0">
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
                                                value="{{ old('outlet_adjustment_value', $price->exists ? ($price->outlet_adjustment_value ?? 0) : '') }}" placeholder="0">
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
                                <label for="preview_hpp">HPP Terbaru <span id="hpp-label-unit" class="text-muted"></span></label>
                                <div id="hpp-unit-toggle" class="btn-group btn-group-sm" style="display:none;margin-bottom:8px;width:100%">
                                    <button type="button" class="btn btn-primary active" data-unit="kecil" style="width:50%">Input per <span class="hpp-name-kecil">satuan kecil</span></button>
                                    <button type="button" class="btn btn-default" data-unit="besar" style="width:50%">Input per <span class="hpp-name-besar">satuan besar</span></button>
                                </div>
                                <input type="hidden" name="hpp_unit" id="hpp_unit" value="kecil">
                                <div id="hpp-besar-group" style="display:none;margin-bottom:8px">
                                    <label for="preview_hpp_besar" class="small text-muted" style="font-weight:normal">HPP per <span class="hpp-name-besar">satuan besar</span></label>
                                    <div class="input-group">
                                        <span class="input-group-addon">Rp</span>
                                        <input id="preview_hpp_besar" name="hpp_besar" type="text" inputmode="decimal" data-currency-input data-currency-decimals="2" class="form-control" value="" placeholder="0">
                                    </div>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-addon">Rp</span>
                                    <input id="preview_hpp" name="hpp" type="text" inputmode="decimal" data-currency-input data-currency-decimals="2" min="0" step="0.01" class="form-control" value="{{ old('hpp', $previewHpp ?? 0) }}">
                                    <input type="hidden" name="hpp_changed" id="hpp_changed" value="0">
                                </div>
                                <p class="help-block" id="hpp-unit-hint" style="display:none"></p>
                                <p class="help-block">Diisi otomatis dari HPP terbaru produk. Jika angka ini diubah, HPP terbarunya ikut diperbarui saat aturan harga disimpan. Harga jual selalu dihitung per satuan kecil.</p>
                            </div>
                            <div class="price-preview-list">
                                <div><span>HPP</span><strong id="preview-hpp">Rp0</strong></div>
                                <div><span>Pajak</span><strong id="preview-tax">Rp0</strong></div>
                                <div><span>HPP Setelah Pajak</span><strong id="preview-after-tax">Rp0</strong></div>
                                <div><span>Diskon Reguler</span><strong id="preview-brand">Rp0</strong></div>
                                <div><span>Harga Akhir</span><strong id="preview-final">Rp0</strong></div>
                                <div><span>Diskon Tambahan 1</span><strong id="preview-additional">Rp0</strong></div>
                                <div><span>Diskon Tambahan 2</span><strong id="preview-additional-2">Rp0</strong></div>
                                <div{{ in_array(3, $hiddenAdditional) ? ' style=display:none' : '' }}><span>Diskon Tambahan 3</span><strong id="preview-additional-3">Rp0</strong></div>
                                <div{{ in_array(4, $hiddenAdditional) ? ' style=display:none' : '' }}><span>Diskon Tambahan 4</span><strong id="preview-additional-4">Rp0</strong></div>
                                <div><span>Harga Dasar</span><strong id="preview-base">Rp0</strong></div>
                                <div><span>Margin</span><strong id="preview-margin">Rp0</strong></div>
                                <div><span>Harga Aktif</span><strong id="preview-active">Rp0</strong></div>
                                <div><span>Diskon Toko</span><strong id="preview-store">Rp0</strong></div>
                                <div><span>Penyesuaian Outlet</span><strong id="preview-adjustment">Rp0</strong></div>
                                <div><span>Harga Sebelum Pembulatan</span><strong id="preview-pre-round">Rp0</strong></div>
                                <div><span>Pembulatan (ke atas Rp100)</span><strong id="preview-rounding">Rp0</strong></div>
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
                    @php
                        $backOutletId = $price->outlet_id ?: request('outlet_id');
                        $backSearch = (string) session('outlet_prices.last_search', '');
                        $backParams = array_filter(['outlet_id' => $backOutletId]);
                        if ($backOutletId && $backSearch !== '' && (int) session('outlet_prices.last_outlet_id', 0) === (int) $backOutletId) {
                            $backParams['search'] = $backSearch;
                        }
                    @endphp
                    <a class="btn btn-default" href="{{ route('outlet-prices.index', $backParams) }}"><i class="fa fa-arrow-left"></i> Kembali</a>
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
    @if (isset($categories))
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

            function closePanel(done) {
                $panel.slideUp(150, function () {
                    if (typeof done === 'function') { done(); }
                });
                $toggleBtn.attr('aria-expanded', 'false');
            }

            // Edit Barang memakai panel yang sama: tombol "Daftarkan Produk Baru" selalu membuka mode tambah yang bersih.
            $toggleBtn.on('click', function () {
                if (isEditMode()) {
                    enterCreateMode();
                    openPanel();
                    return;
                }
                $panel.is(':visible') ? closePanel() : openPanel();
            });
            // Tutup / batal saat mode edit: kembali ke mode tambah (form dikosongkan) setelah panel menutup.
            $('#close-new-product-form, #cancel-new-product-form').on('click', function () {
                closePanel(function () { if (isEditMode()) { enterCreateMode(); } });
            });

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
            function round2(n) { return Math.round(n * 100) / 100; }
            function setHarga($input, n) {
                // Tanpa trigger event, supaya tidak memicu perhitungan berulang.
                $input.val(n > 0 ? fmt(round2(n)) : '');
            }
            // Isi harga BESAR -> kecil = besar / konversi. Isi harga KECIL -> besar = kecil x konversi.
            function refreshHarga() {
                var besar = $.trim($('#np-satuan-besar').val());
                var kecil = $.trim($('#np-satuan').val());
                var konv = parseId($('#np-konversi').val());
                var per = $('#np-harga-per').val() === 'kecil' ? 'kecil' : 'besar';
                var $hint = $('#np-hpp-hint');
                var kecilText = kecil || 'satuan kecil';

                $('#np-harga-kecil-label').text(kecil ? '(per ' + kecil + ')' : '');
                $('#np-harga-besar-label').text(besar ? '(per ' + besar + ')' : '');
                $('#np-konversi-hint').text(besar && konv > 0
                    ? '1 ' + besar + ' = ' + konv + ' ' + kecilText
                    : 'Contoh: 1 karton = 12 pcs, isi 12.');

                if (per === 'besar') {
                    var hargaBesar = parseId($('#np-harga-beli-besar').val());
                    if (konv < 1) {
                        $hint.attr('class', 'help-block text-warning')
                            .text('Isi Satuan Besar dan Isi Konversi supaya HPP per satuan kecil bisa dihitung otomatis.');
                        return;
                    }
                    setHarga($('#np-harga-beli'), hargaBesar / konv);
                    $hint.attr('class', hargaBesar > 0 ? 'help-block text-success' : 'help-block')
                        .text(hargaBesar > 0
                            ? 'HPP per ' + kecilText + ' = Rp' + fmt(round2(hargaBesar / konv))
                                + ' (Rp' + fmt(hargaBesar) + ' ÷ ' + fmt(konv) + ')'
                            : '');
                } else {
                    var hargaKecil = parseId($('#np-harga-beli').val());
                    if (konv >= 1) {
                        setHarga($('#np-harga-beli-besar'), hargaKecil * konv);
                        $hint.attr('class', hargaKecil > 0 ? 'help-block text-muted' : 'help-block')
                            .text(hargaKecil > 0
                                ? 'Harga satuan besar terisi otomatis: Rp' + fmt(round2(hargaKecil)) + ' × ' + fmt(konv) + '.'
                                : '');
                    } else {
                        $('#np-harga-beli-besar').val('');
                        $hint.attr('class', 'help-block text-muted')
                            .text(hargaKecil > 0
                                ? 'Harga satuan besar opsional, terisi otomatis bila Satuan Besar dan Isi Konversi diisi.'
                                : '');
                    }
                }
            }
            $('#np-harga-beli').on('input change keyup', function () { $('#np-harga-per').val('kecil'); refreshHarga(); });
            $('#np-harga-beli-besar').on('input change keyup', function () { $('#np-harga-per').val('besar'); refreshHarga(); });
            $('#np-satuan-besar, #np-satuan, #np-konversi').on('input change keyup', refreshHarga);
            refreshHarga();

            // Catatan Tambahan Diskon: isian langsung (tanpa modal), hanya tampil untuk status Tambahan diskon
            function refreshStatusNote() {
                var isDiskon = $('#np-status-produk').val() === 'tambahan_diskon';
                $('#np-status-note-group').toggle(isDiskon);
                if (!isDiskon) { $('#np-status-note').val(''); }
            }
            $('#np-status-produk').on('change', refreshStatusNote);
            refreshStatusNote();

            // ======== Mode "Edit Barang" (panel yang sama dengan Daftarkan Produk Baru) ========
            var productsBase = @json(route('outlet-prices.products.store')); // /outlet-prices/products
            var editState = { id: null, listingMode: 'closed', listingNominal: null };
            var flashTimer = null;
            var $codeInput = $('#product-code-input');
            var $npForm = $('#new-product-form');

            // function declaration: sudah dipakai handler di atas sebelum baris ini dijalankan (hoisting)
            function isEditMode() { return editState.id !== null; }

            function showFlash(type, message) {
                var $flash = $('#np-flash');
                $flash.attr('class', 'alert alert-' + type).text(message).show();
                clearTimeout(flashTimer);
                flashTimer = setTimeout(function () { $flash.fadeOut(200); }, 7000);
            }

            function clearAjaxErrors() {
                $('#np-ajax-errors').hide().empty();
                $npForm.find('.form-group.has-error').removeClass('has-error');
            }

            function showAjaxErrors(xhr) {
                var messages = [];
                var errors = xhr.responseJSON && xhr.responseJSON.errors;
                if (xhr.status === 422 && errors) {
                    Object.keys(errors).forEach(function (key) {
                        var base = key.split('.')[0];
                        $npForm.find('[name="' + base + '"], [name="' + base + '[]"]').first()
                            .closest('.form-group').addClass('has-error');
                        errors[key].forEach(function (m) { if (messages.indexOf(m) === -1) { messages.push(m); } });
                    });
                } else if (xhr.status === 419) {
                    messages.push('Sesi habis. Muat ulang halaman lalu coba simpan lagi (isian form harga akan hilang bila dimuat ulang).');
                } else if (xhr.status === 403) {
                    messages.push('Anda tidak punya akses untuk mengedit barang.');
                } else {
                    messages.push('Barang gagal disimpan. Coba lagi.');
                }
                var $list = $('<ul style="margin-bottom:0;margin-top:8px"></ul>');
                messages.forEach(function (m) { $list.append($('<li>').text(m)); });
                $('#np-ajax-errors').empty()
                    .append($('<strong>').text('Barang belum tersimpan. Periksa kembali input berikut:'))
                    .append($list).show();
                $('html, body').animate({ scrollTop: $panel.offset().top - 60 }, 200);
            }

            function setStatusProduk(value) {
                // Event native (bukan jQuery trigger): keterangan Listing di partial memakai addEventListener.
                var el = document.getElementById('np-status-produk');
                el.value = value || 'sudah';
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function setExtraOpen(open) {
                $('#new-product-extra').toggle(open);
                $('#toggle-new-product-extra i').toggleClass('fa-caret-down', open).toggleClass('fa-caret-right', !open);
            }

            // Keterangan Listing mode edit, mengikuti kondisi produk (sama dengan halaman edit Product)
            function renderListingEditInfo() {
                var $box = $('#np-listing-edit-info');
                if (!isEditMode() || $('#np-status-produk').val() !== 'listing') { $box.hide(); return; }

                var $alert = $('#np-listing-edit-alert').empty();
                var cls = 'alert-warning';
                var html;
                if (editState.listingMode === 'trigger' && editState.listingNominal) {
                    cls = 'alert-info';
                    html = 'Produk ini akan mencatat Pendapatan Lain Listing sebesar <strong>Rp '
                        + Number(editState.listingNominal).toLocaleString('id-ID', { maximumFractionDigits: 0 })
                        + '</strong> (sekali saja).';
                } else if (editState.listingMode === 'trigger') {
                    cls = 'alert-danger';
                    html = 'Nominal Listing belum diatur, sehingga produk <strong>tidak bisa disimpan</strong> dengan status Listing. '
                        + 'Atur dulu di Pendapatan Lain &gt; Kategori.';
                } else if (editState.listingMode === 'recorded') {
                    html = 'Income Listing produk ini sudah tercatat sebelumnya, jadi tidak akan tercatat lagi.';
                } else {
                    html = 'Produk ini sudah tidak memicu Pendapatan Lain (hanya saat dibuat atau disimpan pertama kali), '
                        + 'jadi mengubah status ke Listing tidak mencatat income.';
                }
                $alert.attr('class', 'alert ' + cls).html('<i class="fa fa-info-circle"></i> ' + html);
                $box.show();
            }
            $('#np-status-produk').on('change', renderListingEditInfo);

            function resetNewProductForm() {
                $npForm.find('input[name="name"], #product-code-input, #np-satuan-besar, #np-satuan, #np-konversi, '
                    + '#np-harga-beli, #np-harga-beli-besar, #np-status-note, input[name="brand"], '
                    + 'input[name="warna"], input[name="ukuran"], input[name="model"], input[name="lokasi"]').val('');
                $npForm.find('input[name="min_stock"]').val(0);
                $('#np-harga-per').val('besar');
                $npForm.find('select[name="category_id"]').val('').trigger('change');
                $npForm.find('select[name="supplier_ids[]"]').val(null).trigger('change');
                setStatusProduk('sudah');
                setExtraOpen(false);
                refreshHarga();
            }

            function fillEditForm(data) {
                $npForm.find('input[name="name"]').val(data.name || '');
                $codeInput.attr('data-ignore-id', data.id).val(data.code || '');
                $('#np-satuan-besar').val(data.satuan_besar || '');
                $('#np-satuan').val(data.satuan || '');
                $('#np-konversi').val(data.konversi_qty ? String(data.konversi_qty) : '');
                // Harga kecil (products.harga_beli) adalah acuan; besar dihitung ulang dari kecil x konversi.
                // Memakai "kecil" supaya produk yang tidak diubah harganya tidak bergeser karena pembulatan.
                $('#np-harga-per').val('kecil');
                $('#np-harga-beli').val(fmt(round2(Number(data.harga_beli) || 0)));
                $('#np-harga-beli-besar').val('');
                $npForm.find('select[name="category_id"]').val(data.category_id ? String(data.category_id) : '').trigger('change');
                $npForm.find('select[name="supplier_ids[]"]').val((data.supplier_ids || []).map(String)).trigger('change');
                setStatusProduk(data.status_produk);
                $('#np-status-note').val(data.status_produk_note || '');
                $npForm.find('input[name="brand"]').val(data.brand || '');
                $npForm.find('input[name="min_stock"]').val(data.min_stock != null ? data.min_stock : 0);
                $npForm.find('input[name="warna"]').val(data.warna || '');
                $npForm.find('input[name="ukuran"]').val(data.ukuran || '');
                $npForm.find('input[name="model"]').val(data.model || '');
                $npForm.find('input[name="lokasi"]').val(data.lokasi || '');
                setExtraOpen(!!(data.brand || data.warna || data.ukuran || data.model || data.lokasi));
                refreshHarga();
            }

            function enterEditMode(data) {
                clearAjaxErrors();
                editState = { id: data.id, listingMode: data.listing_mode, listingNominal: data.listing_nominal };
                $panel.addClass('np-mode-edit');
                $('#np-title').text('Edit Barang');
                $('#np-title-icon').attr('class', 'fa fa-pencil');
                $('#np-submit-label').text('Simpan Perubahan');
                $npForm.attr('action', productsBase + '/' + data.id);
                $('#np-method').prop('disabled', false);
                $npForm.find('input[name="name"]').attr('autocomplete', 'off'); // riwayat browser hanya untuk mode tambah
                fillEditForm(data);
                renderListingEditInfo();
                $codeInput.trigger('barcode:recheck'); // cek barcode dengan mengecualikan produk ini sendiri
            }

            function enterCreateMode() {
                if (!isEditMode()) { return; }
                clearAjaxErrors();
                editState = { id: null, listingMode: 'closed', listingNominal: null };
                $panel.removeClass('np-mode-edit');
                $('#np-title').text('Daftarkan Produk Baru');
                $('#np-title-icon').attr('class', 'fa fa-plus-square');
                $('#np-submit-label').text('Simpan & Atur Harga');
                $npForm.attr('action', productsBase);
                $('#np-method').prop('disabled', true);
                $npForm.find('input[name="name"]').attr('autocomplete', 'on');
                $codeInput.removeAttr('data-ignore-id');
                resetNewProductForm();
                renderListingEditInfo();
                $codeInput.trigger('barcode:recheck'); // kode kosong = status cek barcode dibersihkan
            }

            // Tombol "Edit Barang" aktif hanya setelah produk dipilih
            function syncEditButton() {
                var hasProduct = !!$('#product_id').val();
                $('#edit-product-btn').prop('disabled', !hasProduct);
                $('#edit-product-hint').toggle(!hasProduct);
            }
            $('#product_id').on('change', function () {
                syncEditButton();
                // Ganti produk saat panel masih mode edit: tutup supaya tidak mengedit produk yang salah.
                if (isEditMode() && String($(this).val()) !== String(editState.id)) {
                    closePanel(enterCreateMode);
                }
            });
            syncEditButton();

            $('#edit-product-btn').on('click', function () {
                var productId = $('#product_id').val();
                if (!productId) { return; }

                // Panel masih berisi isian "Daftarkan Produk Baru": jangan timpa diam-diam.
                if ($panel.is(':visible') && !isEditMode()
                    && ($.trim($npForm.find('input[name="name"]').val()) !== '' || $.trim($codeInput.val()) !== '')
                    && !window.confirm('Isian "Daftarkan Produk Baru" akan diganti dengan data barang yang dipilih. Lanjutkan?')) {
                    return;
                }

                var $btn = $(this).prop('disabled', true);
                $('#np-flash').hide();
                $.getJSON(productsBase + '/' + productId)
                    .done(function (data) {
                        enterEditMode(data);
                        openPanel();
                    })
                    .fail(function () { showFlash('danger', 'Data barang gagal dimuat. Coba lagi.'); })
                    .always(syncEditButton);
            });

            function submitEdit() {
                var $submit = $('#submit-new-product-form').prop('disabled', true);
                clearAjaxErrors();
                $.ajax({
                    url: $npForm.attr('action'),
                    method: 'POST', // _method=PUT ikut terkirim di data form
                    data: $npForm.serialize(),
                    dataType: 'json',
                    headers: { 'Accept': 'application/json' }
                }).done(function (res) {
                    // Produk tetap terpilih; panel menutup tanpa reload sehingga isian form harga aman.
                    closePanel(enterCreateMode);
                    showFlash('success', res.message || 'Barang berhasil diperbarui.');
                    if (window.outletPriceProductUpdated) { window.outletPriceProductUpdated(res.product); }
                }).fail(showAjaxErrors)
                  .always(function () { $submit.prop('disabled', false); });
            }

            // Outlet diambil dari pilihan outlet di form harga; cegah klik ganda saat menyimpan.
            // (Handler cek barcode duplikat terpasang lebih dulu dan menahan submit bila barcode bentrok.)
            $npForm.on('submit', function (e) {
                $('#new-product-outlet-id').val($('#outlet_id').val() || '');
                if (isEditMode()) {
                    e.preventDefault();
                    submitEdit();
                    return;
                }
                if (window.saveOutletPriceDraft) { window.saveOutletPriceDraft(); }
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
        /* Panel barang dipakai untuk dua mode: tambah (default) dan edit (kelas np-mode-edit dari JS) */
        #new-product-panel .np-edit-only { display: none; }
        #new-product-panel.np-mode-edit .np-edit-only { display: block; }
        #new-product-panel.np-mode-edit .np-create-only { display: none !important; }
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

            // Harga netto POS dibulatkan ke ATAS kelipatan 100 (19.870 -> 19.900, 4.510 -> 4.600).
            // Aturan yang sama dengan PriceCalculator::roundingAmount() di server.
            const ROUND_STEP = 100;
            function roundUp(value) {
                const v = Math.round(value || 0);
                return v <= 0 ? 0 : Math.ceil(v / ROUND_STEP) * ROUND_STEP;
            }

            function number(id) {
                return (window.parseIdNumber ? window.parseIdNumber($(id).val()) : Number.parseFloat($(id).val())) || 0;
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

            // Netto akhir di POS (sudah dibulatkan) bila harga sebelum penyesuaian = price.
            function nettoFinal(price) {
                return roundUp(nettoFromPreAdjustment(price));
            }

            // Skor kecocokan terhadap target: utamakan netto akhir (setelah pembulatan) yang tepat,
            // lalu netto sebelum pembulatan yang paling dekat dengan target (diskon toko sekecil mungkin).
            function targetScore(preRoundNetto, target) {
                return Math.abs(roundUp(preRoundNetto) - target) * 1000 + Math.abs(preRoundNetto - target);
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
                const targetRaw = targetNetto();
                const $nominalLink = $('#target-use-nominal').hide();
                targetNominal = null;
                if (targetRaw === null || applyingTarget) { return; }
                // Harga netto POS selalu kelipatan 100, jadi target yang tidak bulat ikut dibulatkan ke atas.
                const target = roundUp(targetRaw);
                const roundNote = target !== targetRaw
                    ? ' Target ' + money(targetRaw) + ' dibulatkan ke atas menjadi ' + money(target) + '.'
                    : '';
                applyingTarget = true;
                try {
                    if (hargaAktif <= 0) {
                        setStoreDiscount('percentage', '0');
                        setTargetHint('warning', 'Harga Aktif masih Rp0, belum ada yang bisa didiskon. Lengkapi HPP dan margin dulu.');
                        return;
                    }

                    const maxNetto = nettoFinal(hargaAktif);   // netto akhir (sudah dibulatkan) tanpa diskon toko
                    if (target >= maxNetto) {
                        setStoreDiscount('percentage', '0');
                        setTargetHint(target > maxNetto ? 'warning' : 'success',
                            target > maxNetto
                                ? 'Netto ' + money(target) + ' lebih tinggi dari netto tanpa diskon toko (' + money(maxNetto) + '). Diskon toko diset 0%.'
                                : 'Netto ' + money(target) + ' sama dengan netto tanpa diskon toko, diskon toko 0%.' + roundNote);
                        return;
                    }

                    // Cari harga sebelum penyesuaian (P) yang menghasilkan netto = target.
                    const adjType = $('#outlet_adjustment_type').val();
                    const adjValue = number('#outlet_adjustment_value');
                    const guess = adjType === 'percentage' ? target / (1 + adjValue / 100) : target - adjValue;
                    let bestPrice = Math.min(hargaAktif, Math.max(0, round(guess)));
                    let bestDiff = targetScore(nettoFromPreAdjustment(bestPrice), target);
                    for (let d = -3; d <= 3; d++) {
                        const candidate = round(guess) + d;
                        if (candidate < 0 || candidate > hargaAktif) { continue; }
                        const diff = targetScore(nettoFromPreAdjustment(candidate), target);
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
                        if (pctNetto === null || targetScore(netto, target) < targetScore(pctNetto, target)) {
                            pct = candidate;
                            pctNetto = netto;
                        }
                    });

                    setStoreDiscount('percentage', String(pct));
                    const storeAmount = round(hargaAktif * pct / 100);

                    if (roundUp(pctNetto) === target) {
                        setTargetHint('success', 'Diskon Toko = ' + percentText(pct) + '% (' + money(storeAmount)
                            + ' dari Harga Aktif ' + money(hargaAktif) + ') → Netto ' + money(target) + '.' + roundNote);
                    } else {
                        targetNominal = discountAmount;
                        setTargetHint('warning', 'Dengan persen 2 desimal, netto jadi ' + money(roundUp(pctNetto)) + ' (selisih '
                            + money(Math.abs(roundUp(pctNetto) - target)) + ' dari target).' + roundNote + ' ');
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
                    $value.val('');
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
                let hargaDasar = Math.max(0, round(hargaAkhir - additional));
                // Diskon Tambahan 2-4 bertingkat: masing-masing dari harga setelah diskon sebelumnya.
                const additionalExtra = {};
                [2, 3, 4].forEach(function (n) {
                    const amount = round(discount(hargaDasar, $('#disc_tambahan_' + n + '_type').val(), number('#disc_tambahan_' + n + '_value')));
                    additionalExtra[n] = amount;
                    hargaDasar = Math.max(0, round(hargaDasar - amount));
                });
                const margin = $('#margin_type').val() === 'percentage'
                    ? round(hargaDasar * number('#margin_value') / 100)
                    : round(number('#margin_value'));
                const hargaAktif = round(hargaDasar + margin);
                applyTargetNetto(hargaAktif);
                const store = round(discount(hargaAktif, $('#disc_toko_type').val(), number('#disc_toko_value')));
                const hargaSebelumPenyesuaian = Math.max(0, round(hargaAktif - store));
                const adjustment = round(addition(hargaSebelumPenyesuaian, $('#outlet_adjustment_type').val(), number('#outlet_adjustment_value')));
                const hargaSebelumPembulatan = round(hargaSebelumPenyesuaian + adjustment);
                const pembulatan = roundUp(hargaSebelumPembulatan) - hargaSebelumPembulatan;
                const hargaNetto = hargaSebelumPembulatan + pembulatan;

                $('#preview-hpp').text(money(hpp));
                $('#preview-tax').text(money(tax));
                $('#preview-after-tax').text(money(hppSetelahPajak));
                $('#preview-brand').text(money(brand));
                $('#preview-final').text(money(hargaAkhir));
                $('#preview-additional').text(money(additional));
                [2, 3, 4].forEach(function (n) { $('#preview-additional-' + n).text(money(additionalExtra[n])); });
                $('#preview-base').text(money(hargaDasar));
                $('#preview-margin').text(money(margin));
                $('#preview-active').text(money(hargaAktif));
                $('#preview-store').text(money(store));
                $('#preview-adjustment').text(money(adjustment));
                $('#preview-pre-round').text(money(hargaSebelumPembulatan));
                $('#preview-rounding').text((pembulatan > 0 ? '+' : '') + money(pembulatan));
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
                const changed = Math.round((number('#preview_hpp') || 0) * 100) !== Math.round(loadedHpp * 100);
                $('#hpp_changed').val(changed ? '1' : '0');
            });
            // ===== Input HPP per satuan kecil / besar =====
            // Harga jual tetap dihitung per satuan kecil; di mode besar, HPP kecil = HPP besar / konversi.
            let unitInfo = null; // { satuan, satuan_besar, konversi_qty } bila produk punya satuan besar
            const fmtId = (n) => Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 });
            const round2c = (n) => Math.round(n * 100) / 100;
            const unitKonversi = () => (unitInfo ? Number(unitInfo.konversi_qty) || 0 : 0);

            function updateHppUnitHint() {
                const $hint = $('#hpp-unit-hint');
                if (!unitInfo) { $hint.hide(); return; }
                const konv = unitKonversi();
                if ($('#hpp_unit').val() === 'besar') {
                    $hint.text('1 ' + unitInfo.satuan_besar + ' = ' + konv + ' ' + unitInfo.satuan + '. HPP per ' + unitInfo.satuan
                        + ' = HPP per ' + unitInfo.satuan_besar + ' ÷ ' + konv + ' (otomatis).').show();
                } else {
                    $hint.text('≈ ' + rupiah((number('#preview_hpp') || 0) * konv) + ' per ' + unitInfo.satuan_besar
                        + ' (1 ' + unitInfo.satuan_besar + ' = ' + konv + ' ' + unitInfo.satuan + ').').show();
                }
            }

            function setHppUnit(unit) {
                if (unit === 'besar' && unitKonversi() < 1) { unit = 'kecil'; }
                const isBesar = unit === 'besar';
                $('#hpp_unit').val(unit);
                $('#hpp-unit-toggle button').removeClass('active btn-primary').addClass('btn-default');
                $('#hpp-unit-toggle button[data-unit="' + unit + '"]').removeClass('btn-default').addClass('active btn-primary');
                $('#hpp-besar-group').toggle(isBesar);
                $('#preview_hpp').prop('readonly', isBesar);
                if (isBesar) {
                    $('#preview_hpp_besar').val(fmtId(Math.round((number('#preview_hpp') || 0) * unitKonversi())));
                    window.initCurrencyInputs?.($('#preview_hpp_besar')[0]);
                } else {
                    $('#preview_hpp_besar').val('');
                }
                updateHppUnitHint();
            }

            function setUnitInfo(data) {
                unitInfo = data && data.satuan_besar && Number(data.konversi_qty) >= 1
                    ? { satuan: data.satuan || 'satuan kecil', satuan_besar: data.satuan_besar, konversi_qty: Number(data.konversi_qty) }
                    : null;
                $('#hpp-unit-toggle').toggle(!!unitInfo);
                $('#hpp-label-unit').text(data && data.satuan ? '(per ' + data.satuan + ')' : '');
                $('.hpp-name-kecil').text(data && data.satuan ? data.satuan : 'satuan kecil');
                $('.hpp-name-besar').text(unitInfo ? unitInfo.satuan_besar : 'satuan besar');
                setHppUnit('kecil');
            }

            $('#hpp-unit-toggle button').on('click', function () { setHppUnit($(this).data('unit')); });

            $('#preview_hpp_besar').on('input', function () {
                const konv = unitKonversi();
                if (konv < 1) { return; }
                const kecil = round2c((number('#preview_hpp_besar') || 0) / konv);
                $('#preview_hpp').val(fmtId(kecil));
                window.initCurrencyInputs?.($('#preview_hpp')[0]);
                $('#preview_hpp').trigger('input');
            });
            $('#preview_hpp').on('input', updateHppUnitHint);

            // Info HPP kecil/besar, satuan & konversi. HPP sama di semua outlet,
            // jadi cukup produk yang dipilih; outlet hanya dikirim bila sudah dipilih.
            const rupiah = (v) => 'Rp' + Math.round(Number(v) || 0).toLocaleString('id-ID');

            function renderProductInfo(data) {
                setUnitInfo(data);
                if (!data) {
                    $('#product-unit-info').hide();
                    return;
                }
                const hasBesar = !!data.satuan_besar;
                $('#pui-hpp-kecil').text(rupiah(data.hpp_kecil) + (data.satuan ? ' / ' + data.satuan : ''));
                $('#pui-hpp-besar').text(hasBesar ? rupiah(data.hpp_besar) + ' / ' + data.satuan_besar : '-');
                $('#pui-satuan').text((data.satuan || '-') + (hasBesar ? ' / ' + data.satuan_besar : ''));
                $('#pui-konversi').text(hasBesar ? data.konversi_text : '-');
                $('#product-unit-info').show();
            }

            let hppRequestSeq = 0;
            function loadProductHpp(fillHpp) {
                const seq = ++hppRequestSeq;
                const outletId = $('#outlet_id').val();
                const productId = $('#product_id').val();
                if (!productId) {
                    renderProductInfo(null);
                    updatePreview();
                    return;
                }
                const params = { product_id: productId };
                if (outletId) {
                    params.outlet_id = outletId;
                }
                $.get('{{ route('outlet-prices.preview-hpp') }}', params)
                    .done(function (data) {
                        if (seq !== hppRequestSeq) { return; } // respons lama (produk/outlet sudah berganti)
                        renderProductInfo(data);
                        if (fillHpp) {
                            loadedHpp = Number(data.hpp) || 0;
                            $('#preview_hpp').val(fmtId(Number(data.hpp) || 0));
                            window.initCurrencyInputs?.($('#preview_hpp')[0]);
                            $('#preview_hpp').trigger('input');
                        }
                    });
                updatePreview();
            }

            $('#outlet_id, #product_id').on('change', function () {
                loadProductHpp(true);
            });
            // Dipanggil panel "Edit Barang" setelah barang tersimpan: kode/nama di pilihan produk ikut berubah dan
            // info HPP, satuan & konversi dimuat ulang. HPP di kalkulator ikut data baru hanya bila belum diubah manual,
            // supaya angka yang sedang diketik tidak tertimpa.
            window.outletPriceProductUpdated = function (product) {
                const $select = $('#product_id');
                $select.find('option[value="' + product.id + '"]').text(product.code + ' — ' + product.name);
                $select.trigger('change.select2'); // segarkan tampilan select2 tanpa memicu pemuatan HPP otomatis
                const untouched = Math.round((number('#preview_hpp') || 0) * 100) === Math.round(loadedHpp * 100);
                loadProductHpp(untouched);
            };
            // Saat halaman dibuka dengan produk sudah terpilih (edit / setelah daftar produk baru):
            // tampilkan info; isi HPP hanya bila ini form baru dan HPP-nya masih kosong.
            if ($('#product_id').val()) {
                const emptyHpp = Math.round(number('#preview_hpp')) === 0;
                loadProductHpp(!{{ $price->exists ? 'true' : 'false' }} && emptyHpp);
            }
            // ===== Aturan harga lama otomatis terambil (mode tambah) =====
            // Pilih outlet + produk yang aturan harganya sudah ada -> isian lama dimuat ke form.
            // Saat disimpan, server memperbarui aturan lama itu (store() memakai outlet + produk sebagai kunci).
            const HAS_OLD_INPUT = @json($errors->any());
            const RULE_TYPE_DEFAULTS = {
                disc_brand_type: 'percentage', pajak_type: 'percentage',
                disc_tambahan_type: 'percentage', disc_tambahan_2_type: 'percentage',
                disc_tambahan_3_type: 'percentage', disc_tambahan_4_type: 'percentage',
                margin_type: 'percentage', disc_toko_type: 'percentage', outlet_adjustment_type: 'nominal'
            };
            const RULE_VALUE_FIELDS = ['disc_brand_value', 'pajak_value', 'disc_tambahan_value', 'disc_tambahan_2_value',
                'disc_tambahan_3_value', 'disc_tambahan_4_value', 'margin_value', 'disc_toko_value', 'outlet_adjustment_value'];
            let existingRuleSeq = 0;
            let existingRuleLoaded = false;

            function applyRuleFields(rule) {
                Object.keys(RULE_TYPE_DEFAULTS).forEach(function (name) {
                    $('#' + name).val(rule[name] || RULE_TYPE_DEFAULTS[name]).trigger('change');
                });
                RULE_VALUE_FIELDS.forEach(function (name) {
                    $('#' + name).val(String(rule[name] != null ? rule[name] : 0));
                    window.initCurrencyInputs?.($('#' + name)[0]);
                });
                $('#effective_from').val(rule.effective_from || '');
                $('#effective_until').val(rule.effective_until || '');
                $('#note').val(rule.note || '');
                $('#price-rule-form input[name="is_active"]').prop('checked', rule.is_active !== false);
                clearTarget();
                adjustmentAutoFilled = false;
                syncPrefixes();
                updatePreview();
            }

            function clearExistingRule() {
                $('#existing-rule-alert').hide().text('');
                if (!existingRuleLoaded) { return; }
                existingRuleLoaded = false;
                // Kembali ke isian bawaan form baru (bukan sisa aturan produk sebelumnya).
                applyRuleFields({ is_active: true });
                applyOutletAdjustmentDefault(true);
            }

            function loadExistingRule() {
                if (!IS_CREATE_MODE) { return; }
                const outletId = $('#outlet_id').val();
                const productId = $('#product_id').val();
                const seq = ++existingRuleSeq;
                if (!outletId || !productId) { clearExistingRule(); return; }

                $.get('{{ route('outlet-prices.existing') }}', { outlet_id: outletId, product_id: productId })
                    .done(function (res) {
                        if (seq !== existingRuleSeq) { return; } // respons lama (pilihan sudah berganti)
                        if (!res || !res.exists) { clearExistingRule(); return; }
                        applyRuleFields(res.rule);
                        existingRuleLoaded = true;
                        $('#existing-rule-alert')
                            .text('Aturan harga untuk produk ini di outlet ini sudah ada' + (res.updated_at ? ' (terakhir diubah ' + res.updated_at + ')' : '')
                                + '. Isiannya sudah dimuat otomatis; menyimpan akan memperbarui aturan lama, bukan membuat yang baru.')
                            .show();
                    });
            }

            $('#outlet_id, #product_id').on('change', loadExistingRule);
            if (IS_CREATE_MODE && !HAS_OLD_INPUT && $('#outlet_id').val() && $('#product_id').val()) {
                loadExistingRule();
            }

            // ===== Draft komponen harga =====
            // "Daftarkan Produk Baru" menyimpan lewat redirect penuh (halaman dimuat ulang). Supaya komponen harga yang
            // sudah diisi tidak hilang, isiannya disimpan sementara di sessionStorage lalu dipulihkan setelah halaman
            // kembali (hanya bila ada product_id di URL, outlet sama, dan belum lewat 5 menit).
            const DRAFT_KEY = 'outlet_price_draft';
            const DRAFT_FIELDS = /^(disc_|pajak_|margin_|outlet_adjustment_|effective_|note$)/;

            window.saveOutletPriceDraft = function () {
                try {
                    const values = {};
                    $('#price-rule-form').find('input[name], select[name], textarea[name]').each(function () {
                        if (DRAFT_FIELDS.test(this.name) && this.type !== 'hidden') { values[this.name] = $(this).val(); }
                    });
                    sessionStorage.setItem(DRAFT_KEY, JSON.stringify({
                        at: Date.now(),
                        outlet: String($('#outlet_id').val() || ''),
                        values: values
                    }));
                } catch (e) { /* sessionStorage tidak tersedia: abaikan */ }
            };

            (function restoreOutletPriceDraft() {
                if (!IS_CREATE_MODE) { return; }
                let draft = null;
                try {
                    draft = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || 'null');
                    sessionStorage.removeItem(DRAFT_KEY); // sekali pakai
                } catch (e) { return; }
                if (!draft || !draft.values) { return; }
                const fresh = Date.now() - draft.at < 5 * 60 * 1000;
                const hasProduct = new URLSearchParams(window.location.search).has('product_id');
                if (!fresh || !hasProduct || draft.outlet !== String($('#outlet_id').val() || '')) { return; }

                Object.keys(draft.values).forEach(function (name) {
                    const $el = $('#price-rule-form').find('[name="' + name + '"]').not('[type=hidden]').first();
                    if (!$el.length) { return; }
                    $el.val(draft.values[name]);
                    if ($el.is('select')) { $el.trigger('change.select2'); }
                });
                syncPrefixes();
                window.initCurrencyInputs?.(document.getElementById('price-rule-form'));
                updatePreview();
            })();

            updatePrefixes();
            applyOutletAdjustmentDefault(true);
        });
    </script>
@endsection