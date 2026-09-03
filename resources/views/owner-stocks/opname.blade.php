@extends('layouts.master')

@section('title', 'Stock Opname Toko')

@section('container')
<section class="content-header"><h1>Stock Opname Toko</h1></section>
<section class="content">
    <div class="box box-primary">
        <div class="box-header">
            <form class="form-inline" onsubmit="return loadOpname(event)">
                <label>Outlet</label>
                <select id="opname-outlet" class="form-control input-sm" required {{ auth()->user()->outlet_id ? 'disabled' : '' }}>
                    <option value="">Pilih outlet</option>
                    @foreach ($outlets as $outlet)<option value="{{ $outlet->id }}" {{ $selectedOwner?->id == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>@endforeach
                </select>
                <input type="date" id="adjustment-date" class="form-control input-sm" value="{{ date('Y-m-d') }}" required>
                <button class="btn btn-primary btn-sm">Muat stok</button>
            </form>
        </div>
        <div class="box-body">
            <form onsubmit="return saveOpname(event)">
                <table class="table table-bordered table-condensed"><thead><tr><th>Produk</th><th>Batch/SKU</th><th>System Qty</th><th>Physical Qty</th><th>Keterangan</th></tr></thead><tbody id="opname-rows"><tr><td colspan="5">Pilih outlet lalu muat stok.</td></tr></tbody></table>
                <button id="save-opname" class="btn btn-success" disabled>Simpan Opname</button>
            </form>
        </div>
    </div>
</section>
@endsection
@section('page-script')
<script>
let opnameStocks = [];
function outletId() { return document.getElementById('opname-outlet').value || '{{ auth()->user()->outlet_id }}'; }
function loadOpname(event) {
    event.preventDefault();
    fetch(`{{ route('owner-stock-opname.data') }}?outlet_id=${outletId()}`, {headers: {'Accept': 'application/json'}}).then(r => r.json()).then(data => {
        opnameStocks = data.stocks || [];
        document.getElementById('opname-rows').innerHTML = opnameStocks.map((stock, index) => `<tr><td>${stock.product_code} — ${stock.product_name}</td><td>${stock.batch_number || stock.serial_number || '-'}</td><td>${stock.qty}</td><td><input class="form-control input-sm physical-qty" data-index="${index}" type="number" min="0" step="1" value="${stock.qty}"></td><td><input class="form-control input-sm note" data-index="${index}" type="text"></td></tr>`).join('') || '<tr><td colspan="5">Belum ada stok.</td></tr>';
        document.getElementById('save-opname').disabled = !opnameStocks.length;
    });
    return false;
}
function saveOpname(event) {
    event.preventDefault();
    const items = opnameStocks.map((stock, index) => ({owner_stock_id: stock.id, physical_qty: document.querySelector(`.physical-qty[data-index="${index}"]`).value, keterangan: document.querySelector(`.note[data-index="${index}"]`).value}));
    fetch('{{ route('owner-stock-opname.save') }}', {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'}, body: JSON.stringify({outlet_id: outletId(), adjustment_date: document.getElementById('adjustment-date').value, items})}).then(r => r.json()).then(data => { if (!data.success) throw new Error(data.message); alert(data.message); loadOpname({preventDefault: () => {}}); }).catch(error => alert(error.message));
    return false;
}
</script>
@endsection
