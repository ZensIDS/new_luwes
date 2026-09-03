@extends('layouts.master')

@section('title', 'Belanja Langsung → Tambah Stock Toko')

@section('container')
<section class="content-header"><h1>Belanja Langsung <small>Tambah Stock Toko</small></h1></section>
<section class="content"><form method="POST" action="{{ route('outlet-purchases.store') }}"><div class="box box-primary">
    @csrf
    <div class="box-body">
        <div class="alert alert-warning">
            <strong>Jalur alternatif Stock Toko.</strong>
            Form ini dipakai hanya saat outlet membeli langsung dari supplier.
            Jika stok dikirim dari gudang, prosesnya melalui <strong>Permintaan Barang → Delivery Order (Outbound)</strong>.
            Setelah disimpan, item otomatis tercatat di Stock Toko dan kartu stok.
        </div>
        <div class="row"><div class="col-md-3"><label>Outlet</label><select name="outlet_id" class="form-control" required {{ auth()->user()->outlet_id ? 'disabled' : '' }}><option value="">Pilih outlet</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" {{ old('outlet_id', $selectedOutletId) == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>@endforeach</select>@if(auth()->user()->outlet_id)<input type="hidden" name="outlet_id" value="{{ auth()->user()->outlet_id }}">@endif</div>
        <div class="col-md-3"><label>Supplier</label><select name="supplier_id" class="form-control" required><option value="">Pilih supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label>Tanggal</label><input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date', date('Y-m-d')) }}" required></div>
        <div class="col-md-3"><label>No. Nota Supplier</label><input name="invoice_number" class="form-control" value="{{ old('invoice_number') }}"></div></div>
        <hr><table class="table table-bordered" id="purchase-items"><thead><tr><th>Produk</th><th width="100">Qty</th><th width="160">Harga Beli</th><th>Batch</th><th width="150">Expired</th><th></th></tr></thead><tbody></tbody></table>
        <button type="button" class="btn btn-default" onclick="addPurchaseRow()">Tambah Item</button>
        <div class="row" style="margin-top:15px"><div class="col-md-3"><label>Jumlah Dibayar</label><div class="input-group"><span class="input-group-addon">Rp</span><input type="text" inputmode="numeric" data-currency-input data-currency-decimals="0" min="0" name="paid_amount" class="form-control" value="0"></div></div><div class="col-md-3"><label>Metode Pembayaran</label><input name="payment_method" class="form-control"></div><div class="col-md-6"><label>Catatan</label><input name="notes" class="form-control"></div></div>
    </div><div class="box-footer"><a href="{{ route('owner-stocks.index') }}" class="btn btn-default">Kembali ke Stock Toko</a> <button class="btn btn-primary">Simpan & Tambah Stock Toko</button></div>
</div></form></section>
@endsection
@section('page-script')<script>
const products = @json($products); let rowIndex = 0;
function productOptions() { return '<option value="">Pilih produk</option>' + products.map(p => `<option value="${p.id}">${p.code} — ${p.name}</option>`).join(''); }
function addPurchaseRow() { const index = rowIndex++; const row = document.createElement('tr'); row.innerHTML = `<td><select name="items[${index}][product_id]" class="form-control" required>${productOptions()}</select></td><td><input name="items[${index}][qty]" type="number" min="1" class="form-control" required></td><td><div class="input-group"><span class="input-group-addon">Rp</span><input name="items[${index}][harga_beli]" type="text" inputmode="numeric" data-currency-input data-currency-decimals="0" min="0" class="form-control" required></div></td><td><input name="items[${index}][batch_number]" class="form-control"></td><td><input name="items[${index}][expired_at]" type="date" class="form-control"></td><td><button type="button" class="btn btn-danger btn-xs" onclick="this.closest('tr').remove()">Hapus</button></td>`; document.querySelector('#purchase-items tbody').appendChild(row); window.initCurrencyInputs?.(row); }
addPurchaseRow();
</script>@endsection
