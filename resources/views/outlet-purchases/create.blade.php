@extends('layouts.master')

@section('title', $purchase ? 'Edit Belanja Langsung' : 'Tambah Belanja Langsung')

@section('container')
<section class="content-header">
    <h1>
        {{ $purchase ? 'Edit' : 'Tambah' }} Belanja Langsung
        <small>{{ $purchase ? $purchase->code : 'Pembelian outlet langsung dari supplier' }}</small>
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

    <form method="POST" action="{{ $purchase ? route('outlet-purchases.update', $purchase) : route('outlet-purchases.store') }}">
    @csrf
    @if ($purchase)
        @method('PUT')
    @endif
    <div class="box box-primary">
    <div class="box-body">
        <div class="alert alert-info">
            Gunakan form ini hanya jika outlet membeli langsung dari supplier. Nomor batch dibuat otomatis.
            Setelah disimpan, <strong>stock toko belum bertambah</strong>: barang diterima dulu lewat tombol <em>Lihat</em> di daftar
            (isi qty yang datang). Selama belum completed, transaksi ini masih bisa diedit.
        </div>
        <div class="row">
            <div class="col-md-3"><label>Outlet</label><select name="outlet_id" class="form-control" required {{ auth()->user()->outlet_id ? 'disabled' : '' }}><option value="">Pilih outlet</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" {{ old('outlet_id', $selectedOutletId) == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>@endforeach</select>@if(auth()->user()->outlet_id)<input type="hidden" name="outlet_id" value="{{ auth()->user()->outlet_id }}">@endif</div>
            <div class="col-md-3"><label>Supplier</label><select id="purchase-supplier" name="supplier_id" class="form-control select2" required><option value="">Pilih supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" {{ old('supplier_id', $purchase?->supplier_id) == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label>Tanggal</label><input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date', $purchase?->purchase_date?->format('Y-m-d') ?? date('Y-m-d')) }}" required></div>
            <div class="col-md-3"><label>No. nota supplier <small>(opsional)</small></label><input name="invoice_number" class="form-control" value="{{ old('invoice_number', $purchase?->invoice_number) }}"></div>
        </div>
        <hr>
        <div class="table-responsive"><table class="table table-bordered" id="purchase-items"><thead><tr><th>Produk</th><th width="110">Qty Dipesan</th><th width="180">Harga beli</th><th width="150">Expired</th><th width="60"></th></tr></thead><tbody></tbody></table></div>
        <button type="button" class="btn btn-default" id="add-purchase-row"><i class="fa fa-plus"></i> Tambah produk</button>
        <input type="hidden" name="paid_amount" value="0">
        <input type="hidden" name="payment_method" value="Internal outlet purchase">
        <div class="form-group" style="margin-top:15px"><label>Catatan <small>(opsional)</small></label><input name="notes" class="form-control" value="{{ old('notes', $purchase?->notes) }}"></div>
    </div>
    <div class="box-footer">
        <a href="{{ $purchase ? route('outlet-purchases.show', $purchase) : route('outlet-purchases.index') }}" class="btn btn-default">Kembali</a>
        <button class="btn btn-primary"><i class="fa fa-save"></i> {{ $purchase ? 'Simpan Perubahan' : 'Simpan' }}</button>
    </div>
    </div>
    </form>
</section>
@endsection

@section('page-script')
<script>
let products = [];
let rowIndex = 0;
let productRequest = null;
const initialItems = @json($initialItems);

function escapeHtml(text) {
    return $('<div>').text(text == null ? '' : text).html();
}

function productOptions(selectedId, fallbackLabel) {
    let list = products.slice();
    // Produk pada baris yang sedang diedit bisa saja tidak ada di daftar produk supplier saat ini.
    if (selectedId && !list.some(product => String(product.id) === String(selectedId))) {
        list.push({ id: selectedId, label: fallbackLabel || ('Produk #' + selectedId), harga_beli: 0 });
    }
    return '<option value="">Pilih produk</option>' + list.map(product => {
        const label = product.label || `${product.code} — ${product.name}`;
        const selected = String(product.id) === String(selectedId) ? ' selected' : '';
        return `<option value="${product.id}" data-price="${product.harga_beli || 0}"${selected}>${escapeHtml(label)}</option>`;
    }).join('');
}

function addPurchaseRow(item) {
    item = item || null;
    const index = rowIndex++;
    const batch = (item && item.batch_number) || `OUTLET-${new Date().toISOString().replace(/[-:TZ.]/g, '').slice(0, 14)}-${index + 1}`;
    const qty = item && item.qty ? item.qty : 1;
    const price = item && item.harga_beli !== '' && item.harga_beli != null ? item.harga_beli : '';
    const expired = item && item.expired_at ? item.expired_at : '';
    const row = $(`<tr>
        <td><input type="hidden" name="items[${index}][batch_number]" value="${escapeHtml(batch)}"><select name="items[${index}][product_id]" class="form-control select2 purchase-product" required>${productOptions(item && item.product_id, item && item.label)}</select></td>
        <td><input name="items[${index}][qty]" type="number" min="1" value="${qty}" class="form-control purchase-qty" required></td>
        <td><div class="input-group"><span class="input-group-addon">Rp</span><input name="items[${index}][harga_beli]" type="text" inputmode="numeric" data-currency-input data-currency-decimals="0" value="${price}" class="form-control purchase-price" required></div></td>
        <td><input name="items[${index}][expired_at]" type="date" value="${expired}" class="form-control"></td>
        <td><button type="button" class="btn btn-danger btn-xs remove-purchase-row"><i class="fa fa-trash"></i></button></td>
    </tr>`);
    $('#purchase-items tbody').append(row);
    row.find('.select2').select2({ width: '100%' });
    window.initCurrencyInputs?.(row[0]);
}

function fillRows(useInitial) {
    if (useInitial && initialItems.length) {
        initialItems.forEach(item => addPurchaseRow(item));
    } else {
        addPurchaseRow();
    }
}

function loadSupplierProducts(supplierId, useInitial) {
    products = [];
    $('#purchase-items tbody').empty();
    if (!supplierId) { fillRows(useInitial); return; }
    if (productRequest) productRequest.abort();
    productRequest = $.get('{{ route('pembelian.all-products') }}', { supplier_id: supplierId })
        .done(data => { products = data || []; fillRows(useInitial); })
        .fail(xhr => { if (xhr.statusText !== 'abort') alert('Gagal memuat produk supplier ini.'); fillRows(useInitial); })
        .always(() => { productRequest = null; });
}

$(function () {
    $('#purchase-supplier').select2({ width: '100%', placeholder: 'Pilih supplier' });
    // Ganti supplier oleh user: daftar produk dimuat ulang dan baris dikosongkan.
    $('#purchase-supplier').on('change', function () { loadSupplierProducts(this.value, false); });
    $('#add-purchase-row').on('click', () => addPurchaseRow());
    $(document).on('click', '.remove-purchase-row', function () {
        if ($('#purchase-items tbody tr').length > 1) $(this).closest('tr').remove();
    });
    $(document).on('change', '.purchase-product', function () {
        const price = $(this).find('option:selected').data('price') || 0;
        $(this).closest('tr').find('.purchase-price').val(price).trigger('input');
    });
    // Muat awal: pakai item yang sudah ada (mode edit / validasi gagal).
    loadSupplierProducts($('#purchase-supplier').val(), true);
});
</script>
@endsection