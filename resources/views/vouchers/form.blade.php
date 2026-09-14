@extends('layouts.master')

@php
    $selectedProducts = old('product_ids', $voucher->products->pluck('id')->all());
    if (empty($selectedProducts) && $voucher->product_id) $selectedProducts = [$voucher->product_id];
    $selectedOutlets = old('outlet_ids', $voucher->outlets->pluck('id')->all());
    if (empty($selectedOutlets) && $voucher->outlet_id) $selectedOutlets = [$voucher->outlet_id];
@endphp

@section('title', $isEdit ? 'Edit Voucher' : 'Create Voucher')

@section('container')
<section class="content-header"><h1>{{ $isEdit ? 'Edit Voucher' : 'Create Voucher' }} <small>Scan this code at POS</small></h1></section>
<section class="content">
    @if ($errors->any())<div class="alert alert-danger"><ul style="margin-bottom:0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $isEdit ? route('voucher.update', $voucher) : route('voucher.store') }}"><div class="box box-primary">
        @csrf @if($isEdit) @method('PUT') @endif
        <div class="box-body">
            <div class="row">
                <div class="col-md-6 form-group"><label>Voucher name</label><input name="name" class="form-control" value="{{ old('name', $voucher->name) }}" required></div>
                <div class="col-md-6 form-group"><label>Barcode / code <small>(optional)</small></label><input name="code" class="form-control" value="{{ old('code', $voucher->code) }}" {{ $isEdit ? 'readonly' : '' }}><small>If blank, Luwes generates a unique scan code automatically.</small></div>
            </div>
            <div class="row">
                <div class="col-md-3 form-group"><label>Discount type</label><select id="voucher-type" name="type" class="form-control" required><option value="nominal" {{ old('type', $voucher->type) === 'nominal' ? 'selected' : '' }}>Nominal (Rp)</option><option value="percentage" {{ old('type', $voucher->type) === 'percentage' ? 'selected' : '' }}>Percentage (%)</option></select></div>
                <div class="col-md-3 form-group"><label>Discount value</label><div class="input-group"><span class="input-group-addon" id="voucher-value-prefix">Rp</span><input name="value" type="text" inputmode="numeric" data-currency-input data-currency-decimals="0" class="form-control" value="{{ old('value', $voucher->value) }}" required></div></div>
                @if(!$isEdit)<div class="col-md-3 form-group"><label>Number of vouchers</label><input name="quantity" type="number" min="1" max="500" class="form-control" value="{{ old('quantity', 1) }}"><small>Each voucher receives its own barcode.</small></div>@endif
                <div class="col-md-{{ $isEdit ? 6 : 3 }} form-group"><label>Minimum purchase</label><div class="input-group"><span class="input-group-addon">Rp</span><input name="min_purchase" type="text" inputmode="numeric" data-currency-input data-currency-decimals="0" class="form-control" value="{{ old('min_purchase', $voucher->min_purchase) }}"></div><small>The voucher is rejected until this amount is reached.</small></div>
            </div>
            <div class="row">
                <div class="col-md-6 form-group"><label>Outlet scope <small>(optional)</small></label><select name="outlet_ids[]" class="form-control select2" multiple data-placeholder="All outlets"><option value="">All outlets</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" {{ in_array($outlet->id, $selectedOutlets) ? 'selected' : '' }}>{{ $outlet->name }}</option>@endforeach</select><small>Leave empty to make it available in every outlet.</small></div>
                <div class="col-md-6 form-group"><label>Product scope <small>(optional)</small></label><select id="voucher-products" name="product_ids[]" class="form-control select2" multiple data-placeholder="All products"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" data-code="{{ $product->code }}" {{ in_array($product->id, $selectedProducts) ? 'selected' : '' }}>{{ $product->code }} — {{ $product->name }}</option>@endforeach</select><small>Leave empty for all products. You can add products by scanning their barcode.</small></div>
            </div>
            <div class="form-group"><label for="voucher-product-scan">Scan product barcode to add to scope</label><input id="voucher-product-scan" class="form-control" placeholder="Scan a product code, then press Enter"><small class="text-muted">This selects products for the voucher; it does not change stock.</small></div>
            <div class="row"><div class="col-md-3 form-group"><label>Maximum discount <small>(optional)</small></label><div class="input-group"><span class="input-group-addon">Rp</span><input name="max_discount_amount" type="text" inputmode="numeric" data-currency-input data-currency-decimals="0" class="form-control" value="{{ old('max_discount_amount', $voucher->max_discount_amount) }}"></div></div><div class="col-md-9 form-group"><label>Notes</label><input name="desc" class="form-control" value="{{ old('desc', $voucher->desc) }}"></div></div>
            <div class="form-group"><label>Active period</label><input type="text" name="daterange" id="daterange" class="form-control" value="{{ old('daterange', $voucher->start_at && $voucher->end_at ? $voucher->start_at->format('Y-m-d H:i') . ' - ' . $voucher->end_at->format('Y-m-d H:i') : '') }}"><small>Leave empty for no date restriction.</small></div>
        </div>
        <div class="box-footer"><a href="{{ route('voucher.index') }}" class="btn btn-default">Back</a> <button class="btn btn-primary"><i class="fa fa-save"></i> Save voucher</button></div>
    </div></form>
</section>
@endsection

@section('page-script')
<script>
$(function(){
    $('.select2').select2({ width: '100%', allowClear: true });
    $('#daterange').daterangepicker({timePicker:true,timePickerIncrement:30,autoUpdateInput:false,locale:{format:'YYYY-MM-DD HH:mm',cancelLabel:'Clear'}}).on('apply.daterangepicker',function(ev,picker){$(this).val(picker.startDate.format('YYYY-MM-DD HH:mm')+' - '+picker.endDate.format('YYYY-MM-DD HH:mm'));}).on('cancel.daterangepicker',function(){$(this).val('');});
    $('#voucher-type').on('change', function(){ $('#voucher-value-prefix').text(this.value === 'percentage' ? '%' : 'Rp'); window.initCurrencyInputs?.(); });
    $('#voucher-value-prefix').text($('#voucher-type').val() === 'percentage' ? '%' : 'Rp');
    $('#voucher-products').on('select2:select', function(){ $(this).find('option[value=""]').prop('selected', false); });
    $('#voucher-product-scan').on('keydown', function(event){
        if (event.key !== 'Enter') return;
        event.preventDefault();
        const code = this.value.trim().toLowerCase();
        const option = $('#voucher-products option[data-code]').filter(function(){ return String($(this).data('code')).toLowerCase() === code; }).first();
        if (!option.length) { alert('Product barcode not found.'); return; }
        const selected = $('#voucher-products').val() || [];
        if (!selected.includes(String(option.val()))) selected.push(String(option.val()));
        $('#voucher-products').val(selected).trigger('change');
        this.value = '';
    });
});
</script>
@endsection
