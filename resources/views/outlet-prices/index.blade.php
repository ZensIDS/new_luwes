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
        /* Supplier dibatasi lebarnya: teks panjang dipotong dengan "..." (nama lengkap muncul saat di-hover). */
        /* Edit cepat (klik 2x): barcode, nama, kategori. Simpan = Enter, batal = Esc / klik di luar. */
        #example1 td.inline-edit { cursor: cell; }
        #example1 td.inline-edit:hover { background: #fffbe6; }
        #example1 td.inline-edit.ie-saved { background: #dff0d8; transition: background .8s; }
        #example1 td.inline-edit .ie-input { width: 100%; min-width: 200px; box-sizing: border-box; }
        #example1 td.inline-edit .select2-container { width: 100% !important; }
        #example1 td.inline-edit .ie-error { color: #a94442; font-size: 12px; margin-top: 2px; white-space: normal; }
        #example1 td.op-supplier { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

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
        .opf-card-latest { background:#00a65a; border-color:#00a65a; color:#fff; }
        .opf-card-latest:hover, .opf-card-latest:focus { color:#fff; border-color:#008d4c; box-shadow:0 3px 10px rgba(0,166,90,.35); }
        .opf-card-latest .opf-icon, .opf-card-latest .opf-count { color:#fff; }
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
                    <form id="outlet-price-label-print-form" method="POST"
                        action="{{ route('cashier.print.products') }}" target="_blank"
                        style="display:inline-block; margin-left:8px;">
                        @csrf
                        <input type="hidden" name="print" value="1">
                        <input type="hidden" name="outlet_id" value="{{ $selectedOutletId }}">
                        <select name="mode" class="form-control input-sm" style="display:inline-block; width:auto;"
                            title="Mode cetak label">
                            <option value="thermal" selected>Thermal (hitam putih)</option>
                            <option value="warna">Warna</option>
                        </select>
                        <select name="paper" class="form-control input-sm" style="display:inline-block; width:auto;"
                            title="Ukuran kertas label">
                            <option value="80" selected>80 mm</option>
                            <option value="68">68 mm</option>
                            <option value="58">58 mm</option>
                            <option value="100">A4 (label 68 mm)</option>
                        </select>
                        <button type="submit" id="print-selected-outlet-price-labels" class="btn bg-purple" disabled>
                            <i class="fa fa-print"></i> Cetak label terpilih
                            (<span id="selected-outlet-price-count">0</span>)
                        </button>
                        <button type="button" id="clear-selected-outlet-price-labels" class="btn btn-default btn-sm"
                            title="Hapus semua pilihan di semua halaman">Hapus pilihan</button>
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
                    @if ($mode === 'products' && ($promoFilter ?? '') !== '')
                        <input type="hidden" name="promo" value="{{ $promoFilter }}">
                    @endif
                    <button class="btn btn-primary btn-sm">Filter</button>
                </form>
            </div>
            @if ($mode === 'folders')
                <div class="box-body">
                    <div class="opf-toolbar">
                        <span class="opf-hint">
                            <i class="fa fa-folder-open-o"></i> Pilih folder (kata pertama nama produk; awalan simbol/angka ada di folder #/Lainnya), atau ketik awalan nama/kode di kotak pencarian atas untuk mencari di semua produk.
                        </span>
                        @if (count($folders) > 12)
                            <input type="text" id="opf-filter" class="form-control input-sm" style="max-width:240px;"
                                placeholder="Cari folder..." autocomplete="off">
                        @endif
                    </div>

                    @if ($totalAll > 0)
                        <div class="opf-grid" id="opf-grid">
                            <a class="opf-card opf-card-latest"
                                href="{{ route('outlet-prices.index', ['outlet_id' => $selectedOutletId, 'latest' => 1]) }}">
                                <span class="opf-icon"><i class="fa fa-clock-o"></i></span>
                                <span class="opf-name">Terakhir Ditambahkan</span>
                                <span class="opf-count">{{ number_format($totalAll, 0, ',', '.') }} produk</span>
                            </a>
                            <a class="opf-card opf-card-all"
                                href="{{ route('outlet-prices.index', ['outlet_id' => $selectedOutletId, 'all' => 1]) }}">
                                <span class="opf-icon"><i class="fa fa-th-large"></i></span>
                                <span class="opf-name">Semua Barang (A-Z)</span>
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
                            @elseif ($showLatest)
                                Folder <strong>Terakhir Ditambahkan</strong>
                            @else
                                Semua Barang (A-Z)
                            @endif
                            &middot; {{ number_format($prices->total(), 0, ',', '.') }} produk
                        </span>
                        <form method="GET" action="{{ route('outlet-prices.index') }}" class="form-inline" style="display:inline-block;margin-left:12px">
                            <input type="hidden" name="outlet_id" value="{{ $selectedOutletId }}">
                            @if ($searchTerm !== '')
                                <input type="hidden" name="search" value="{{ $searchTerm }}">
                            @elseif ($activeFolder !== null)
                                <input type="hidden" name="folder" value="{{ $activeFolder }}">
                            @elseif ($showLatest)
                                <input type="hidden" name="latest" value="1">
                            @else
                                <input type="hidden" name="all" value="1">
                            @endif
                            <label class="control-label" for="promo-filter" style="font-weight:normal">Diskon:</label>
                            <select id="promo-filter" name="promo" class="form-control input-sm" onchange="this.form.submit()"
                                @if (($promoFilter ?? '') !== '') style="border-color:#e0577a" @endif>
                                <option value="" {{ ($promoFilter ?? '') === '' ? 'selected' : '' }}>Semua</option>
                                <option value="diskon" {{ ($promoFilter ?? '') === 'diskon' ? 'selected' : '' }}>Dengan diskon</option>
                                <option value="tanpa" {{ ($promoFilter ?? '') === 'tanpa' ? 'selected' : '' }}>Tanpa diskon</option>
                            </select>
                            @if (($promoFilter ?? '') !== '')
                                <a class="btn btn-default btn-sm" title="Hapus filter diskon"
                                    href="{{ route('outlet-prices.index', array_filter([
                                        'outlet_id' => $selectedOutletId,
                                        'search' => $searchTerm,
                                        'folder' => $searchTerm === '' ? $activeFolder : null,
                                        'latest' => ($searchTerm === '' && $activeFolder === null && $showLatest) ? 1 : null,
                                        'all' => ($searchTerm === '' && $activeFolder === null && ! $showLatest) ? 1 : null,
                                    ])) }}">Reset</a>
                            @endif
                        </form>
                    </div>
                @endif
                <table id="{{ $selectedOutletId ? 'example1' : 'outlet-prices-empty-table' }}" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            @if ($selectedOutletId)
                                <th class="text-center">
                                    <input type="checkbox" id="select-all-outlet-price-labels"
                                        title="Pilih semua produk pada halaman ini (pilihan di halaman lain tetap tersimpan)">
                                </th>
                            @endif
                            <th>Barcode</th>
                            <th>Nama Product</th>
                            <th>Kategori</th>
                            <th>Supplier</th>
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
                                                value="{{ $price->product_id }}"
                                                aria-label="Pilih {{ $price->product?->name }}">
                                        @else
                                            <span class="text-muted" title="Produk belum memiliki barcode">—</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="inline-edit" data-field="code" data-value="{{ $price->product?->code }}" data-id="{{ $price->product_id }}" title="Klik 2x untuk edit">{{ $price->product?->code ?: '—' }}</td>
                                <td class="inline-edit" data-field="name" data-value="{{ $price->product?->name }}" data-id="{{ $price->product_id }}" title="Klik 2x untuk edit">{{ $price->product?->name }}</td>
                                <td class="inline-edit" data-field="category_id" data-value="{{ $price->product?->category_id }}" data-id="{{ $price->product_id }}" title="Klik 2x untuk edit">{{ $price->category_name ?: '—' }}</td>
                                <td class="op-supplier" title="{{ $price->supplier_name }}">{{ $price->supplier_name ?: '—' }}</td>
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
                                    <td colspan="17" class="text-center">Pilih outlet terlebih dahulu untuk melihat master harga.</td>
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
@endsection

