@extends('layouts.master')

@php
    $brandType = old('disc_brand_type', $price->disc_brand_type ?: 'nominal');
    $additionalType = old('disc_tambahan_type', $price->disc_tambahan_type ?: 'nominal');
    $marginType = old('margin_type', $price->margin_type ?: 'percentage');
    $storeDiscType = old('disc_toko_type', $price->disc_toko_type ?: 'nominal');
    $taxType = old('pajak_type', $price->pajak_type ?: 'percentage');
    $adjustmentType = old('outlet_adjustment_type', $price->outlet_adjustment_type ?: 'nominal');
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
                                        <p class="text-muted small">Tambahan harga untuk Beauty atau outlet lain. Default Rp0.</p>
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
                                    <input id="preview_hpp" type="text" inputmode="numeric" data-currency-input data-currency-decimals="0" min="0" step="1" class="form-control" value="{{ $previewHpp ?? 100000 }}">
                                </div>
                                <p class="help-block">Diisi otomatis dari HPP terakhir produk dan outlet. Anda dapat mengubah angka ini untuk simulasi.</p>
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
            $('#outlet_id, #product_id').on('change', function () {
                const outletId = $('#outlet_id').val();
                const productId = $('#product_id').val();
                if (!outletId || !productId) {
                    updatePreview();
                    return;
                }
                $.get('{{ route('outlet-prices.preview-hpp') }}', { outlet_id: outletId, product_id: productId })
                    .done(function (data) {
                        $('#preview_hpp').val(data.hpp).trigger('input');
                    });
                updatePreview();
            });
            updatePrefixes();
        });
    </script>
@endsection
