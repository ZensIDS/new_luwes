@extends('layouts.master')

@php
    $brandType = old('disc_brand_type', $price->disc_brand_type ?: 'nominal');
    $marginType = old('margin_type', $price->margin_type ?: 'percentage');
    $storeDiscType = old('disc_toko_type', $price->disc_toko_type ?: 'nominal');
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
                                            <option value="{{ $outlet->id }}"
                                                {{ old('outlet_id', $price->outlet_id) == $outlet->id ? 'selected' : '' }}>
                                                {{ $outlet->name }}
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
                                    <select id="product_id" name="product_id" class="form-control select2 price-select"
                                        data-placeholder="Cari kode atau nama produk" style="width:100%" required
                                        {{ $price->exists ? 'disabled' : '' }}>
                                        <option value=""></option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}"
                                                {{ old('product_id', $price->product_id) == $product->id ? 'selected' : '' }}>
                                                {{ $product->code }} — {{ $product->name }}
                                            </option>
                                        @endforeach
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
                                        <h4 style="margin-top:0"><span class="label label-info">1</span> Disc Brand</h4>
                                        <p class="text-muted small">Mengurangi HPP untuk membentuk Harga Akhir dan tidak mengurangi margin.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_brand_type">Tipe Potongan</label>
                                        <select id="disc_brand_type" name="disc_brand_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $brandType === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $brandType === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_brand_value">Nilai Disc Brand</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="disc_brand_type">Rp</span>
                                            <input id="disc_brand_value" name="disc_brand_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="disc_brand_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('disc_brand_value', $price->disc_brand_value ?? 0) }}" placeholder="0">
                                        </div>
                                        @error('disc_brand_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="price-rule-card price-rule-margin">
                                <div class="row">
                                    <div class="col-sm-4">
                                        <h4 style="margin-top:0"><span class="label label-success">2</span> Margin</h4>
                                        <p class="text-muted small">Keuntungan dihitung dari Harga Akhir setelah Disc Brand.</p>
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
                                        <h4 style="margin-top:0"><span class="label label-warning">3</span> Disc Toko</h4>
                                        <p class="text-muted small">Dipotong dari Harga Aktif dan menjadi Harga Netto di POS.</p>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_toko_type">Tipe Potongan</label>
                                        <select id="disc_toko_type" name="disc_toko_type" class="form-control select2 price-type" style="width:100%">
                                            <option value="nominal" {{ $storeDiscType === 'nominal' ? 'selected' : '' }}>Rp — Nominal</option>
                                            <option value="percentage" {{ $storeDiscType === 'percentage' ? 'selected' : '' }}>% — Persentase</option>
                                        </select>
                                    </div>
                                    <div class="col-sm-4 form-group">
                                        <label for="disc_toko_value">Nilai Disc Toko</label>
                                        <div class="input-group">
                                            <span class="input-group-addon price-prefix" data-for="disc_toko_type">Rp</span>
                                            <input id="disc_toko_value" name="disc_toko_value" type="text" inputmode="decimal" data-currency-input data-currency-toggle="disc_toko_type" data-currency-decimals="0" min="0" step="0.01" class="form-control price-value"
                                                value="{{ old('disc_toko_value', $price->disc_toko_value ?? 0) }}" placeholder="0">
                                        </div>
                                        @error('disc_toko_value') <span class="help-block text-danger">{{ $message }}</span> @enderror
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
                                <label for="preview_hpp">Contoh HPP</label>
                                <div class="input-group">
                                    <span class="input-group-addon">Rp</span>
                                    <input id="preview_hpp" type="text" inputmode="numeric" data-currency-input data-currency-decimals="0" min="0" step="1" class="form-control" value="100000">
                                </div>
                                <p class="help-block">Preview ini tidak disimpan. HPP sebenarnya diambil dari batch Stock Toko saat POS.</p>
                            </div>
                            <div class="price-preview-list">
                                <div><span>HPP</span><strong id="preview-hpp">Rp0</strong></div>
                                <div><span>Disc Brand</span><strong id="preview-brand">Rp0</strong></div>
                                <div><span>Harga Akhir</span><strong id="preview-final">Rp0</strong></div>
                                <div><span>Margin</span><strong id="preview-margin">Rp0</strong></div>
                                <div><span>Harga Aktif</span><strong id="preview-active">Rp0</strong></div>
                                <div><span>Disc Toko</span><strong id="preview-store">Rp0</strong></div>
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
                <a class="btn btn-default" href="{{ route('outlet-prices.index') }}"><i class="fa fa-arrow-left"></i> Kembali</a>
                <button type="submit" class="btn btn-primary pull-right"><i class="fa fa-save"></i> Simpan Aturan Harga</button>
            </div>
        </form>
    </section>
@endsection

@section('page-script')
    <style>
        .price-rule-card {
            border: 1px solid #e5e7eb;
            border-left: 4px solid #3c8dbc;
            border-radius: 4px;
            padding: 16px 14px 4px;
            margin-bottom: 14px;
            background: #fff;
        }
        .price-rule-margin { border-left-color: #00a65a; }
        .price-rule-store { border-left-color: #f39c12; }
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

            function money(value) {
                return 'Rp' + Math.round(value || 0).toLocaleString('id-ID');
            }

            function number(id) {
                return window.parseIdNumber ? window.parseIdNumber($(id).val()) : (Number.parseFloat($(id).val()) || 0);
            }

            function discount(base, type, value) {
                return type === 'percentage' ? base * value / 100 : value;
            }

            function updatePreview() {
                const hpp = number('#preview_hpp');
                const brand = discount(hpp, $('#disc_brand_type').val(), number('#disc_brand_value'));
                const hargaAkhir = Math.max(0, hpp - brand);
                const margin = $('#margin_type').val() === 'percentage'
                    ? hargaAkhir * number('#margin_value') / 100
                    : number('#margin_value');
                const hargaAktif = hargaAkhir + margin;
                const store = discount(hargaAktif, $('#disc_toko_type').val(), number('#disc_toko_value'));
                const hargaNetto = Math.max(0, hargaAktif - store);

                $('#preview-hpp').text(money(hpp));
                $('#preview-brand').text(money(brand));
                $('#preview-final').text(money(hargaAkhir));
                $('#preview-margin').text(money(margin));
                $('#preview-active').text(money(hargaAktif));
                $('#preview-store').text(money(store));
                $('#preview-net').text(money(hargaNetto));
            }

            function updatePrefixes() {
                $('.price-prefix').each(function () {
                    const type = $('#' + $(this).data('for')).val();
                    $(this).text(type === 'percentage' ? '%' : 'Rp');
                });
                updatePreview();
            }

            $('.price-type').on('change', function () {
                window.initCurrencyInputs?.();
                updatePrefixes();
            });
            $('#price-rule-form input').on('input', updatePreview);
            updatePrefixes();
        });
    </script>
@endsection
