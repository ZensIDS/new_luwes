@extends('layouts.master')

@section('title', 'Master Harga Jual')

@php
    // Komponen harga yang digabung dalam satu kolom: label => [kolom nilai, kolom tipe]
    $priceComponents = [
        'Disc. Reguler' => ['disc_brand_value', 'disc_brand_type'],
        'Disc. Tambahan' => ['disc_tambahan_value', 'disc_tambahan_type'],
        'Margin' => ['margin_value', 'margin_type'],
        'Disc. Toko' => ['disc_toko_value', 'disc_toko_type'],
        'Pajak' => ['pajak_value', 'pajak_type'],
        'Peny. Outlet' => ['outlet_adjustment_value', 'outlet_adjustment_type'],
    ];
    $formatComponent = fn (float $value, ?string $type) => $type === 'percentage'
        ? rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . '%'
        : 'Rp' . number_format($value, 0, ',', '.');
@endphp

@section('container')
    <section class="content-header">
        <h1>Master Harga Jual <small>Aturan harga POS per outlet</small></h1>
    </section>
    <section class="content">
        <div class="box box-primary">
            <div class="box-header">
                {{-- <div class="alert alert-info">
                    Halaman ini <strong>tidak menambah atau mengurangi stok</strong>.
                    Gunanya mengatur harga aktif POS dengan urutan:
                    HPP → Pajak → HPP Setelah Pajak → Diskon Reguler → Diskon Tambahan → Margin →
                    Harga Aktif → Diskon Toko → Penyesuaian Outlet → Harga Netto.
                </div> --}}
                <a class="btn btn-success" href="{{ route('outlet-prices.create', array_filter(['outlet_id' => $selectedOutletId])) }}"><i class="fa fa-plus"></i> Tambah Aturan
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
                        placeholder="Awalan nama/kode produk">
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
                            <th>HPP</th>
                            <th>Harga Coret</th>
                            <th>Harga Jual</th>
                            <th>Catatan</th>
                            <th>Terakhir Diupdate</th>
                            <th>Stok Outlet</th>
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
                                    <button type="button" class="btn btn-xs btn-info btn-hpp-history"
                                        data-toggle="modal" data-target="#hppHistoryModal"
                                        data-url="{{ route('outlet-prices.hpp-history', $price) }}"
                                        data-product="{{ $price->product?->code }} — {{ $price->product?->name }}"
                                        title="Klik untuk melihat riwayat HPP">
                                        @currency($price->print_hpp ?? 0)
                                    </button>
                                </td>
                                <td>
                                    <del>@currency($price->print_price_strike ?? 0)</del>
                                    <small class="text-muted">HPP: @currency($price->print_price_hpp_after_tax ?? 0) + Margin: @currency($price->print_price_margin ?? 0)</small>
                                </td>
                                <td style="min-width:200px">
                                    <strong style="font-size:15px">@currency($price->print_price_net ?? 0)</strong>
                                    <div class="small" style="line-height:1.6;margin-top:4px">
                                        @foreach ($priceComponents as $label => $columns)
                                            @php $componentValue = (float) ($price->{$columns[0]} ?? 0); @endphp
                                            <div class="{{ $componentValue == 0 ? 'text-muted' : '' }}">
                                                <span style="display:inline-block;min-width:78px">{{ $label }}</span>
                                                : {{ $formatComponent($componentValue, $price->{$columns[1]}) }}
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                                <td style="min-width:160px;white-space:normal">{{ $price->note ?: '—' }}</td>
                                <td data-order="{{ $price->updated_at?->timestamp }}" style="white-space:nowrap">
                                    {{ $price->updated_at?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                @php
                                    $stockQty = (int) ($price->outlet_stock_qty ?? 0);
                                    $stockProduct = $price->product;
                                @endphp
                                <td style="white-space:nowrap" data-order="{{ $stockQty }}">
                                    <span class="{{ $stockQty <= 0 ? 'text-danger' : '' }}">
                                        {{ $stockQty }} {{ $stockProduct?->satuan ?? '' }}
                                    </span>
                                    @if ($stockQty > 0 && $stockProduct?->konversi_qty && $stockProduct?->satuan_besar)
                                        / <span class="label label-info">{{ $stockProduct->konversiDisplay($stockQty) }}</span>
                                    @endif
                                </td>
                                <td><a class="btn btn-xs btn-warning"
                                        href="{{ route('outlet-prices.edit', $price) }}">Edit</a>
                                    <form action="{{ route('outlet-prices.destroy', $price) }}" method="POST"
                                        style="display:inline">@csrf @method('DELETE')<button class="btn btn-xs btn-danger"
                                            onclick="return confirm('Hapus harga ini?')">Hapus</button></form>
                                </td>
                            </tr>
                        @empty
                            {{-- Kalau outlet dipilih, tabel adalah DataTable: JANGAN render <td colspan> (bikin error).
                                 Pesan kosong ditangani DataTables lewat language.emptyTable. --}}
                            @unless ($selectedOutletId)
                                <tr>
                                    <td colspan="9" class="text-center">Pilih outlet terlebih dahulu untuk melihat master harga.</td>
                                </tr>
                            @endunless
                        @endforelse
                    </tbody>
                </table>{{ $prices->links() }}

                <!-- HPP History Modal -->
                <div class="modal fade" id="hppHistoryModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                <h4 class="modal-title">Riwayat HPP</h4>
                                <small class="text-muted" id="hppHistoryProduct"></small>
                            </div>
                            <div class="modal-body">
                                <table class="table table-bordered table-sm">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>User</th>
                                            <th>Batch</th>
                                            <th>Perubahan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="hppHistoryBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
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

                // Modal riwayat HPP (cara kerjanya sama seperti Price History di index Product)
                function hppHistoryRow(cells) {
                    var $tr = $('<tr>');
                    cells.forEach(function (text) { $tr.append($('<td>').text(text)); });
                    return $tr;
                }

                function hppNumber(value) {
                    return Number(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
                }

                $('#hppHistoryModal').on('show.bs.modal', function (event) {
                    var button = $(event.relatedTarget);
                    var $body = $('#hppHistoryBody');
                    $('#hppHistoryProduct').text(button.data('product') || '');
                    $body.empty().append($('<tr>').append(
                        $('<td colspan="4" class="text-center">').text('Loading...')
                    ));

                    $.ajax({
                        url: button.data('url'),
                        method: 'GET',
                        success: function (res) {
                            $body.empty();
                            if (res.data && res.data.length) {
                                res.data.forEach(function (item) {
                                    var change = (item.event === 'created' || item.old === null)
                                        ? 'Dibuat → ' + hppNumber(item.new)
                                        : hppNumber(item.old) + ' → ' + hppNumber(item.new);
                                    $body.append(hppHistoryRow([item.date, item.user, item.batch, change]));
                                });
                            } else {
                                $body.append($('<tr>').append(
                                    $('<td colspan="4" class="text-center">').text('Belum ada riwayat perubahan HPP.')
                                ));
                            }
                        },
                        error: function () {
                            $body.empty().append($('<tr>').append(
                                $('<td colspan="4" class="text-center text-danger">').text('Gagal memuat data.')
                            ));
                        }
                    });
                });

                if ($.fn.DataTable.isDataTable('#example1')) {
                    $('#example1').DataTable().destroy();
                }
                // The query is already ordered by updated_at DESC. Keep that
                // order instead of letting DataTables sort by outlet name.
                $('#example1').DataTable({
                    dom: 'lrtip', // search bawaan (cocokkan di tengah kata, hanya 25 baris) dimatikan; pakai search server di atas
                    ordering: false,
                    pageLength: 25,
                    language: { emptyTable: 'Belum ada master harga untuk outlet ini.' }
                });
            });
        </script>
    @endif
@endsection