@section('page-script')
    @if ($selectedOutletId)
        <script>
            $(function () {
                // Pilihan disimpan di Set supaya tidak hilang saat pindah halaman DataTables
                // (baris halaman lain dicabut dari DOM).
                // Pagination server-side (reload tiap halaman) -> simpan di sessionStorage per outlet.
                var labelStorageKey = 'outlet-price-label-selected:{{ $selectedOutletId }}';
                var selectedLabelIds = new Set();
                try {
                    JSON.parse(sessionStorage.getItem(labelStorageKey) || '[]').forEach(function (id) {
                        selectedLabelIds.add(String(id));
                    });
                } catch (e) { /* abaikan */ }
                var labelTable = null;

                function persistLabelSelection() {
                    try {
                        sessionStorage.setItem(labelStorageKey, JSON.stringify(Array.from(selectedLabelIds)));
                    } catch (e) { /* abaikan */ }
                }

                function updateSelectedOutletPriceLabels() {
                    persistLabelSelection();
                    var count = selectedLabelIds.size;
                    $('#selected-outlet-price-count').text(count);
                    $('#print-selected-outlet-price-labels').prop('disabled', count === 0);

                    var $visible = allLabelCheckboxes();
                    $('#select-all-outlet-price-labels').prop(
                        'checked',
                        $visible.length > 0 && $visible.filter(':checked').length === $visible.length
                    );
                }

                // Semua checkbox milik halaman server ini, termasuk baris yang sedang
                // disembunyikan oleh paging DataTables.
                function allLabelCheckboxes() {
                    if (labelTable) {
                        return labelTable.rows().nodes().to$().find('.outlet-price-label-checkbox');
                    }
                    return $('.outlet-price-label-checkbox');
                }

                function restoreLabelChecks() {
                    allLabelCheckboxes().each(function () {
                        $(this).prop('checked', selectedLabelIds.has(String(this.value)));
                    });
                    updateSelectedOutletPriceLabels();
                }

                $('#select-all-outlet-price-labels').on('change', function () {
                    var checked = this.checked;
                    // Hanya baris di halaman yang sedang tampil
                    allLabelCheckboxes().each(function () {
                        $(this).prop('checked', checked);
                        if (checked) { selectedLabelIds.add(String(this.value)); }
                        else { selectedLabelIds.delete(String(this.value)); }
                    });
                    updateSelectedOutletPriceLabels();
                });

                $(document).on('change', '.outlet-price-label-checkbox', function () {
                    if (this.checked) { selectedLabelIds.add(String(this.value)); }
                    else { selectedLabelIds.delete(String(this.value)); }
                    updateSelectedOutletPriceLabels();
                });

                // Saat cetak: kirim semua ID terpilih dari semua halaman
                $('#outlet-price-label-print-form').on('submit', function () {
                    var $form = $(this);
                    $form.find('input.js-selected-product-id').remove();
                    selectedLabelIds.forEach(function (id) {
                        $('<input type="hidden" class="js-selected-product-id" name="product_ids[]">')
                            .val(id).appendTo($form);
                    });

                    // Setelah cetak: kosongkan pilihan (submit sudah membaca input di atas).
                    setTimeout(function () {
                        selectedLabelIds.clear();
                        restoreLabelChecks();
                    }, 0);
                });
                $('#clear-selected-outlet-price-labels').on('click', function () {
                    selectedLabelIds.clear();
                    restoreLabelChecks();
                });


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
                labelTable = $('#example1').DataTable({
                    dom: 'lrtip', // search bawaan (cocokkan di tengah kata, hanya 25 baris) dimatikan; pakai search server di atas
                    ordering: false,
                    pageLength: 25,
                    language: { emptyTable: 'Belum ada master harga untuk outlet ini.' }
                });
                labelTable.on('draw', restoreLabelChecks);
                restoreLabelChecks();
            });
        </script>

        <script>
            // Edit cepat: klik 2x pada Barcode / Nama Product / Kategori. Enter = simpan, Esc atau klik di luar = batal.
            (function () {
                const categories = @json(($categories ?? collect())->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values());
                const csrf = '{{ csrf_token() }}';

                $(document).on('dblclick', '#example1 td.inline-edit', function () {
                    const $td = $(this);
                    if ($td.data('editing')) return;

                    const field = $td.data('field');
                    const isSelect = field === 'category_id';
                    const original = String($td.attr('data-value') ?? '');
                    const originalHtml = $td.html();
                    // Lebar sel saat ini: input dibuat memenuhi kolom (tidak mengecil mengikuti isi).
                    const cellWidth = $td.innerWidth() - parseFloat($td.css('padding-left')) - parseFloat($td.css('padding-right'));
                    let finished = false;
                    let saving = false;

                    $td.data('editing', true);

                    let $input;
                    if (isSelect) {
                        $input = $('<select class="ie-input">');
                        $input.append($('<option value="">'));
                        categories.forEach(function (c) {
                            $input.append($('<option>').val(c.id).text(c.name).prop('selected', String(c.id) === original));
                        });
                    } else {
                        $input = $('<input type="text" class="form-control input-sm ie-input" autocomplete="off">').val(original);
                    }

                    const $error = $('<div class="ie-error">').hide();
                    $input.css('min-width', Math.max(cellWidth, 200) + 'px');
                    $td.empty().append($input, $error);

                    if (isSelect) {
                        $input.select2({
                            width: '100%',
                            placeholder: '— Pilih kategori —',
                            allowClear: false,
                            dropdownAutoWidth: true,
                        });
                        $input.select2('open');
                    } else {
                        $input.trigger('focus');
                        $input[0].select();
                    }

                    function teardown() {
                        finished = true;
                        $(document).off('mousedown.ie keydown.ie');
                        if (isSelect) {
                            try { $input.select2('destroy'); } catch (e) {}
                        }
                    }

                    function cancel() {
                        if (finished || saving) return;
                        teardown();
                        $td.data('editing', false).html(originalHtml);
                    }

                    function save() {
                        if (finished || saving) return;
                        const value = String($input.val() ?? '').trim();

                        if (value === original || (isSelect && value === '')) { cancel(); return; }

                        saving = true;
                        if (!isSelect) $input.prop('disabled', true);
                        $error.hide();

                        $.ajax({
                            url: '{{ url('/outlet-prices/products') }}/' + $td.data('id') + '/inline',
                            method: 'PATCH',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            data: { field: field, value: value },
                        }).done(function (res) {
                            teardown();
                            $td.attr('data-value', res.value);
                            $td.data('editing', false).text(res.display || '—');
                            $td.addClass('ie-saved');
                            setTimeout(function () { $td.removeClass('ie-saved'); }, 900);
                        }).fail(function (xhr) {
                            saving = false;
                            if (!isSelect) $input.prop('disabled', false).trigger('focus');
                            const msg = xhr.responseJSON && (xhr.responseJSON.message || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))
                                || 'Gagal menyimpan. Coba lagi.';
                            $error.text(msg).show();
                        });
                    }

                    if (isSelect) {
                        // Memilih dengan keyboard (Enter di kotak pencarian select2) = simpan.
                        // Memilih dengan mouse hanya mengganti pilihan; simpan tetap dengan Enter.
                        $input.on('select2:select', function (e) {
                            // Select2 hanya menyertakan originalEvent saat dipilih dengan mouse/sentuh; lewat Enter tidak ada.
                            const oe = e.params && e.params.originalEvent;
                            const byPointer = oe && /^(mouse|click|touch|pointer)/.test(oe.type);
                            if (byPointer) {
                                setTimeout(function () { $td.find('.select2-selection').trigger('focus'); }, 0);
                            } else {
                                setTimeout(save, 0);
                            }
                        });

                        // Dropdown sudah tertutup dan fokus ada di kotak pilihan: Enter = simpan (bukan buka dropdown lagi).
                        $td[0].addEventListener('keydown', function (e) {
                            if (finished || e.key !== 'Enter') return;
                            if ($input.data('select2') && $input.data('select2').isOpen()) return;
                            e.preventDefault();
                            e.stopPropagation();
                            save();
                        }, true);

                        // Esc membatalkan (dropdown ada di luar sel, jadi didengarkan di document).
                        $(document).on('keydown.ie', function (e) {
                            if (e.key === 'Escape') { setTimeout(cancel, 0); }
                        });
                    } else {
                        $input.on('keydown', function (e) {
                            if (e.key === 'Enter') { e.preventDefault(); save(); }
                            else if (e.key === 'Escape') { e.preventDefault(); cancel(); }
                        });
                        // Klik di luar (blur) dianggap batal, sama seperti Esc.
                        $input.on('blur', function () { setTimeout(cancel, 0); });
                    }

                    // Klik di luar sel (dan di luar dropdown select2) = batal.
                    if (isSelect) {
                        $(document).on('mousedown.ie', function (e) {
                            const $t = $(e.target);
                            if ($t.closest($td).length || $t.closest('.select2-container').length) return;
                            cancel();
                        });
                    }
                });
            })();
        </script>
    @endif
@endsection