@extends('layouts.master')

@php
    $campaign = $campaign ?? null;
    $isEdit = $isEdit ?? false;
    $routeType = $routeType ?? ($campaignType === 'voucher' ? 'voucher' : 'promotion');
    $selectedType = old('campaign_type', $campaignType);
    $selectedProducts = (array) old('products', $selectedProducts ?? []);
    $selectedOutlets = (array) old('outlet_ids', $selectedOutlets ?? []);
    $bonusRows = (array) old('bonuses', $bonusRows ?? []);
    $defaultDiscountType = $campaign
        ? ($campaignType === 'voucher' ? $campaign->type : $campaign->discount_type)
        : 'percentage';
    $defaultDiscountValue = $campaign
        ? ($campaignType === 'bundle' ? $campaign->bundle_price : ($campaignType === 'voucher' ? $campaign->value : $campaign->discount_value))
        : '';
    // Voucher: default 1 kode. Rafaksi/Bundle: kosong = tanpa batas kuota.
    $defaultQuota = $campaignType === 'voucher' ? ($selectedType === 'voucher' ? 1 : null) : $campaign?->quota_qty;
    $defaultActive = $campaign?->is_active ?? true;
@endphp

@section('title', $isEdit ? 'Edit Voucher / Promo' : 'Buat Voucher / Promo')

@section('container')
<section class="content-header">
    <h1>{{ $isEdit ? 'Edit Voucher / Promo' : 'Buat Voucher / Promo' }} <small>Satu halaman untuk semua jenis promo</small></h1>
</section>

