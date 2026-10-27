@extends('layouts.master')

@section('title', 'Master Harga Jual')

@php
    // Kolom komponen harga (urutan sama dengan tabel): label => [kolom nilai, kolom tipe]
    $componentColumns = [
        'Pajak' => ['pajak_value', 'pajak_type'],
        'Disc Reg' => ['disc_brand_value', 'disc_brand_type'],
        'Disc 1' => ['disc_tambahan_value', 'disc_tambahan_type'],
        'Disc 2' => ['disc_tambahan_2_value', 'disc_tambahan_2_type'],
        'Disc Toko' => ['disc_toko_value', 'disc_toko_type'],
        'Margin' => ['margin_value', 'margin_type'],
    ];
    $formatComponent = fn (float $value, ?string $type) => $type === 'percentage'
        ? rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',') . '%'
        : 'Rp' . number_format($value, 0, ',', '.');
@endphp

@section('container')
    <style>
        /* Catatan boleh turun baris; kolom lain mengikuti text-nowrap seperti halaman product. */
        /* Header tabel product memakai <td> (tidak tebal); di sini tetap <th> karena DataTables, jadi ketebalannya disamakan. */
        #example1 thead th, #outlet-prices-empty-table thead th { font-weight: normal; }
        #example1 td.op-note { min-width: 160px; white-space: normal; }

        /* Card folder (kata pertama nama produk): ringan, tanpa gambar. */
        .opf-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
        .opf-toolbar .opf-hint { color:#777; }
        .opf-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:12px; }
        .opf-card { display:flex; flex-direction:column; gap:4px; padding:14px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;
            color:#333; text-decoration:none; transition:box-shadow .15s, border-color .15s, transform .15s; min-width:0; }
        .opf-card:hover, .opf-card:focus { color:#333; text-decoration:none; border-color:#605ca8; box-shadow:0 3px 10px rgba(96,92,168,.18); transform:translateY(-1px); }
        .opf-icon { font-size:22px; color:#f0ad4e; line-height:1; }
        .opf-name { font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .opf-count { font-size:12px; color:#777; }
        .opf-card-all { background:#605ca8; border-color:#605ca8; color:#fff; }
        .opf-card-all:hover, .opf-card-all:focus { color:#fff; border-color:#4b4787; box-shadow:0 3px 10px rgba(96,92,168,.35); }
        .opf-card-all .opf-icon, .opf-card-all .opf-count { color:#fff; }
        .opf-empty { display:none; padding:20px 0; color:#777; }
        .opf-crumb { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
        .opf-crumb .opf-path { color:#555; }

        /* Popup Export Excel master harga */
        .pm-option { display:block; margin:0 0 10px; padding:12px 14px; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; font-weight:normal; transition:border-color .15s, background .15s; }
        .pm-option:hover { border-color:#b9b6e0; }
        .pm-option.active { border-color:#605ca8; background:#f6f5fc; }
        .pm-option input[type=radio] { margin:0 8px 0 0; vertical-align:middle; }
        .pm-option .pm-title { font-weight:600; vertical-align:middle; }
        .pm-option .pm-desc { display:block; margin:2px 0 0 22px; color:#777; font-size:12px; }
        .pm-option .pm-select { margin:8px 0 0 22px; }
    </style>
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
                    <button type="button" class="btn btn-default" data-toggle="modal" data-target="#exportMasterModal">
                        <i class="fa fa-file-excel-o"></i> Export Excel</button>
                @endif
                @if ($mode === 'products')
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
            @if ($mode === 'folders')
                <div class="box-body">
                    <div class="opf-toolbar">
                        <span class="opf-hint">
                            <i class="fa fa-folder-open-o"></i> Pilih folder (kata pertama nama produk), atau ketik awalan nama/kode di kotak pencarian atas untuk mencari di semua produk.
                        </span>
                        @if (count($folders) > 12)
                            <input type="text" id="opf-filter" class="form-control input-sm" style="max-width:240px;"
                                placeholder="Cari folder..." autocomplete="off">
                        @endif
                    </div>

                    @if ($totalAll > 0)
                        <div class="opf-grid" id="opf-grid">
                            <a class="opf-card opf-card-all"
                                href="{{ route('outlet-prices.index', ['outlet_id' => $selectedOutletId, 'all' => 1]) }}">
                                <span class="opf-icon"><i class="fa fa-th-large"></i></span>
                                <span class="opf-name">All Product</span>
                                <span class="opf-count">{{ number_format($totalAll, 0, ',', '.') }} produk</span>
                            </a>
                            @foreach ($folders as $folder)
                                <a class="opf-card" data-name="{{ \Illuminate\Support\Str::lower($folder['label']) }}"
                                    title="{{ $folder['label'] }}"
                                    href="{{ route('outlet-prices.index', ['outlet_id' => $selectedOutletId, 'folder' => $folder['key']]) }}">
                                    <span class="opf-icon"><i class="fa fa-folder"></i></span>
                                    <span class="opf-name">{{ $folder['label'] }}</span>
                                    <span class="opf-count">{{ number_format($folder['total'], 0, ',', '.') }} produk</span>
                                </a>
                            @endforeach
                        </div>
                        <div class="opf-empty" id="opf-empty">Folder tidak ditemukan.</div>
                    @else
                        <p class="text-center text-muted" style="padding:20px 0;">Belum ada master harga untuk outlet ini.</p>
                    @endif
                </div>
            @else
            <div class="box-body table-responsive text-nowrap">
                @if ($mode === 'products')
                    <div class="opf-crumb">
                        <a class="btn btn-default btn-sm"
                            href="{{ route('outlet-prices.index', ['outlet_id' => $selectedOutletId]) }}">
                            <i class="fa fa-arrow-left"></i> Kembali ke folder</a>
                        <span class="opf-path">
                            <i class="fa fa-folder-open-o"></i> Master Harga Jual &rsaquo;
                            @if ($searchTerm !== '')
                                Hasil pencarian &ldquo;{{ $searchTerm }}&rdquo; (semua produk)
                            @elseif ($activeFolder !== null)
                                Folder <strong>{{ $activeFolder === \App\Services\PriceFolderIndex::OTHER ? \App\Services\PriceFolderIndex::OTHER_LABEL : $activeFolder }}</strong>
                            @else
                                All Product
                            @endif
                            &middot; {{ number_format($prices->total(), 0, ',', '.') }} produk
                        </span>
                    </div>
                @endif
                <table id="{{ $selectedOutletId ? 'example1' : 'outlet-prices-empty-table' }}" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            @if ($selectedOutletId)
                                <th class="text-center">
                                    <input type="checkbox" id="select-all-outlet-price-labels"
                                        title="Pilih semua produk pada halaman ini">
                                </th>
                            @endif
                            <th>Barcode</th>
                            <th>Nama Product</th>
                            <th>HPP</th>
                            @foreach ($componentColumns as $label => $columns)
                                <th class="text-center">{{ $label }}</th>
                            @endforeach
                            <th class="text-center">Harga Jual</th>
                            <th class="text-center">Penyesuaian</th>
                            <th>Catatan</th>
                            <th>Terakhir Update</th>
                            <th>Stock Outlet</th>
                            <th>Action</th>
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
                                <td>{{ $price->product?->code ?: '—' }}</td>
                                <td>{{ $price->product?->name }}</td>
                                <td>
                                    <button type="button" class="btn btn-xs btn-info btn-hpp-history"
                                        data-toggle="modal" data-target="#hppHistoryModal"
                                        data-url="{{ route('outlet-prices.hpp-history', $price) }}"
                                        data-product="{{ $price->product?->code }} — {{ $price->product?->name }}"
                                        title="Klik untuk melihat riwayat HPP">
                                        @currency($price->print_hpp ?? 0)
                                    </button>
                                    @php
                                        $hppKonversi = (float) ($price->product?->konversi_qty ?? 0);
                                    @endphp
                                    <div><small class="text-muted">/ {{ $price->product?->satuan ?? 'satuan kecil' }}</small></div>
                                    @if ($hppKonversi >= 1)
                                        <div>
                                            <small class="text-muted">
                                                @currency(round(($price->print_hpp ?? 0) * $hppKonversi, 2))
                                                / {{ $price->product?->satuan_besar ?? 'satuan besar' }}
                                            </small>
                                        </div>
                                    @endif
                                </td>
                                @foreach ($componentColumns as $label => $columns)
                                    @php $componentValue = (float) ($price->{$columns[0]} ?? 0); @endphp
                                    <td class="text-center {{ $componentValue == 0 ? 'text-muted' : '' }}">
                                        {{ $componentValue == 0 ? '—' : $formatComponent($componentValue, $price->{$columns[1]}) }}
                                    </td>
                                @endforeach
                                <td class="text-center">@currency($price->print_price_net ?? 0)</td>
                                @php $adjustmentValue = (float) ($price->outlet_adjustment_value ?? 0); @endphp
                                <td class="text-center {{ $adjustmentValue == 0 ? 'text-muted' : '' }}">
                                    {{ $adjustmentValue == 0 ? '—' : $formatComponent($adjustmentValue, $price->outlet_adjustment_type) }}
                                </td>
                                <td class="op-note">{{ $price->note ?: '—' }}</td>
                                <td data-order="{{ $price->updated_at?->timestamp }}">
                                    {{ $price->updated_at?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                @php
                                    $stockQty = (int) ($price->outlet_stock_qty ?? 0);
                                    $stockProduct = $price->product;
                                @endphp
                                <td data-order="{{ $stockQty }}">
                                    <span class="{{ $stockQty <= 0 ? 'text-danger' : '' }}">
                                        {{ $stockQty }} {{ $stockProduct?->satuan ?? '' }}
                                    </span>
                                    @if ($stockQty > 0 && $stockProduct?->konversi_qty && $stockProduct?->satuan_besar)
                                        / <span class="label label-info">{{ $stockProduct->konversiDisplay($stockQty) }}</span>
                                    @endif
                                </td>
                                <td><a class="btn btn-warning"
                                        href="{{ route('outlet-prices.edit', $price) }}">Edit</a>
                                    <form action="{{ route('outlet-prices.destroy', $price) }}" method="POST"
                                        style="display:inline">@csrf @method('DELETE')<button class="border-0 btn btn-danger"
                                            onclick="return confirm('Hapus harga ini?')">Hapus</button></form>
                                </td>
                            </tr>
                        @empty
                            {{-- Kalau outlet dipilih, tabel adalah DataTable: JANGAN render <td colspan> (bikin error).
                                 Pesan kosong ditangani DataTables lewat language.emptyTable. --}}
                            @unless ($selectedOutletId)
                                <tr>
                                    <td colspan="15" class="text-center">Pilih outlet terlebih dahulu untuk melihat master harga.</td>
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
            @endif
        </div>
    </section>

    @if ($selectedOutletId)
        {{-- Popup Export Excel master harga: semua barang outlet, atau satu folder. --}}
        <div class="modal fade" id="exportMasterModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="GET" action="{{ route('outlet-prices.export') }}" id="export-master-form">
                    <input type="hidden" name="outlet_id" value="{{ $selectedOutletId }}">
                    <div class="modal-content">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title"><i class="fa fa-file-excel-o"></i> Export Excel Master Harga Jual</h4>
                            <small class="text-muted">Outlet: {{ $outlets->firstWhere('id', $selectedOutletId)?->name ?? '-' }}</small>
                        </div>
                        <div class="modal-body">
                            @if ($totalAll > 0)
                                <label class="pm-option">
                                    <input type="radio" name="scope" value="all" {{ $activeFolder === null ? 'checked' : '' }}>
                                    <span class="pm-title">Semua barang (outlet terpilih)</span>
                                    <span class="pm-desc">{{ number_format($totalAll, 0, ',', '.') }} produk, urut abjad nama produk</span>
                                </label>
                                <label class="pm-option">
                                    <input type="radio" name="scope" value="folder" {{ $activeFolder !== null ? 'checked' : '' }}>
                                    <span class="pm-title">Barang dalam folder</span>
                                    <span class="pm-desc">Hanya produk di satu folder (kata pertama nama produk)</span>
                                    <div class="pm-select">
                                        <select name="folder" id="export-folder" style="width:100%;" data-placeholder="Pilih folder">
                                            <option value=""></option>
                                            @foreach ($folders as $folder)
                                                <option value="{{ $folder['key'] }}" {{ $activeFolder === $folder['key'] ? 'selected' : '' }}>
                                                    {{ $folder['label'] }} ({{ number_format($folder['total'], 0, ',', '.') }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </label>
                                <p class="text-muted small" style="margin:6px 0 0;">
                                    File Excel (.xlsx) langsung terunduh, kolom sama seperti tabel di halaman ini tanpa kolom Action.
                                </p>
                            @else
                                <p class="text-center text-muted" style="margin:10px 0;">Belum ada master harga untuk outlet ini.</p>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" id="export-master-submit" {{ $totalAll > 0 ? '' : 'disabled' }}>
                                <i class="fa fa-file-excel-o"></i> Export Excel</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@section('page-script')
    @if ($selectedOutletId && $totalAll > 0)
        <script>
            $(function () {
                var $modal = $('#exportMasterModal');
                var $folder = $('#export-folder');

                $folder.select2({
                    width: '100%',
                    placeholder: $folder.data('placeholder'),
                    allowClear: false,
                    dropdownParent: $modal
                });

                function syncExportForm() {
                    var isFolder = $('#export-master-form input[name=scope]:checked').val() === 'folder';
                    $folder.prop('disabled', !isFolder);
                    $('.pm-option').removeClass('active');
                    $('#export-master-form input[name=scope]:checked').closest('.pm-option').addClass('active');
                    // Cakupan folder wajib memilih folder dulu.
                    $('#export-master-submit').prop('disabled', isFolder && !$folder.val());
                }

                $('#export-master-form input[name=scope]').on('change', syncExportForm);
                $folder.on('change', function () {
                    // Memilih folder otomatis memilih cakupan "folder".
                    if ($folder.val()) {
                        $('#export-master-form input[name=scope][value=folder]').prop('checked', true);
                    }
                    syncExportForm();
                });
                syncExportForm();

                // File terunduh dari halaman ini; tutup popup setelah dikirim.
                $('#export-master-form').on('submit', function () {
                    setTimeout(function () { $modal.modal('hide'); }, 150);
                });
            });
        </script>
    @endif
    @if ($mode === 'folders')
        <script>
            $(function () {
                // Cari folder di sisi browser (daftar folder sudah ada di halaman).
                $('#opf-filter').on('input', function () {
                    var term = $.trim($(this).val()).toLowerCase();
                    var visible = 0;
                    $('#opf-grid .opf-card[data-name]').each(function () {
                        var match = term === '' || String($(this).data('name')).indexOf(term) !== -1;
                        $(this).toggle(match);
                        if (match) { visible++; }
                    });
                    $('#opf-empty').toggle(term !== '' && visible === 0);
                });
            });
        </script>
    @endif
    @if ($mode === 'products')
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