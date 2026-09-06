@extends('layouts.master')

@section('title', $isEdit ? 'Edit Promo' : 'Tambah Promo')

@section('container')
<section class="content-header"><h1>{{ $isEdit ? 'Edit Promo' : 'Tambah Promo' }}</h1></section>
<section class="content">
    <form method="POST" action="{{ $isEdit ? route('promotion.update', $promotion) : route('promotion.store') }}">
        @csrf @if ($isEdit) @method('PUT') @endif
        @php($chosenProducts = old('products', $selectedProducts))
        <div class="box box-primary">
            <div class="box-body">
                <div class="row">
                    <div class="col-md-6">
                        <label>Nama Promo</label>
                        <input name="name" class="form-control" value="{{ old('name', $promotion->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label>Kode Promo (opsional)</label>
                        <input name="code" class="form-control" value="{{ old('code', $promotion->code) }}">
                    </div>
                </div>
                <div class="row" style="margin-top:15px">
                    <div class="col-md-3">
                        <label>Tipe Promo</label>
                        <select id="promotion-type" name="type" class="form-control" required>
                            <option value="flash_sale" {{ old('type', $promotion->type) === 'flash_sale' ? 'selected' : '' }}>Flash sale</option>
                            <option value="bundle" {{ old('type', $promotion->type) === 'bundle' ? 'selected' : '' }}>Bundling</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Tipe Nilai Flash Sale</label>
                        <select id="discount-type" name="discount_type" class="form-control" required>
                            <option value="percentage" {{ old('discount_type', $promotion->discount_type) === 'percentage' ? 'selected' : '' }}>Diskon %</option>
                            <option value="nominal" {{ old('discount_type', $promotion->discount_type) === 'nominal' ? 'selected' : '' }}>Potongan nominal</option>
                            <option value="fixed_price" {{ old('discount_type', $promotion->discount_type) === 'fixed_price' ? 'selected' : '' }}>Harga promo tetap</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Nilai Flash Sale</label>
                        <div class="input-group"><span class="input-group-addon" id="discount-prefix">%</span><input name="discount_value" data-currency-input data-currency-toggle="discount-type" data-currency-decimals="0" class="form-control" value="{{ old('discount_value', $promotion->discount_value) }}" min="0" step="0.01" required></div>
                        <small>Untuk harga tetap, isi harga jual promo.</small>
                    </div>
                    <div class="col-md-3">
                        <label>Potongan Bundle</label>
                        <div class="input-group"><span class="input-group-addon">Rp</span><input name="bundle_price" data-currency-input data-currency-decimals="0" class="form-control" value="{{ old('bundle_price', $promotion->bundle_price) }}" min="0" step="0.01"><span class="input-group-addon">per bundle</span></div>
                    </div>
                </div>
                <div class="row" style="margin-top:15px">
                    <div class="col-md-3"><label>Maks. Qty / Produk</label><input name="max_qty" type="number" min="1" class="form-control" value="{{ old('max_qty', $promotion->max_qty) }}"><small>Untuk flash sale; kosong = tanpa batas.</small></div>
                    <div class="col-md-3"><label>Kuota Promo</label><input name="quota_qty" type="number" min="1" class="form-control" value="{{ old('quota_qty', $promotion->quota_qty) }}"><small>Flash sale: unit. Bundle: jumlah bundle.</small></div>
                    <div class="col-md-3"><label>Prioritas</label><input name="priority" type="number" min="0" class="form-control" value="{{ old('priority', $promotion->priority ?? 100) }}"><small>Angka lebih kecil diproses lebih dahulu.</small></div>
                    <div class="col-md-3"><label>Minimum Pembelian</label><div class="input-group"><span class="input-group-addon">Rp</span><input name="min_purchase" data-currency-input data-currency-decimals="0" class="form-control" value="{{ old('min_purchase', $promotion->min_purchase) }}" min="0" step="0.01"></div><small>Disiapkan untuk rule promo berikutnya.</small></div>
                </div>
                <div class="row" style="margin-top:15px">
                    <div class="col-md-6"><label>Outlet</label><select name="outlet_id" class="form-control"><option value="">Semua outlet</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" {{ old('outlet_id', $promotion->outlet_id) == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label>Rentang Aktif</label><input type="text" name="daterange" id="promotion-daterange" class="form-control" value="{{ old('daterange', $promotion->start_at && $promotion->end_at ? $promotion->start_at->format('Y-m-d H:i').' - '.$promotion->end_at->format('Y-m-d H:i') : '') }}"><small>Gunakan format waktu agar flash sale dapat diuji per jam.</small></div>
                </div>
                <div class="form-group" style="margin-top:15px"><label>Deskripsi</label><textarea name="desc" class="form-control" rows="2">{{ old('desc', $promotion->desc) }}</textarea></div>
                <div class="checkbox"><label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $promotion->is_active ?? true) ? 'checked' : '' }}> Aktif</label> &nbsp; <label><input type="checkbox" name="stackable" value="1" {{ old('stackable', $promotion->stackable ?? false) ? 'checked' : '' }}> Boleh ditumpuk dengan promo otomatis lain</label></div>

                <h4>Pilih Produk</h4>
                <p class="help-block">Centang produk yang ikut promo. Untuk bundling, isi kebutuhan quantity tiap produk per bundle.</p>
                <div class="table-responsive" style="max-height:380px;overflow:auto">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th width="60">Pilih</th><th>Kode</th><th>Produk</th><th width="180">Qty per promo/bundle</th></tr></thead>
                        <tbody>
                        @foreach ($products as $product)
                            @php($selected = array_key_exists($product->id, $chosenProducts))
                            <tr>
                                <td class="text-center"><input type="checkbox" class="promotion-product" data-product-id="{{ $product->id }}" {{ $selected ? 'checked' : '' }}></td>
                                <td>{{ $product->code }}</td>
                                <td>{{ $product->name }}</td>
                                <td><input type="number" name="products[{{ $product->id }}]" class="form-control product-qty" data-product-id="{{ $product->id }}" min="0.01" step="0.01" value="{{ $chosenProducts[$product->id] ?? 1 }}" {{ $selected ? '' : 'disabled' }}></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="box-footer"><a href="{{ route('promotion.index') }}" class="btn btn-default">Kembali</a> <button class="btn btn-primary">Simpan Promo</button></div>
        </div>
    </form>
