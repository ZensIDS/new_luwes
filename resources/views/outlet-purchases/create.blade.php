@extends('layouts.master')

@section('title', 'Direct Outlet Purchase')

@section('container')
<section class="content-header"><h1>Direct Outlet Purchase <small>Add stock to the selected outlet</small></h1></section>
<section class="content"><form method="POST" action="{{ route('outlet-purchases.store') }}"><div class="box box-primary">
    @csrf
    <div class="box-body">
        <div class="alert alert-info">Use this form only when the outlet buys directly from a supplier. The batch number is generated automatically and payment fields are handled as internal defaults.</div>
        <div class="row">
            <div class="col-md-3"><label>Outlet</label><select name="outlet_id" class="form-control" required {{ auth()->user()->outlet_id ? 'disabled' : '' }}><option value="">Select outlet</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" {{ old('outlet_id', $selectedOutletId) == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>@endforeach</select>@if(auth()->user()->outlet_id)<input type="hidden" name="outlet_id" value="{{ auth()->user()->outlet_id }}">@endif</div>
            <div class="col-md-3"><label>Supplier</label><select id="purchase-supplier" name="supplier_id" class="form-control select2" required><option value="">Select supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ old('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label>Date</label><input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date', date('Y-m-d')) }}" required></div>
            <div class="col-md-3"><label>Supplier invoice no. <small>(optional)</small></label><input name="invoice_number" class="form-control" value="{{ old('invoice_number') }}"></div>
        </div>
        <hr>
        <div class="table-responsive"><table class="table table-bordered" id="purchase-items"><thead><tr><th>Product</th><th width="110">Qty</th><th width="180">Purchase price</th><th width="150">Expired</th><th width="60"></th></tr></thead><tbody></tbody></table></div>
        <button type="button" class="btn btn-default" id="add-purchase-row"><i class="fa fa-plus"></i> Add product</button>
        <input type="hidden" name="paid_amount" value="0">
        <input type="hidden" name="payment_method" value="Internal outlet purchase">
        <div class="form-group" style="margin-top:15px"><label>Notes <small>(optional)</small></label><input name="notes" class="form-control" value="{{ old('notes') }}"></div>
    </div><div class="box-footer"><a href="{{ route('owner-stocks.index') }}" class="btn btn-default">Back to outlet stock</a> <button class="btn btn-primary"><i class="fa fa-save"></i> Save and add stock</button></div>
</div></form></section>
@endsection

@section('page-script')
<script>
let products = [];
let rowIndex = 0;
let productRequest = null;

function productOptions() {
    return '<option value="">Select product</option>' + products.map(product => `<option value="${product.id}" data-price="${product.harga_beli || 0}">${product.code} — ${product.name}</option>`).join('');
}

function addPurchaseRow() {
    const index = rowIndex++;
    const batch = `OUTLET-${new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14)}-${index + 1}`;
    const row = $(`<tr>
        <td><input type="hidden" name="items[${index}][batch_number]" value="${batch}"><select name="items[${index}][product_id]" class="form-control select2 purchase-product" required>${productOptions()}</select></td>
        <td><input name="items[${index}][qty]" type="number" min="1" value="1" class="form-control purchase-qty" required></td>
        <td><div class="input-group"><span class="input-group-addon">Rp</span><input name="items[${index}][harga_beli]" type="text" inputmode="numeric" data-currency-input data-currency-decimals="0" class="form-control purchase-price" required></div></td>
        <td><input name="items[${index}][expired_at]" type="date" class="form-control"></td>
        <td><button type="button" class="btn btn-danger btn-xs remove-purchase-row"><i class="fa fa-trash"></i></button></td>
    </tr>`);
    $('#purchase-items tbody').append(row);
    row.find('.select2').select2({ width: '100%' });
    window.initCurrencyInputs?.(row[0]);
}

function loadSupplierProducts(supplierId) {
    products = [];
    $('#purchase-items tbody').empty();
    if (!supplierId) { addPurchaseRow(); return; }
    if (productRequest) productRequest.abort();
    productRequest = $.get('{{ route('pembelian.all-products') }}', { supplier_id: supplierId })
        .done(data => { products = data || []; addPurchaseRow(); })
        .fail(xhr => { if (xhr.statusText !== 'abort') alert('Unable to load products for this supplier.'); addPurchaseRow(); })
        .always(() => { productRequest = null; });
}

$(function () {
    $('#purchase-supplier').select2({ width: '100%', placeholder: 'Select supplier' });
    $('#purchase-supplier').on('change', function () { loadSupplierProducts(this.value); });
    $('#add-purchase-row').on('click', addPurchaseRow);
    $(document).on('click', '.remove-purchase-row', function () {
        if ($('#purchase-items tbody tr').length > 1) $(this).closest('tr').remove();
    });
    $(document).on('change', '.purchase-product', function () {
        const price = $(this).find('option:selected').data('price') || 0;
        $(this).closest('tr').find('.purchase-price').val(price).trigger('input');
    });
    loadSupplierProducts($('#purchase-supplier').val());
});
</script>
@endsection
