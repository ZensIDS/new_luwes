@extends('layouts.master')

@section('title', 'Master Harga Jual')

@section('container')
<section class="content-header"><h1>Master Harga Jual <small>Aturan harga POS per outlet</small></h1></section>
<section class="content"><div class="box box-primary">
    <div class="box-header">
        <div class="alert alert-info">
            Halaman ini <strong>tidak menambah atau mengurangi stok</strong>.
            Gunanya mengatur harga aktif POS dengan urutan:
            HPP → Disc Brand → Harga Akhir → Margin → Harga Aktif → Disc Toko → Harga Netto.
        </div>
        <a class="btn btn-success" href="{{ route('outlet-prices.create') }}"><i class="fa fa-plus"></i> Tambah Aturan Harga</a>
        <a class="btn btn-default" href="{{ route('owner-stocks.index') }}"><i class="fa fa-cubes"></i> Lihat Stock Toko</a>
        <form class="form-inline pull-right" method="GET"><select class="form-control input-sm" name="outlet_id"><option value="">Semua outlet</option>@foreach($outlets as $outlet)<option value="{{ $outlet->id }}" {{ request('outlet_id') == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>@endforeach</select><input class="form-control input-sm" name="search" value="{{ request('search') }}" placeholder="Cari produk"><button class="btn btn-primary btn-sm">Filter</button></form>
    </div>
    <div class="box-body table-responsive"><table class="table table-bordered table-striped table-condensed"><thead><tr><th>Outlet</th><th>Produk</th><th>Disc Brand</th><th>Margin</th><th>Disc Toko</th><th>Aktif</th><th>Aksi</th></tr></thead><tbody>
    @forelse($prices as $price)<tr><td>{{ $price->outlet?->name }}</td><td>{{ $price->product?->code }} — {{ $price->product?->name }}</td><td>{{ $price->disc_brand_value }}{{ $price->disc_brand_type === 'percentage' ? '%' : '' }}</td><td>{{ $price->margin_value }}{{ $price->margin_type === 'percentage' ? '%' : '' }}</td><td>{{ $price->disc_toko_value }}{{ $price->disc_toko_type === 'percentage' ? '%' : '' }}</td><td>{{ $price->is_active ? 'Ya' : 'Tidak' }}</td><td><a class="btn btn-xs btn-warning" href="{{ route('outlet-prices.edit', $price) }}">Edit</a><form action="{{ route('outlet-prices.destroy', $price) }}" method="POST" style="display:inline">@csrf @method('DELETE')<button class="btn btn-xs btn-danger" onclick="return confirm('Hapus harga ini?')">Hapus</button></form></td></tr>@empty<tr><td colspan="7" class="text-center">Belum ada master harga.</td></tr>@endforelse
    </tbody></table>{{ $prices->links() }}</div>
</div></section>
@endsection