<section class="content">
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Periksa kembali data promo:</strong>
            <ul style="margin: 6px 0 0 18px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('campaign.update', ['type' => $routeType, 'id' => $campaign->id]) : route('campaign.store') }}" id="campaign-form">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif
        <input type="hidden" name="code_auto" id="code-auto" value="{{ old('code_auto', $isEdit ? 0 : 1) }}">

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-tags"></i> Informasi promo</h3>
            </div>
            <div class="box-body">
                <div class="alert alert-info" style="margin-bottom: 20px;">
                    <i class="fa fa-lightbulb-o"></i>
                    Pilih jenis promo, isi nama, lalu pilih produk yang berlaku. Kolom yang tidak diperlukan akan disembunyikan otomatis.
                </div>

                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="campaign-type">Jenis promo</label>
                        @if ($isEdit)
                            <input type="hidden" name="campaign_type" value="{{ $selectedType }}">
                        @endif
                        <select id="campaign-type" class="form-control" required @if (!$isEdit) name="campaign_type" @endif {{ $isEdit ? 'disabled' : '' }}>
                            <option value="voucher" {{ $selectedType === 'voucher' ? 'selected' : '' }}>Voucher</option>
                            <option value="flash_sale" {{ $selectedType === 'flash_sale' ? 'selected' : '' }}>Rafaksi</option>
                            <option value="bundle" {{ $selectedType === 'bundle' ? 'selected' : '' }}>Bundle + Bonus</option>
                        </select>
                        <small class="help-block" id="campaign-help"></small>
                        @if ($isEdit)<small class="help-block">Jenis promo tidak dapat diubah setelah disimpan.</small>@endif
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="campaign-name">Nama promo</label>
                        <input type="text" name="name" id="campaign-name" class="form-control" value="{{ old('name', $campaign?->name) }}" required autofocus>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="campaign-code">Kode scan <small>(otomatis)</small></label>
                        <input type="text" name="code" id="campaign-code" class="form-control" value="{{ old('code', $campaign?->code) }}" maxlength="100" autocomplete="off">
                        <small class="help-block">{{ $isEdit ? 'Kode saat ini dipertahankan. Ubah jika perlu.' : 'Kode dibuat dari nama. Ubah jika ingin memakai kode sendiri.' }}</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group discount-field">
                        <label for="discount-type">Tipe potongan</label>
                        <select name="discount_type" id="discount-type" class="form-control">
                            <option value="percentage" {{ old('discount_type', $defaultDiscountType) === 'percentage' ? 'selected' : '' }}>Persentase (%)</option>
                            <option value="nominal" {{ old('discount_type', $defaultDiscountType) === 'nominal' ? 'selected' : '' }}>Nominal (Rp)</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group discount-field">
                        <label for="discount-value">Nilai potongan</label>
                        <div class="input-group">
                            <span class="input-group-addon" id="discount-prefix">%</span>
                            <input type="text" name="discount_value" id="discount-value" class="form-control" inputmode="numeric" data-currency-input data-currency-toggle="discount-type" data-currency-decimals="0" value="{{ old('discount_value', $defaultDiscountValue) }}">
                        </div>
                        <small class="help-block" id="discount-help"></small>
                    </div>
                    <div class="col-md-3 form-group campaign-quota">
                        <label for="quota-qty">Jumlah / kuota promo</label>
                        <input type="number" name="quota_qty" id="quota-qty" class="form-control" min="1" value="{{ old('quota_qty', $defaultQuota) }}" placeholder="Kosong = tanpa batas" {{ $isEdit && $routeType === 'voucher' ? 'disabled' : '' }}>
                        <small class="help-block" id="quota-help"></small>
                    </div>
                    <div class="col-md-3 form-group campaign-max-qty">
                        <label for="max-qty">Batas pemakaian / transaksi <small>(opsional)</small></label>
                        <input type="number" name="max_qty" id="max-qty" class="form-control" min="1" value="{{ old('max_qty', $campaign?->max_qty) }}">
                        <small class="help-block">Kosongkan jika tidak dibatasi.</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 form-group voucher-only">
                        <label for="max-discount">Maksimal nilai potongan <small>(opsional)</small></label>
                        <div class="input-group">
                            <span class="input-group-addon">Rp</span>
                            <input type="text" name="max_discount_amount" id="max-discount" class="form-control" inputmode="numeric" data-currency-input data-currency-decimals="0" value="{{ old('max_discount_amount', $campaign?->max_discount_amount) }}">
                        </div>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="min-purchase">Minimal belanja <small>(opsional)</small></label>
                        <div class="input-group">
                            <span class="input-group-addon">Rp</span>
                            <input type="text" name="min_purchase" id="min-purchase" class="form-control" inputmode="numeric" data-currency-input data-currency-decimals="0" value="{{ old('min_purchase', $campaign?->min_purchase) }}">
                        </div>
                    </div>
                    <div class="col-md-6 form-group promotion-only">
                        <label class="checkbox-inline" style="padding-left: 0; margin-top: 25px;">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $defaultActive) ? 'checked' : '' }}>
                            Promo aktif
                        </label>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="daterange">Masa berlaku <small>(opsional)</small></label>
                        <input type="text" name="daterange" id="daterange" class="form-control" value="{{ old('daterange', $campaign?->start_at && $campaign?->end_at ? $campaign->start_at->format('Y-m-d H:i').' - '.$campaign->end_at->format('Y-m-d H:i') : '') }}" placeholder="Pilih tanggal dan jam">
                        <small class="help-block">Kosongkan agar promo tidak dibatasi tanggal.</small>
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="outlet-ids">Outlet yang berlaku <small>(opsional)</small></label>
                        <select name="outlet_ids[]" id="outlet-ids" class="form-control select2" multiple data-placeholder="Semua outlet">
                            @foreach ($outlets as $outlet)
                                <option value="{{ $outlet->id }}" {{ in_array($outlet->id, $selectedOutlets) ? 'selected' : '' }}>{{ $outlet->name }}</option>
                            @endforeach
                        </select>
                        <small class="help-block">Kosongkan agar berlaku di semua outlet.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="campaign-desc">Catatan <small>(opsional)</small></label>
                    <input type="text" name="desc" id="campaign-desc" class="form-control" value="{{ old('desc', $campaign?->desc) }}" placeholder="Contoh: promo akhir pekan">
                </div>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-shopping-basket"></i> Produk yang berlaku</h3>
            </div>
            <div class="box-body">
                <p class="help-block" id="product-help"></p>
                <div class="row">
                    <div class="col-md-8 form-group">
                        <label>Produk yang dipilih</label>
                        <div>
                            <button type="button" class="btn btn-primary" id="open-product-modal"><i class="fa fa-search"></i> Pilih Produk</button>
                            <span class="text-muted" id="picked-count" style="margin-left: 10px;"></span>
                        </div>
                        {{-- Penyimpan pilihan produk (tidak dikirim; yang dikirim adalah input products[id] di tabel bawah). --}}
                        <select id="product-picker" multiple style="display: none;">
                            @foreach ($selectedProductOptions as $product)
                                <option value="{{ $product['id'] }}" selected>{{ $product['code'] }} — {{ $product['name'] }}</option>
                            @endforeach
                        </select>
                        <small class="help-block">Buka daftar produk untuk mencari dan mencentang beberapa produk sekaligus (hanya produk yang harga jualnya sudah diatur; dimuat per halaman dari server, jadi tetap ringan walau produknya ribuan), atau scan barcode di samping.</small>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="product-scan">Scan barcode produk</label>
                        <input type="text" id="product-scan" class="form-control" placeholder="Scan lalu tekan Enter" autocomplete="off">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="selected-products-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Produk</th>
                                <th width="180" class="required-qty-column">Isi per bundle</th>
                                <th width="70">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="alert alert-warning" id="no-products" style="display: none; margin-bottom: 0;"></div>
            </div>
        </div>

        <div class="box box-primary bundle-only">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-gift"></i> Bonus bundle <small>(opsional)</small></h3>
            </div>
            <div class="box-body">
                <p class="help-block">Tambahkan bonus yang diterima pelanggan. Bonus ini dicatat sebagai informasi promo dan tidak mengurangi stok produk.</p>
                <div id="bonus-rows">
                    @foreach ($bonusRows as $index => $bonus)
                        <div class="row bonus-row" style="margin-bottom: 8px;">
                            <div class="col-md-8"><input type="text" name="bonuses[{{ $index }}][name]" class="form-control" placeholder="Nama bonus" value="{{ $bonus['name'] ?? '' }}"></div>
                            <div class="col-md-3"><input type="number" name="bonuses[{{ $index }}][qty]" class="form-control" min="1" placeholder="Qty" value="{{ $bonus['qty'] ?? 1 }}"></div>
                            <div class="col-md-1"><button type="button" class="btn btn-danger remove-bonus" title="Hapus bonus"><i class="fa fa-trash"></i></button></div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-default" id="add-bonus"><i class="fa fa-plus"></i> Tambah bonus</button>
            </div>
        </div>

        <div class="box-footer" style="padding-left: 0; padding-right: 0;">
            <a href="{{ route('voucher.index') }}" class="btn btn-default">Batal</a>
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <span id="save-label">{{ $isEdit ? 'Simpan perubahan' : 'Simpan promo' }}</span></button>
        </div>
    </form>

    {{-- Modal pilih produk: di luar <form> supaya tombol Enter di kolom cari tidak mengirim form. --}}
    <div class="modal fade" id="product-modal" tabindex="-1" role="dialog" data-backdrop="static">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-shopping-basket"></i> Pilih Produk</h4>
                </div>
                <div class="modal-body">
                    <div class="input-group" style="margin-bottom: 10px;">
                        <input type="search" id="product-modal-search" class="form-control" placeholder="Cari nama atau kode produk (kosong = semua produk berharga jual)" autocomplete="off">
                        <span class="input-group-addon"><i class="fa fa-search"></i></span>
                    </div>
                    <p class="help-block" style="margin-top: 0;">Produk yang sudah ditambahkan langsung tercentang. Pilihan tetap tersimpan saat pindah halaman atau mengganti pencarian; hilangkan centang untuk membuang produk.</p>
                    <div class="table-responsive" style="max-height: 55vh; overflow-y: auto;">
                        <table class="table table-bordered table-striped table-hover" style="margin-bottom: 0;">
                            <thead>
                                <tr>
                                    <th width="36"><input type="checkbox" id="product-modal-check-page" title="Centang semua di halaman ini"></th>
                                    <th width="170">Kode</th>
                                    <th>Produk</th>
                                </tr>
                            </thead>
                            <tbody id="product-modal-body"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer" style="text-align: left;">
                    <div class="row">
                        <div class="col-sm-5" style="padding-top: 6px;">
                            <button type="button" class="btn btn-default btn-sm" id="product-modal-prev"><i class="fa fa-chevron-left"></i> Sebelumnya</button>
                            <span id="product-modal-page" style="margin: 0 8px;">Hal. 1</span>
                            <button type="button" class="btn btn-default btn-sm" id="product-modal-next">Berikutnya <i class="fa fa-chevron-right"></i></button>
                        </div>
                        <div class="col-sm-7 text-right">
                            <strong id="product-modal-count" style="margin-right: 10px;">0 dipilih</strong>
                            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                            <button type="button" class="btn btn-primary" id="product-modal-apply"><i class="fa fa-plus"></i> Tambahkan</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('page-script')
