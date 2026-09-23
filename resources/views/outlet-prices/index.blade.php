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
                @if ($selectedOutletId)
                    <form id="outlet-price-label-print-form" method="POST"
                        action="{{ route('cashier.print.products') }}" target="_blank"
                        style="display:inline-block; margin-left:8px;">
                        @csrf
                        <input type="hidden" name="print" value="1">
                        <input type="hidden" name="outlet_id" value="{{ $selectedOutletId }}">
                        <button type="submit" id="print-selected-outlet-price-labels" class="btn bg-purple" disabled>
                            <i class="fa fa-print"></i> Cetak label terpilih
                            (<span id="selected-outlet-price-count">0</span>)
                        </button>
                    </form>
                @endif
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
                            @if ($selectedOutletId)
                                <th class="text-center">
                                    <input type="checkbox" id="select-all-outlet-price-labels"
                                        title="Pilih semua produk pada halaman ini">
                                </th>
                            @endif
                            <th>Outlet</th>
                            <th>Produk</th>
                            <th>Harga Coret</th>
                            <th>Harga Jual POS</th>
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
                                @if ($selectedOutletId)
                                    <td class="text-center">
                                        @if ($price->product?->code)
                                            <input type="checkbox" class="outlet-price-label-checkbox"
                                                form="outlet-price-label-print-form" name="product_ids[]"
                                                value="{{ $price->product_id }}"
                                                aria-label="Pilih {{ $price->product?->name }}">
                                        @else
                                            <span class="text-muted" title="Produk belum memiliki barcode">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td>{{ $price->outlet?->name }}</td>
                                <td>{{ $price->product?->code }} — {{ $price->product?->name }}</td>
                                <td>
                                    <del>@currency($price->print_price_strike ?? 0)</del>
                                    <small class="text-muted">HPP setelah pajak + margin</small>
                                </td>
                                <td><strong>@currency($price->print_price_net ?? 0)</strong></td>
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
                                <td colspan="{{ $selectedOutletId ? 13 : 12 }}" class="text-center">
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
                function updateSelectedOutletPriceLabels() {
                    var selected = $('.outlet-price-label-checkbox:checked').length;
                    $('#selected-outlet-price-count').text(selected);
                    $('#print-selected-outlet-price-labels').prop('disabled', selected === 0);
                    $('#select-all-outlet-price-labels').prop(
                        'checked',
                        selected > 0 && selected === $('.outlet-price-label-checkbox').length
                    );
                }

                $('#select-all-outlet-price-labels').on('change', function () {
                    $('.outlet-price-label-checkbox').prop('checked', this.checked);
                    updateSelectedOutletPriceLabels();
                });

                $(document).on('change', '.outlet-price-label-checkbox', updateSelectedOutletPriceLabels);
                updateSelectedOutletPriceLabels();

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