</section>
@endsection

@section('page-script')
<script>
$(function () {
    $('#promotion-daterange').daterangepicker({timePicker:true,timePickerIncrement:30,autoUpdateInput:false,locale:{format:'YYYY-MM-DD HH:mm',cancelLabel:'Clear'}})
        .on('apply.daterangepicker', function (ev, picker) { $(this).val(picker.startDate.format('YYYY-MM-DD HH:mm')+' - '+picker.endDate.format('YYYY-MM-DD HH:mm')); })
        .on('cancel.daterangepicker', function () { $(this).val(''); });
    function updateTypeFields() {
        const isBundle = $('#promotion-type').val() === 'bundle';
        $('#discount-type').closest('.col-md-3').toggle(!isBundle);
        $('input[name="discount_value"]').closest('.col-md-3').toggle(!isBundle);
        $('input[name="bundle_price"]').closest('.col-md-3').toggle(isBundle);
        $('#discount-prefix').text($('#discount-type').val() === 'percentage' ? '%' : 'Rp');
        window.initCurrencyInputs?.();
    }
    $('#promotion-type, #discount-type').on('change', updateTypeFields);
    $('.promotion-product').on('change', function () { $('.product-qty[data-product-id="'+$(this).data('product-id')+'"]').prop('disabled', !this.checked); });
    updateTypeFields();
});
</script>
@endsection