<script>
$(function () {
    const searchUrl = @json(route('campaign.products.search'));
    const scanUrl = @json(route('campaign.products.scan'));
    // Cache hanya untuk produk yang sudah terpilih / pernah muncul di hasil pencarian.
    const productCache = {};
    @json($selectedProductOptions).forEach(function (product) {
        productCache[String(product.id)] = product;
    });
    const rememberedQuantities = @json($selectedProducts);
    let bonusIndex = {{ count($bonusRows) }};
    let codeWasEdited = {{ old('code_auto', $isEdit ? 0 : 1) ? 'false' : 'true' }};

    $('.select2').select2({ width: '100%', allowClear: true });
    // Versi daterangepicker bawaan AdminLTE (v1.x) membaca opsi `format` di level atas,
    // bukan `locale.format`. Tanpa itu nilai awal "2026-10-13 10:00" dibaca sebagai MM/DD/YYYY
    // sehingga tahun menjadi 0000 saat edit. Kirim keduanya + startDate/endDate dari nilai input.
    const DATE_FORMAT = 'YYYY-MM-DD HH:mm';
    const pickerOptions = {
        timePicker: true,
        timePickerIncrement: 30,
        timePicker12Hour: false,
        timePicker24Hour: true,
        format: DATE_FORMAT,
        autoUpdateInput: false,
        locale: { format: DATE_FORMAT, cancelLabel: 'Clear' }
    };
    const existingRange = String($('#daterange').val() || '').split(' - ');
    if (existingRange.length === 2) {
        const existingStart = moment(existingRange[0].trim(), DATE_FORMAT, true);
        const existingEnd = moment(existingRange[1].trim(), DATE_FORMAT, true);
        if (existingStart.isValid() && existingEnd.isValid()) {
            pickerOptions.startDate = existingStart;
            pickerOptions.endDate = existingEnd;
        }
    }
    $('#daterange').daterangepicker(pickerOptions).on('apply.daterangepicker', function (event, picker) {
        $(this).val(picker.startDate.format(DATE_FORMAT) + ' - ' + picker.endDate.format(DATE_FORMAT));
    }).on('cancel.daterangepicker', function () {
        $(this).val('');
    });

    function escapeHtml(value) {
        return $('<div>').text(value ?? '').html();
    }

    function suggestedCode(name, type) {
        const slug = String(name || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 85);
        return (type === 'voucher' ? 'VCRL-' : 'PROMO-') + (slug || 'BARU');
    }

    function updateCode() {
        if (!codeWasEdited || !$('#campaign-code').val()) {
            if (!String($('#campaign-name').val() || '').trim()) {
                $('#campaign-code').val('');
                return;
            }
            $('#campaign-code').val(suggestedCode($('#campaign-name').val(), $('#campaign-type').val()));
            $('#code-auto').val('1');
        }
    }

    function rememberProductQuantities() {
        $('#selected-products-table .product-qty').each(function () {
            rememberedQuantities[String($(this).data('product-id'))] = $(this).val() || 1;
        });
    }

    function renderSelectedProducts() {
        rememberProductQuantities();
        const ids = ($('#product-picker').val() || []).map(String);
        const rows = ids.map(function (id) {
            const product = productCache[id];
            if (!product) return '';
            const quantity = rememberedQuantities[id] || 1;
            return `<tr data-product-id="${id}">
                <td>${escapeHtml(product.code)}</td>
                <td>${escapeHtml(product.name)}</td>
                <td class="required-qty-column"><input type="number" name="products[${id}]" class="form-control product-qty" data-product-id="${id}" min="0.01" step="0.01" value="${escapeHtml(quantity)}"></td>
                <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove-product" title="Hapus produk"><i class="fa fa-trash"></i></button></td>
            </tr>`;
        }).join('');
        $('#selected-products-table tbody').html(rows);
        // Baris dibuat ulang tiap render: kolom "Isi per bundle" hanya tampil untuk Bundle.
        $('.required-qty-column').toggle($('#campaign-type').val() === 'bundle');
        updatePickedCount();

        if (ids.length === 0) {
            $('#selected-products-table').hide();
            $('#no-products').show().text($('#campaign-type').val() === 'voucher'
                ? 'Tidak ada produk dipilih: voucher akan berlaku untuk semua produk.'
                : 'Pilih minimal satu produk untuk promo ini.');
        } else {
            $('#selected-products-table').show();
            $('#no-products').hide();
        }
    }

    let quotaTouched = {{ old('quota_qty') !== null ? 'true' : 'false' }};
    $('#quota-qty').on('input', function () { quotaTouched = true; });

    function updateTypeFields() {
        const type = $('#campaign-type').val();
        const isVoucher = type === 'voucher';
        const isBundle = type === 'bundle';
        const isPromotion = !isVoucher;

        if (isBundle) $('#discount-type').val('nominal');
        $('#discount-type option[value="percentage"]').prop('disabled', isBundle);
        $('.voucher-only').toggle(isVoucher).find('input').prop('disabled', !isVoucher);
        $('.campaign-max-qty').toggle(isPromotion).find('input').prop('disabled', !isPromotion);
        $('.promotion-only').toggle(isPromotion).find('input').prop('disabled', !isPromotion);
        $('.bundle-only').toggle(isBundle).find('input, button').prop('disabled', !isBundle);
        $('#discount-prefix').text($('#discount-type').val() === 'percentage' ? '%' : 'Rp');
        // "Isi per bundle" hanya dipakai Bundle. Flash Sale / Rafaksi / Voucher tidak membaca nilai ini.
        $('.required-qty-column').toggle(isBundle);
        $('#campaign-help').text(isVoucher
            ? 'Kode dimasukkan manual di kasir. Bisa berlaku untuk beberapa produk.'
            : (isBundle ? 'Pelanggan membeli produk pilihan dan mendapat potongan atau bonus.' : 'Potongan otomatis saat produk pilihan dibeli.'));
        $('#discount-help').text(isBundle ? 'Bundle selalu menggunakan nominal rupiah.' : 'Gunakan persentase atau nominal rupiah.');
        $('#quota-help').text(isVoucher
            ? 'Jumlah kode voucher yang dibuat. Maksimal 500 kode.'
            : (isBundle ? 'Jumlah bundle yang boleh digunakan.' : 'Jumlah unit yang boleh mendapat promo.') + ' Kosongkan jika tanpa batas kuota.');
        // Saat membuat baru (belum diubah manual): rafaksi default kosong (tanpa batas), voucher default 1 kode.
        if (!{{ $isEdit ? 'true' : 'false' }} && !quotaTouched) {
            const $quota = $('#quota-qty');
            if (type === 'flash_sale') {
                $quota.val('');
            } else if (isVoucher && $quota.val() === '') {
                $quota.val(1);
            }
        }
        $('#product-help').text(isVoucher
            ? 'Kosongkan pilihan agar voucher berlaku untuk semua produk. Pilih beberapa produk jika ingin membatasi voucher.'
            : (isBundle
                ? 'Pilih produk isi bundle. "Isi per bundle" adalah jumlah unit tiap produk dalam 1 paket (contoh: Sabun 2 + Shampoo 1).'
                : 'Pilih produk yang mendapat potongan rafaksi.'));
        $('#save-label').text({{ $isEdit ? 'true' : 'false' }} ? 'Simpan perubahan' : (isVoucher ? 'Simpan voucher' : 'Simpan promo'));
        updateCode();
        window.initCurrencyInputs?.();
    }

    $('#campaign-name').on('input', function () {
        if ($('#code-auto').val() === '1') codeWasEdited = false;
        updateCode();
    });
    $('#campaign-code').on('input', function () {
        codeWasEdited = true;
        $('#code-auto').val('0');
    });
    // Kode voucher wajib berawalan VCRL (server juga memastikannya).
    const originalCampaignCode = String($('#campaign-code').val() || '').trim().toUpperCase();
    $('#campaign-code').on('blur', function () {
        const value = String($(this).val() || '').trim().toUpperCase();
        if ($('#campaign-type').val() !== 'voucher' || !value || value === originalCampaignCode) return;
        const fixed = value.replace(/^VCR-/, '');
        $(this).val(fixed.startsWith('VCRL') ? fixed : 'VCRL-' + fixed);
    });
    $('#campaign-type, #discount-type').on('change', updateTypeFields);
    $('#product-picker').on('change', renderSelectedProducts);
    $(document).on('click', '.remove-product', function () {
        const id = String($(this).closest('tr').data('product-id'));
        const selected = ($('#product-picker').val() || []).map(String).filter(value => value !== id);
        $('#product-picker').val(selected).trigger('change');
    });
    $('#product-scan').on('keydown', function (event) {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        const input = this;
        const code = input.value.trim();
        if (!code) return;
        $.getJSON(scanUrl, { code: code }).done(function (product) {
            productCache[String(product.id)] = product;
            const selected = ($('#product-picker').val() || []).map(String);
            if (!selected.includes(String(product.id))) {
                // Tambahkan option baru lalu pilih, tanpa memuat daftar produk lain.
                if (!$('#product-picker option[value="' + product.id + '"]').length) {
                    $('#product-picker').append(new Option(product.text, product.id, true, true));
                }
                selected.push(String(product.id));
            }
            $('#product-picker').val(selected).trigger('change');
            input.value = '';
        }).fail(function (xhr) {
            alert((xhr.responseJSON && xhr.responseJSON.message) || 'Barcode produk tidak ditemukan.');
        });
    });
    $('#add-bonus').on('click', function () {
        const index = bonusIndex++;
        $('#bonus-rows').append(`<div class="row bonus-row" style="margin-bottom: 8px;">
            <div class="col-md-8"><input type="text" name="bonuses[${index}][name]" class="form-control" placeholder="Nama bonus"></div>
            <div class="col-md-3"><input type="number" name="bonuses[${index}][qty]" class="form-control" min="1" value="1" placeholder="Qty"></div>
            <div class="col-md-1"><button type="button" class="btn btn-danger remove-bonus" title="Hapus bonus"><i class="fa fa-trash"></i></button></div>
        </div>`);
    });
    $(document).on('click', '.remove-bonus', function () {
        $(this).closest('.bonus-row').remove();
    });

    // ===== Modal "Pilih Produk" (paging + cari dari server, pilihan bertahan antar halaman / pencarian) =====
    const modalSelected = {};   // id -> {id, code, name}
    let modalPage = 1;
    let modalTerm = '';
    let modalRequest = null;
    let modalTimer = null;

    function pickedIds() {
        return ($('#product-picker').val() || []).map(String);
    }

    function updatePickedCount() {
        const count = pickedIds().length;
        $('#picked-count').text(count ? count + ' produk dipilih' : 'Belum ada produk dipilih');
    }

    function setPickedProducts(ids) {
        ids.forEach(function (id) {
            if (!$('#product-picker option[value="' + id + '"]').length && productCache[id]) {
                $('#product-picker').append(new Option(productCache[id].code + ' — ' + productCache[id].name, id, true, true));
            }
        });
        $('#product-picker').val(ids).trigger('change');
    }

    function updateModalCount() {
        $('#product-modal-count').text(Object.keys(modalSelected).length + ' dipilih');
        const $checks = $('#product-modal-body .modal-product-check');
        const checked = $checks.filter(':checked').length;
        $('#product-modal-check-page')
            .prop('checked', $checks.length > 0 && checked === $checks.length)
            .prop('indeterminate', checked > 0 && checked < $checks.length);
    }

    function loadModalProducts() {
        if (modalRequest) modalRequest.abort();
        $('#product-modal-body').html('<tr><td colspan="3" class="text-center text-muted">Memuat produk...</td></tr>');
        $('#product-modal-prev, #product-modal-next').prop('disabled', true);
        modalRequest = $.getJSON(searchUrl, { q: modalTerm, page: modalPage }).done(function (data) {
            const rows = (data.results || []).map(function (item) {
                const id = String(item.id);
                productCache[id] = { id: item.id, code: item.code, name: item.name };
                return `<tr class="modal-product-row" style="cursor: pointer;">
                    <td><input type="checkbox" class="modal-product-check" value="${id}" ${modalSelected[id] ? 'checked' : ''}></td>
                    <td>${escapeHtml(item.code)}</td>
                    <td>${escapeHtml(item.name)}</td>
                </tr>`;
            }).join('');
            $('#product-modal-body').html(rows || '<tr><td colspan="3" class="text-center text-muted">Produk tidak ditemukan</td></tr>');
            $('#product-modal-page').text('Hal. ' + modalPage);
            $('#product-modal-prev').prop('disabled', modalPage <= 1);
            $('#product-modal-next').prop('disabled', !(data.pagination && data.pagination.more));
            updateModalCount();
        }).fail(function (xhr) {
            if (xhr.statusText === 'abort') return;
            $('#product-modal-body').html('<tr><td colspan="3" class="text-center text-danger">Gagal memuat produk. Coba lagi.</td></tr>');
        });
    }

    $('#open-product-modal').on('click', function () {
        Object.keys(modalSelected).forEach(function (key) { delete modalSelected[key]; });
        pickedIds().forEach(function (id) {
            modalSelected[id] = productCache[id] || { id: id, code: '', name: '' };
        });
        modalPage = 1;
        modalTerm = '';
        $('#product-modal-search').val('');
        $('#product-modal').modal('show');
        loadModalProducts();
    });
    $('#product-modal').on('shown.bs.modal', function () {
        $('#product-modal-search').trigger('focus');
    });
    $('#product-modal-search').on('keydown', function (event) {
        if (event.key === 'Enter') event.preventDefault();
    }).on('input', function () {
        const term = $(this).val().trim();
        clearTimeout(modalTimer);
        modalTimer = setTimeout(function () {
            if (term === modalTerm) return;
            modalTerm = term;
            modalPage = 1;
            loadModalProducts();
        }, 300);
    });
    $('#product-modal-prev').on('click', function () {
        if (modalPage > 1) { modalPage--; loadModalProducts(); }
    });
    $('#product-modal-next').on('click', function () {
        modalPage++;
        loadModalProducts();
    });
    $(document).on('change', '.modal-product-check', function () {
        const id = String(this.value);
        if (this.checked) {
            modalSelected[id] = productCache[id] || { id: id, code: '', name: '' };
        } else {
            delete modalSelected[id];
        }
        updateModalCount();
    });
    $(document).on('click', '.modal-product-row', function (event) {
        if ($(event.target).is('input')) return;
        $(this).find('.modal-product-check').trigger('click');
    });
    $('#product-modal-check-page').on('change', function () {
        const checked = this.checked;
        $('#product-modal-body .modal-product-check').each(function () {
            if (this.checked !== checked) $(this).trigger('click');
        });
    });
    $('#product-modal-apply').on('click', function () {
        // Urutan: produk yang sudah ada tetap di posisi semula, produk baru di bawahnya.
        const keep = pickedIds().filter(function (id) { return modalSelected[id]; });
        const added = Object.keys(modalSelected).filter(function (id) { return keep.indexOf(id) === -1; });
        setPickedProducts(keep.concat(added));
        $('#product-modal').modal('hide');
    });

    updateTypeFields();
    $('#product-picker').trigger('change');
    if (!$('#campaign-code').val()) updateCode();
});
</script>
@endsection