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
    $defaultQuota = $campaignType === 'voucher' ? 1 : $campaign?->quota_qty;
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
                            <option value="flash_sale" {{ $selectedType === 'flash_sale' ? 'selected' : '' }}>Flash Sale</option>
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
                        <input type="number" name="quota_qty" id="quota-qty" class="form-control" min="1" value="{{ old('quota_qty', $defaultQuota) }}" {{ $isEdit && $routeType === 'voucher' ? 'disabled' : '' }}>
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
                        <label for="product-picker">Pilih beberapa produk</label>
                        <select id="product-picker" class="form-control select2" multiple data-placeholder="Cari dan pilih produk">
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}" {{ array_key_exists($product->id, $selectedProducts) ? 'selected' : '' }}>{{ $product->code }} — {{ $product->name }}</option>
                            @endforeach
                        </select>
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
                                <th width="180" class="required-qty-column">Qty untuk 1 promo</th>
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
</section>
@endsection

@section('page-script')
<script>
$(function () {
    const products = @json($products->map(fn ($product) => ['id' => $product->id, 'code' => $product->code, 'name' => $product->name])->values());
    const rememberedQuantities = @json($selectedProducts);
    let bonusIndex = {{ count($bonusRows) }};
    let codeWasEdited = {{ old('code_auto', $isEdit ? 0 : 1) ? 'false' : 'true' }};

    $('.select2').select2({ width: '100%', allowClear: true });
    $('#daterange').daterangepicker({
        timePicker: true,
        timePickerIncrement: 30,
        autoUpdateInput: false,
        locale: { format: 'YYYY-MM-DD HH:mm', cancelLabel: 'Clear' }
    }).on('apply.daterangepicker', function (event, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD HH:mm') + ' - ' + picker.endDate.format('YYYY-MM-DD HH:mm'));
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
        return (type === 'voucher' ? 'VCR-' : 'PROMO-') + (slug || 'BARU');
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
            const product = products.find(item => String(item.id) === id);
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
        $('.required-qty-column').toggle(!isVoucher);
        $('#campaign-help').text(isVoucher
            ? 'Kode dimasukkan manual di kasir. Bisa berlaku untuk beberapa produk.'
            : (isBundle ? 'Pelanggan membeli produk pilihan dan mendapat potongan atau bonus.' : 'Potongan otomatis saat produk pilihan dibeli.'));
        $('#discount-help').text(isBundle ? 'Bundle selalu menggunakan nominal rupiah.' : 'Gunakan persentase atau nominal rupiah.');
        $('#quota-help').text(isVoucher
            ? 'Jumlah kode voucher yang dibuat. Maksimal 500 kode.'
            : (isBundle ? 'Jumlah bundle yang boleh digunakan.' : 'Jumlah unit yang boleh mendapat promo.'));
        $('#product-help').text(isVoucher
            ? 'Kosongkan pilihan agar voucher berlaku untuk semua produk. Pilih beberapa produk jika ingin membatasi voucher.'
            : 'Pilih produk yang menjadi syarat promo. Qty adalah unit untuk 1 promo atau isi 1 bundle.');
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
        const code = this.value.trim().toLowerCase();
        const product = products.find(item => String(item.code || '').toLowerCase() === code);
        if (!product) {
            alert('Barcode produk tidak ditemukan.');
            return;
        }
        const selected = ($('#product-picker').val() || []).map(String);
        if (!selected.includes(String(product.id))) selected.push(String(product.id));
        $('#product-picker').val(selected).trigger('change');
        this.value = '';
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

    updateTypeFields();
    $('#product-picker').trigger('change');
    if (!$('#campaign-code').val()) updateCode();
});
</script>
@endsection
