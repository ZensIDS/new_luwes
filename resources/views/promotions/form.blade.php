@extends('layouts.master')

@php
    $chosenProducts = old('products', $selectedProducts);
    $chosenOutlets = old('outlet_ids', $selectedOutlets);
    $bonusRows = old('bonuses', $bonuses->map(fn ($bonus) => ['name' => $bonus->name, 'qty' => $bonus->qty])->all());
@endphp

@section('title', $isEdit ? 'Edit Promotion' : 'Create Promotion')

@section('container')
<section class="content-header"><h1>{{ $isEdit ? 'Edit Promotion' : 'Create Promotion' }} <small>Simple rules for Rafaksi and bonus bundles</small></h1></section>
<section class="content">
    @if ($errors->any())<div class="alert alert-danger"><ul style="margin-bottom:0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $isEdit ? route('promotion.update', $promotion) : route('promotion.store') }}">
        @csrf @if ($isEdit) @method('PUT') @endif
        <div class="box box-primary"><div class="box-body">
            <div class="alert alert-info"><strong>How it works:</strong> choose the products the customer must buy, then configure either a discount (Rafaksi), a bundle saving, or a free bonus item. The bonus is your separate bonus stock—not outlet or warehouse stock.</div>
            <div class="row">
                <div class="col-md-6 form-group"><label>Promotion name</label><input name="name" class="form-control" value="{{ old('name', $promotion->name) }}" required></div>
                <div class="col-md-6 form-group"><label>Promotion code <small>(optional)</small></label><input name="code" class="form-control" value="{{ old('code', $promotion->code) }}"><small>If blank, an automatic scan code is generated.</small></div>
            </div>
            <div class="row">
                <div class="col-md-3 form-group"><label>Rule type</label><select id="promotion-type" name="type" class="form-control" required><option value="flash_sale" {{ old('type', $promotion->type) === 'flash_sale' ? 'selected' : '' }}>Rafaksi / discount</option><option value="bundle" {{ old('type', $promotion->type) === 'bundle' ? 'selected' : '' }}>Bundle / free bonus</option></select></div>
                <div class="col-md-3 form-group promotion-discount-field"><label>Discount type</label><select id="discount-type" name="discount_type" class="form-control"><option value="percentage" {{ old('discount_type', $promotion->discount_type) === 'percentage' ? 'selected' : '' }}>Percentage (%)</option><option value="nominal" {{ old('discount_type', $promotion->discount_type) === 'nominal' ? 'selected' : '' }}>Nominal (Rp)</option></select></div>
                <div class="col-md-3 form-group promotion-discount-field"><label>Discount value</label><div class="input-group"><span class="input-group-addon" id="discount-prefix">%</span><input name="discount_value" data-currency-input data-currency-toggle="discount-type" data-currency-decimals="0" class="form-control" value="{{ old('discount_value', $promotion->discount_value) }}" min="0"></div></div>
                <div class="col-md-3 form-group bundle-field"><label>Bundle saving <small>(optional)</small></label><div class="input-group"><span class="input-group-addon">Rp</span><input name="bundle_price" data-currency-input data-currency-decimals="0" class="form-control" value="{{ old('bundle_price', $promotion->bundle_price) }}" min="0"></div><small>Saving per qualifying bundle.</small></div>
            </div>
            <div class="row">
                <div class="col-md-3 form-group"><label id="max-qty-label">Max discounted units / transaction</label><input name="max_qty" type="number" min="1" class="form-control" value="{{ old('max_qty', $promotion->max_qty) }}"><small>Leave empty for unlimited.</small></div>
                <div class="col-md-3 form-group"><label>Total promo quota</label><input name="quota_qty" type="number" min="1" class="form-control" value="{{ old('quota_qty', $promotion->quota_qty) }}"><small>Rafaksi = units; bundle = bundles.</small></div>
                <div class="col-md-3 form-group"><label>Minimum purchase</label><div class="input-group"><span class="input-group-addon">Rp</span><input name="min_purchase" data-currency-input data-currency-decimals="0" class="form-control" value="{{ old('min_purchase', $promotion->min_purchase) }}" min="0"></div><small>Checked against the transaction subtotal.</small></div>
                <div class="col-md-3 form-group"><label>Active period</label><input type="text" name="daterange" id="promotion-daterange" class="form-control" value="{{ old('daterange', $promotion->start_at && $promotion->end_at ? $promotion->start_at->format('Y-m-d H:i').' - '.$promotion->end_at->format('Y-m-d H:i') : '') }}"><small>Use date and time.</small></div>
            </div>
            <div class="row">
                <div class="col-md-6 form-group"><label>Outlet scope</label><select name="outlet_ids[]" class="form-control select2" multiple data-placeholder="All outlets"><option value="">All outlets</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" {{ in_array($outlet->id, $chosenOutlets) ? 'selected' : '' }}>{{ $outlet->name }}</option>@endforeach</select><small>Leave empty for all outlets.</small></div>
                <div class="col-md-6 form-group"><label>Notes</label><input name="desc" class="form-control" value="{{ old('desc', $promotion->desc) }}"></div>
            </div>
            <div class="checkbox"><label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $promotion->is_active ?? true) ? 'checked' : '' }}> Active</label> &nbsp; <label><input type="checkbox" name="stackable" value="1" {{ old('stackable', $promotion->stackable ?? false) ? 'checked' : '' }}> Can stack with another selected automatic promotion</label></div>

            <hr><h4>Qualifying products</h4>
            <p class="help-block">Scan a product code or tick products below. The quantity is the number required for one discount or one bundle.</p>
            <input id="promotion-product-scan" class="form-control" placeholder="Scan product barcode and press Enter" style="margin-bottom:10px">
            <div class="table-responsive" style="max-height:360px;overflow:auto"><table class="table table-bordered table-striped"><thead><tr><th width="60">Use</th><th>Code</th><th>Product</th><th width="180">Qty required</th></tr></thead><tbody>
            @foreach ($products as $product)
                @php($selected = array_key_exists($product->id, $chosenProducts))
                <tr><td class="text-center"><input type="checkbox" class="promotion-product" data-product-id="{{ $product->id }}" data-code="{{ $product->code }}" {{ $selected ? 'checked' : '' }}></td><td>{{ $product->code }}</td><td>{{ $product->name }}</td><td><input type="number" name="products[{{ $product->id }}]" class="form-control product-qty" data-product-id="{{ $product->id }}" min="0.01" step="0.01" value="{{ $chosenProducts[$product->id] ?? 1 }}" {{ $selected ? '' : 'disabled' }}></td></tr>
            @endforeach
            </tbody></table></div>

            <div class="bundle-field" style="margin-top:20px"><h4>Free bonus items <small>(optional)</small></h4><p class="help-block">Examples: “Mug”, quantity 1; or “Product A”, quantity 1 for buy-one-get-one. These rows do not reduce stock.</p><div id="bonus-rows">
                @foreach($bonusRows as $index => $bonus)<div class="row bonus-row" style="margin-bottom:8px"><div class="col-md-8"><input name="bonuses[{{ $index }}][name]" class="form-control" placeholder="Bonus name" value="{{ $bonus['name'] ?? '' }}"></div><div class="col-md-3"><input name="bonuses[{{ $index }}][qty]" type="number" min="1" class="form-control" placeholder="Qty" value="{{ $bonus['qty'] ?? 1 }}"></div><div class="col-md-1"><button type="button" class="btn btn-danger remove-bonus"><i class="fa fa-trash"></i></button></div></div>@endforeach
            </div><button type="button" class="btn btn-default" id="add-bonus"><i class="fa fa-plus"></i> Add bonus item</button></div>
        </div><div class="box-footer"><a href="{{ route('promotion.index') }}" class="btn btn-default">Back</a> <button class="btn btn-primary"><i class="fa fa-save"></i> Save promotion</button></div></div>
    </form>
