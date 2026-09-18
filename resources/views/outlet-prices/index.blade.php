@extends('layouts.master')

@section('title', 'Master Harga Jual')

@section('container')
    <section class="content-header">
        <h1>Master Harga Jual <small>Aturan harga POS per outlet</small></h1>
    </section>
    <section class="content">
        <div class="box box-primary">
            <div class="box-header">
                <div class="alert alert-info">
                    Halaman ini <strong>tidak menambah atau mengurangi stok</strong>.
                    Gunanya mengatur harga aktif POS dengan urutan:
                    HPP → Pajak → HPP Setelah Pajak → Diskon Reguler → Diskon Tambahan → Margin →
                    Harga Aktif → Diskon Toko → Penyesuaian Outlet → Harga Netto.
                </div>
                <a class="btn btn-success" href="{{ route('outlet-prices.create') }}"><i class="fa fa-plus"></i> Tambah Aturan
                    Harga</a>
                <a class="btn btn-default" href="{{ route('owner-stocks.index') }}"><i class="fa fa-cubes"></i> Lihat Stock
                    Toko</a>
                <form class="form-inline pull-right" method="GET">
                    <select class="form-control input-sm" name="outlet_id" onchange="this.form.submit()">
                        <option value="">Pilih outlet terlebih dahulu</option>
                        @foreach ($outlets as $outlet)
                            <option value="{{ $outlet->id }}" {{ $selectedOutletId == $outlet->id ? 'selected' : '' }}>
                                {{ $outlet->name }}</option>
                        @endforeach
                    </select>
                    <input class="form-control input-sm" name="search" value="{{ request('search') }}"
                        placeholder="Cari produk">
                    <button class="btn btn-primary btn-sm">Filter</button>
                </form>
            </div>
            <div class="box-body table-responsive">
                <table id="{{ $selectedOutletId ? 'example1' : 'outlet-prices-empty-table' }}" class="table table-bordered table-striped table-condensed">
                    <thead>
                        <tr>
                            <th>Outlet</th>
                            <th>Produk</th>
                            <th>Diskon Reguler</th>
                            <th>Diskon Tambahan</th>
                            <th>Margin</th>
                            <th>Diskon Toko</th>
                            <th>Pajak</th>
                            <th>Penyesuaian Outlet</th>
                            <th>Aktif</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($prices as $price)
                            <tr>
                                <td>{{ $price->outlet?->name }}</td>
                                <td>{{ $price->product?->code }} — {{ $price->product?->name }}</td>
                                <td>{{ $price->disc_brand_value }}{{ $price->disc_brand_type === 'percentage' ? '%' : '' }}
                                </td>
                                <td>{{ $price->disc_tambahan_value ?? 0 }}{{ $price->disc_tambahan_type === 'percentage' ? '%' : '' }}
                                </td>
                                <td>{{ $price->margin_value }}{{ $price->margin_type === 'percentage' ? '%' : '' }}</td>
                                <td>{{ $price->disc_toko_value ?? 0 }}{{ $price->disc_toko_type === 'percentage' ? '%' : '' }}
                                </td>
                                <td>{{ $price->pajak_value ?? 0 }}{{ $price->pajak_type === 'percentage' ? '%' : '' }}</td>
                                <td>{{ $price->outlet_adjustment_value ?? 0 }}{{ $price->outlet_adjustment_type === 'percentage' ? '%' : '' }}</td>
                                <td>{{ $price->is_active ? 'Ya' : 'Tidak' }}</td>
                                <td><a class="btn btn-xs btn-warning"
                                        href="{{ route('outlet-prices.edit', $price) }}">Edit</a>
                                    <form action="{{ route('outlet-prices.destroy', $price) }}" method="POST"
                                        style="display:inline">@csrf @method('DELETE')<button class="btn btn-xs btn-danger"
                                            onclick="return confirm('Hapus harga ini?')">Hapus</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">
                                    {{ $selectedOutletId ? 'Belum ada master harga untuk outlet ini.' : 'Pilih outlet terlebih dahulu untuk melihat master harga.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>{{ $prices->links() }}
            </div>
        </div>
    </section>
@endsection

@section('page-script')
    @if ($selectedOutletId)
        <script>
            $(function () {
                if ($.fn.DataTable.isDataTable('#example1')) {
                    $('#example1').DataTable().destroy();
                }
                // The query is already ordered by updated_at DESC. Keep that
                // order instead of letting DataTables sort by outlet name.
                $('#example1').DataTable({ ordering: false, pageLength: 25 });
            });
        </script>
    @endif
@endsection
