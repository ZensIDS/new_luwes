@extends('layouts.master')

@section('title', 'Kartu Stock Toko')

@section('container')
<section class="content-header"><h1>Kartu Stock Toko</h1></section>
<section class="content">
    <div class="box box-primary">
        <div class="box-header">
            <form method="GET" class="form-inline">
                <label>Outlet</label>
                <select name="outlet_id" class="form-control input-sm" onchange="this.form.submit()" {{ auth()->user()->outlet_id ? 'disabled' : '' }}>
                    <option value="">Pilih outlet</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}" {{ $selectedOwner?->id == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                    @endforeach
                </select>
                @if (auth()->user()->outlet_id)<input type="hidden" name="outlet_id" value="{{ auth()->user()->outlet_id }}">@endif
                <select id="product_id" name="product_id" class="form-control input-sm" {{ !$selectedOwner ? 'disabled' : '' }} onchange="loadCard()">
                    <option value="">Pilih produk</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>{{ $product->code }} — {{ $product->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <div class="box-body">
            <div id="card-summary" class="alert alert-info">Pilih outlet dan produk.</div>
            <div class="table-responsive"><table class="table table-bordered table-condensed"><thead><tr><th>Tanggal</th><th>Tipe</th><th>Masuk</th><th>Keluar</th><th>Saldo</th><th>Keterangan</th></tr></thead><tbody id="card-rows"></tbody></table></div>
        </div>
    </div>
</section>
@endsection
@section('page-script')
<script>
function loadCard() {
    const outlet = document.querySelector('[name="outlet_id"]').value;
    const product = document.getElementById('product_id').value;
    if (!outlet || !product) return;
    fetch(`{{ route('owner-stocks.kartu.data') }}?outlet_id=${outlet}&product_id=${product}`, {headers: {'Accept': 'application/json'}})
        .then(response => response.json()).then(data => {
            document.getElementById('card-summary').textContent = `${data.product.name}: ${data.summary.qty} unit | Batch: ${data.summary.batches.length}`;
            document.getElementById('card-rows').innerHTML = data.transactions.map(row => `<tr><td>${row.date || '-'}</td><td>${row.type}</td><td>${row.qty_in}</td><td>${row.qty_out}</td><td>${row.balance}</td><td>${row.notes || '-'}</td></tr>`).join('') || '<tr><td colspan="6">Belum ada movement.</td></tr>';
        });
}
document.addEventListener('DOMContentLoaded', loadCard);
</script>
@endsection