</section>
@endsection

@section('page-script')
<script>
$(function () {
    $('.select2').select2({ width: '100%', allowClear: true });
    $('#promotion-daterange').daterangepicker({timePicker:true,timePickerIncrement:30,autoUpdateInput:false,locale:{format:'YYYY-MM-DD HH:mm',cancelLabel:'Clear'}}).on('apply.daterangepicker', function (ev, picker) { $(this).val(picker.startDate.format('YYYY-MM-DD HH:mm')+' - '+picker.endDate.format('YYYY-MM-DD HH:mm')); }).on('cancel.daterangepicker', function () { $(this).val(''); });
    function updateTypeFields() {
        const isBundle = $('#promotion-type').val() === 'bundle';
        $('.promotion-discount-field').toggle(!isBundle).find('input,select').prop('disabled', isBundle);
        $('.bundle-field').toggle(isBundle);
        $('#max-qty-label').text(isBundle ? 'Max bundles / transaction' : 'Max discounted units / transaction');
        $('#discount-prefix').text($('#discount-type').val() === 'percentage' ? '%' : 'Rp');
        window.initCurrencyInputs?.();
    }
    $('#promotion-type, #discount-type').on('change', updateTypeFields); updateTypeFields();
    $('.promotion-product').on('change', function () { $('.product-qty[data-product-id="'+$(this).data('product-id')+'"]').prop('disabled', !this.checked); });
    $('#promotion-product-scan').on('keydown', function(event){ if(event.key !== 'Enter') return; event.preventDefault(); const code = this.value.trim().toLowerCase(); const checkbox = $('.promotion-product').filter(function(){ return String($(this).data('code')).toLowerCase() === code; }).first(); if(!checkbox.length){ alert('Product barcode not found.'); return; } checkbox.prop('checked', true).trigger('change'); this.value = ''; });
    let bonusIndex = {{ count($bonusRows) }};
    $('#add-bonus').on('click', function(){ const i = bonusIndex++; $('#bonus-rows').append(`<div class="row bonus-row" style="margin-bottom:8px"><div class="col-md-8"><input name="bonuses[${i}][name]" class="form-control" placeholder="Bonus name"></div><div class="col-md-3"><input name="bonuses[${i}][qty]" type="number" min="1" value="1" class="form-control" placeholder="Qty"></div><div class="col-md-1"><button type="button" class="btn btn-danger remove-bonus"><i class="fa fa-trash"></i></button></div></div>`); });
    $(document).on('click', '.remove-bonus', function(){ $(this).closest('.bonus-row').remove(); });
});
</script>
@endsection
