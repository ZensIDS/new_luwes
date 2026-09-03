@extends('layouts.master')

@section('title', 'Stock Toko')

@section('container')
<section class="content-header">
    <h1>Stock Toko <small>Saldo stok per outlet</small></h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-header">
            <div class="alert alert-info" style="margin-bottom:15px">
                <strong>Alur Stock Toko:</strong>
                stok utama berasal dari <strong>Delivery Order (Outbound)</strong> Gudang → Outlet.
                Pembelian langsung dari supplier adalah jalur alternatif dan otomatis masuk ke halaman ini.
            </div>
            <div style="margin-bottom:15px">
                @if (in_array(auth()->user()->role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet']))
                    <a href="{{ route('delivery-orders.index') }}" class="btn btn-default btn-sm"><i class="fa fa-truck"></i> Riwayat Delivery Order</a>
                    <a href="{{ route('outlet-purchases.create', ['outlet_id' => request('outlet_id')]) }}" class="btn btn-warning btn-sm"><i class="fa fa-shopping-basket"></i> Belanja Langsung → Tambah Stock</a>
                @endif
                @if (in_array(auth()->user()->role, ['superadmin', 'admin-gudang', 'owner']))
                    <a href="{{ route('outlet-prices.index') }}" class="btn btn-primary btn-sm"><i class="fa fa-money"></i> Atur Harga Jual POS</a>
                @endif
            </div>
            <form method="GET" class="form-inline">
                <label for="outlet_id">Outlet</label>
                <select id="outlet_id" name="outlet_id" class="form-control input-sm" onchange="this.form.submit()"
                    {{ auth()->user()->outlet_id ? 'disabled' : '' }}>
                    <option value="">Pilih outlet</option>
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}" {{ $selectedOwner?->id == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                    @endforeach
                </select>
                @if (auth()->user()->outlet_id)
                    <input type="hidden" name="outlet_id" value="{{ auth()->user()->outlet_id }}">
                @endif
                <input type="search" name="search" value="{{ request('search') }}" class="form-control input-sm"
                    placeholder="Cari produk / batch">
                <button class="btn btn-primary btn-sm">Cari</button>
            </form>
        </div>
        <div class="box-body table-responsive">
            @if (!$selectedOwner)
                <div class="alert alert-info">Pilih outlet untuk melihat stock toko.</div>
            @else
                <p><strong>{{ $selectedOwner->name }}</strong> — {{ $stocks->sum('qty') }} unit tersedia.</p>
                <table class="table table-bordered table-striped table-condensed">
                    <thead><tr><th>Kode</th><th>Produk</th><th>Batch/SKU</th><th>Sumber</th><th>HPP</th><th>Masuk</th><th>Keluar/Terjual</th><th>Adjustment</th><th>Saldo</th><th>Expired</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @forelse ($stocks as $stock)
                        @php
                            $sourceType = strtolower((string) $stock->source_type);
                            $sourceLabel = str_contains($sourceType, 'delivery') ? 'Delivery Order'
                                : (str_contains($sourceType, 'purchase') ? 'Belanja Langsung' : ($stock->source_type ?: 'Manual'));
                        @endphp
                        <tr>
                            <td>{{ $stock->product?->code ?? '-' }}</td>
                            <td>{{ $stock->product?->name ?? '-' }}</td>
                            <td>{{ $stock->batch_number ?: ($stock->stock?->serial_number ?: '-') }}</td>
                            <td>
                                @if ($stock->source_id && str_contains($sourceType, 'delivery'))
                                    <a href="{{ route('delivery-orders.show', $stock->source_id) }}">{{ $sourceLabel }} #{{ $stock->source_id }}</a>
                                @elseif ($stock->source_id && str_contains($sourceType, 'purchase'))
                                    <a href="{{ route('outlet-purchases.show', $stock->source_id) }}">{{ $sourceLabel }} #{{ $stock->source_id }}</a>
                                @else
                                    {{ $sourceLabel }}{{ $stock->source_id ? ' #' . $stock->source_id : '' }}
                                @endif
                            </td>
                            <td>@currency($stock->hpp)</td>
                            <td>{{ (int) ($stock->qty_in_total ?? 0) }}</td>
                            <td>{{ (int) ($stock->qty_out_total ?? 0) }}</td>
                            <td>{{ (int) (($stock->adjustment_in_total ?? 0) - ($stock->adjustment_out_total ?? 0)) }}</td>
                            <td><strong>{{ $stock->qty }}</strong> {{ $stock->product?->satuan }}</td>
                            <td>{{ optional($stock->expired_at)->format('Y-m-d') ?: '-' }}</td>
                            <td><a class="btn btn-xs btn-info" href="{{ route('owner-stocks.kartu', ['outlet_id' => $selectedOwner->id, 'product_id' => $stock->product_id]) }}">Kartu</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center">Belum ada stock toko.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</section>
@endsection
