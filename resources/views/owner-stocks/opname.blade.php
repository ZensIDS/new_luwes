@extends('layouts.master')

@section('title', 'Stock Opname Toko')

@section('container')
    <style>
        .stock-filter-bar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
        .stock-filter { min-width:160px; margin:0; }
        .stock-filter.search-filter { flex:1 1 230px; }
        .stock-filter label { display:block; margin-bottom:4px; font-size:12px; color:#666; }
        .stock-filter .form-control, .stock-filter .select2-container { min-width:160px; }
        .stock-table th, .stock-table td { vertical-align:middle !important; }
        .stock-table input.form-control { min-width:90px; }
        .stock-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .stock-actions .spacer { flex:1; }
        .scan-bar { position:relative; max-width:560px; margin:0 0 12px; display:none; }
        .scan-bar .form-control { font-size:15px; height:38px; }
        .scan-sugg { position:absolute; left:0; right:0; top:100%; z-index:1050; background:#fff; border:1px solid #ccc; border-top:0; max-height:320px; overflow-y:auto; display:none; box-shadow:0 4px 10px rgba(0,0,0,.15); }
        .scan-sugg .it { padding:8px 12px; cursor:pointer; border-bottom:1px solid #f0f0f0; }
        .scan-sugg .it small { color:#888; display:block; }
        .scan-sugg .it.active, .scan-sugg .it:hover { background:#e8f0fe; }
        .scan-sugg .empty { padding:8px 12px; color:#888; }
        tr.row-flash td { background:#fff8c5 !important; transition:background 1.5s; }
        .result-panel { display:none; margin-top:18px; }
        .result-panel h4 { margin:0 0 8px; font-size:15px; }
        @media (max-width:767px) {
            .stock-filter, .stock-filter.search-filter, .stock-filter .form-control, .stock-filter .select2-container { width:100% !important; }
            .stock-actions .spacer { display:none; }
            .stock-actions .btn { width:100%; }
        }
    </style>

    <section class="content-header">
        <h1>Stock Opname Toko <small>Penyesuaian stok per outlet</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                @if (($deficitCount ?? 0) > 0 && in_array(auth()->user()->role, ['superadmin', 'owner', 'staff-outlet']))
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        Outlet ini punya <b>{{ $deficitCount }}</b> produk stok minus. Stok minus tidak muncul di opname per supplier;
                        cek fisiknya lalu catat penerimaan atau tutup lewat
                        <a href="{{ route('deficit-stocks.index', ['outlet_id' => $selectedOwner->id]) }}" class="alert-link">halaman Stok Minus</a>.
                    </div>
                @endif
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><strong>STOCK OPNAME TOKO</strong></h3>
                    </div>
                    <div class="box-body">
                        <div class="stock-filter-bar">
                            <div class="stock-filter">
                                <label for="filterOutlet">Outlet <span class="text-red">*</span></label>
                                <select id="filterOutlet" class="form-control input-sm select2"
                                    {{ auth()->user()->outlet_id ? 'disabled' : '' }}>
                                    <option value="">-- Pilih Outlet --</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" {{ $selectedOwner?->id == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="tglStockOpname">Tanggal Stock Opname</label>
                                <input type="date" id="tglStockOpname" class="form-control input-sm" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="stock-filter">
                                <label for="filterKategori">Kategori</label>
                                <select id="filterKategori" class="form-control input-sm select2">
                                    <option value="">-- Semua Kategori --</option>
                                    @foreach ($categoryOptions as $category)
                                        <option value="{{ $category }}" {{ request('kategori') == $category ? 'selected' : '' }}>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterLokasi">Lokasi</label>
                                <select id="filterLokasi" class="form-control input-sm select2">
                                    <option value="">-- Semua Lokasi --</option>
                                    @foreach ($locationOptions as $location)
                                        <option value="{{ $location }}" {{ request('lokasi') == $location ? 'selected' : '' }}>{{ $location }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterSupplier">Supplier <span class="text-red">*</span></label>
                                <select id="filterSupplier" class="form-control input-sm select2" {{ $selectedOwner ? '' : 'disabled' }}>
                                    <option value="">-- Pilih Supplier --</option>
                                    <option value="{{ $setStockKey }}" {{ request('supplier_id') === $setStockKey ? 'selected' : '' }}>★ Hasil Set Stock (semua produk yang sudah di-set stock)</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter search-filter">
                                <label for="filterSearch">Cari Produk / Batch</label>
                                <input type="search" id="filterSearch" class="form-control input-sm" placeholder="Ketik untuk mencari di daftar...">
                            </div>
                            <button type="button" id="resetOwnerOpnameFilters" class="btn btn-default btn-sm">
                                <i class="fa fa-refresh"></i> Reset
                            </button>
                        </div>
                        <p class="text-muted" style="margin:10px 0 15px;">
                            @if ($selectedOwner)
                                Pilih supplier terlebih dahulu untuk memuat data stock {{ $selectedOwner->name }}, atau pilih <b>Hasil Set Stock</b> untuk opname semua stok yang berasal dari Set Stock. Kategori dan lokasi bersifat opsional.
                            @else
                                Pilih outlet, lalu pilih supplier untuk memuat data stock.
                            @endif
                        </p>

                        <div id="scanBar" class="scan-bar">
                            <label for="scanProduct" style="font-size:12px;color:#666;margin-bottom:4px;">Scan barcode / ketik nama produk <span class="text-muted">(Enter)</span></label>
                            <input type="text" id="scanProduct" class="form-control" placeholder="Scan barcode atau ketik nama produk, lalu pilih" autocomplete="off">
                            <div id="scanSugg" class="scan-sugg"></div>
                        </div>

                        <div class="table-responsive">
                            <table id="ownerOpnameTable" class="table table-bordered table-striped stock-table">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Barcode</th>
                                        <th>Product</th>
                                        <th>Batch / SKU</th>
                                        <th>Satuan</th>
                                        <th>Stock Fisik</th>
                                        <th>Stock di Kartu</th>
                                        <th>Selisih</th>
                                        <th>Keterangan</th>
                                        <th style="width:36px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <tr><td colspan="10" class="text-center">{{ $selectedOwner ? 'Silakan pilih Supplier terlebih dahulu.' : 'Pilih outlet terlebih dahulu.' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="box-body" style="padding-top:0;">
                        <div id="resultPanel" class="result-panel">
                            <h4><i class="fa fa-check-circle text-green"></i> Hasil opname yang baru disimpan
                                <small id="resultInfo" class="text-muted"></small></h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-condensed">
                                    <thead>
                                        <tr><th>Barcode</th><th>Product</th><th>Batch / SKU</th><th class="text-right">Stok Sebelum</th><th class="text-right">Stok Sekarang</th><th class="text-right">Selisih</th><th>Riwayat</th></tr>
                                    </thead>
                                    <tbody id="resultBody"></tbody>
                                </table>
                            </div>
                            <p class="text-muted" style="margin:0;">Daftar di atas sudah menampilkan stok terbaru; produk tetap ada di daftar sehingga tidak perlu dicari lagi.</p>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="stock-actions">
                            <span id="rowCountInfo" class="text-muted"></span>
                            <span class="spacer"></span>
                            <button class="btn btn-default btn-sm" id="btnClearList" style="display:none;">
                                <i class="fa fa-times"></i> Kosongkan Daftar
                            </button>
                            <button class="btn btn-success btn-sm" id="btnSaveOpname" disabled>
                                <i class="fa fa-save"></i> Simpan Opname
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('page-script')
    <script>
        $(function () {
            const fixedOutletId = @json(auth()->user()->outlet_id);
            const SET_KEY = @json($setStockKey);
            const dataUrl = '{{ route('owner-stock-opname.data') }}';
            const searchUrl = '{{ route('owner-stock-opname.search') }}';
            const saveUrl = '{{ route('owner-stock-opname.save') }}';
            const pageUrl = '{{ route('owner-stock-opname') }}';
            const kartuUrl = '{{ route('owner-stocks.kartu') }}';
            const MAX_PRODUCTS = 100;
            const params = new URLSearchParams(window.location.search);
            const initialSupplier = params.get('supplier_id') || '';
            const $body = $('#tableBody');
            const $sugg = $('#scanSugg');
            const $scan = $('#scanProduct');
            let allStockData = [];
            let productOrder = [];            // mode Hasil Set Stock: id produk di daftar (terbaru di atas)
            let suggItems = [], suggIdx = -1, suggTimer = null, suggSeq = 0, busy = false;

            function escapeHtml(value) { return $('<div>').text(value == null ? '' : value).html(); }
            function currentOutlet() { return $('#filterOutlet').val() || fixedOutletId || ''; }
            function isSetMode() { return $('#filterSupplier').val() === SET_KEY; }
            function fmt(n) { return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 2 }); }
            function storeKey() { return 'opname:set:' + currentOutlet(); }
            function saveList() { try { sessionStorage.setItem(storeKey(), JSON.stringify(productOrder)); } catch (e) {} }
            function readList() { try { return JSON.parse(sessionStorage.getItem(storeKey()) || '[]') || []; } catch (e) { return []; } }

            function emptyMessage() {
                if (!currentOutlet()) return 'Pilih outlet terlebih dahulu.';
                if (isSetMode()) return 'Scan barcode atau ketik nama produk di kolom atas untuk menambahkannya ke daftar opname.';
                return 'Silakan pilih Supplier terlebih dahulu.';
            }

            function renderEmptyState(message) {
                $body.html('<tr><td colspan="10" class="text-center">' + escapeHtml(message) + '</td></tr>');
                $('#btnSaveOpname').prop('disabled', true);
                $('#btnClearList').toggle(isSetMode() && productOrder.length > 0);
                $('#rowCountInfo').text('');
                allStockData = [];
            }

            function updateNumbers() {
                var visible = 0;
                $body.find('tr[data-stock-id]').each(function () {
                    if ($(this).css('display') === 'none') return;
                    visible++;
                    $(this).find('td:first').text(visible);
                });
                $('#rowCountInfo').text(allStockData.length
                    ? visible + ' dari ' + allStockData.length + ' batch ditampilkan' + (isSetMode() ? ' (' + productOrder.length + ' produk)' : '')
                    : '');
            }

            function applySearchFilter() {
                var search = String($('#filterSearch').val() || '').toLowerCase().trim();
                $body.find('tr[data-stock-id]').each(function () {
                    var haystack = String($(this).attr('data-search') || '');
                    this.style.display = (!search || haystack.indexOf(search) !== -1) ? '' : 'none';
                });
                updateNumbers();
            }

            function rowHtml(item, index) {
                var batch = item.batch_number || item.serial_number || '-';
                var search = [item.product_code, item.product_name, batch].join(' ').toLowerCase();
                return '<tr data-stock-id="' + item.id + '" data-product-id="' + item.product_id + '" data-search="' + escapeHtml(search) + '">' +
                    '<td>' + (index + 1) + '</td>' +
                    '<td><input type="text" class="form-control input-sm" value="' + escapeHtml(item.product_code || '-') + '" disabled></td>' +
                    '<td><input type="text" class="form-control input-sm" value="' + escapeHtml(item.product_name || '-') + '" disabled></td>' +
                    '<td><input type="text" class="form-control input-sm" value="' + escapeHtml(batch) + '" disabled></td>' +
                    '<td><input type="text" class="form-control input-sm" value="' + escapeHtml(item.satuan || 'pcs') + '" disabled></td>' +
                    '<td><input type="number" step="0.01" min="0" class="form-control input-sm stock_fisik" value="' + item.qty + '"></td>' +
                    '<td><input type="number" class="form-control input-sm stock_dikartu" value="' + item.qty + '" disabled></td>' +
                    '<td><input type="number" step="0.01" class="form-control input-sm selisih" value="0" disabled></td>' +
                    '<td><input type="text" class="form-control input-sm keterangan"></td>' +
                    '<td class="text-center">' + (isSetMode() ? '<button type="button" class="btn btn-link btn-xs text-red btn-remove-product" title="Hapus dari daftar"><i class="fa fa-times"></i></button>' : '') + '</td>' +
                    '</tr>';
            }

            // Baris dibangun sekali sebagai satu string (bukan append per baris).
            function renderRows() {
                if (!allStockData.length) {
                    renderEmptyState(isSetMode() ? emptyMessage() : 'Tidak ada data stock untuk filter ini.');
                    return;
                }
                $body.html(allStockData.map(rowHtml).join(''));
                $('#btnSaveOpname').prop('disabled', false);
                $('#btnClearList').toggle(isSetMode());
                applySearchFilter();
            }

            // ---------- Supplier biasa: muat semua stok supplier ----------
            function loadStockData() {
                var outletId = currentOutlet();
                var supplierId = $('#filterSupplier').val();

                if (!outletId) { renderEmptyState('Pilih outlet terlebih dahulu.'); return; }
                if (!supplierId) { renderEmptyState('Silakan pilih Supplier terlebih dahulu.'); return; }
                if (supplierId === SET_KEY) { restoreSetList(); return; }

                renderEmptyState('Memuat data...');

                $.get(dataUrl, {
                    outlet_id: outletId,
                    supplier_id: supplierId,
                    kategori: $('#filterKategori').val(),
                    lokasi: $('#filterLokasi').val()
                }).done(function (response) {
                    allStockData = response.stocks || [];
                    renderRows();
                }).fail(function () {
                    alert('Gagal memuat data stock');
                    renderEmptyState('Gagal memuat data. Silakan coba lagi.');
                });
            }

            // ---------- Hasil Set Stock: daftar dibangun lewat scan / pencarian ----------
            function fetchProducts(ids) {
                var all = [], chunks = [];
                for (var i = 0; i < ids.length; i += 50) chunks.push(ids.slice(i, i + 50));
                return $.when.apply($, chunks.map(function (chunk) {
                    return $.get(dataUrl, { outlet_id: currentOutlet(), supplier_id: SET_KEY, product_ids: chunk })
                        .then(function (res) { all = all.concat(res.stocks || []); });
                })).then(function () { return all; });
            }

            // Susun ulang allStockData mengikuti productOrder (produk terbaru di atas), batch tetap urut dari server.
            function sortByOrder() {
                var rank = {};
                productOrder.forEach(function (id, i) { rank[id] = i; });
                allStockData = allStockData.filter(function (r) { return rank[r.product_id] !== undefined; })
                    .map(function (r, i) { return { r: r, i: i }; })
                    .sort(function (a, b) { return (rank[a.r.product_id] - rank[b.r.product_id]) || (a.i - b.i); })
                    .map(function (x) { return x.r; });
            }

            function flashProduct(productId) {
                var $rows = $body.find('tr[data-product-id="' + productId + '"]');
                if (!$rows.length) return;
                $rows.addClass('row-flash');
                setTimeout(function () { $rows.removeClass('row-flash'); }, 1600);
                var $first = $rows.first().find('.stock_fisik');
                $first.trigger('focus').trigger('select');
                if ($rows[0].scrollIntoView) $rows[0].scrollIntoView({ block: 'nearest' });
            }

            function addProduct(productId) {
                productId = parseInt(productId, 10);
                hideSugg();
                $scan.val('');
                if (productOrder.indexOf(productId) !== -1) { flashProduct(productId); return; }
                if (productOrder.length >= MAX_PRODUCTS) { alert('Daftar opname maksimal ' + MAX_PRODUCTS + ' produk. Simpan dulu atau kosongkan daftar.'); return; }
                if (busy) return;
                busy = true;
                fetchProducts([productId]).done(function (rows) {
                    if (!rows.length) { alert('Produk tidak ditemukan di stok hasil Set Stock.'); return; }
                    productOrder.unshift(productId);
                    allStockData = rows.concat(allStockData);
                    sortByOrder();
                    saveList();
                    // Hanya baris produk baru yang ditambahkan; angka yang sudah diketik di baris lain tidak hilang.
                    var $existing = $body.find('tr[data-stock-id]');
                    if (!$existing.length) { renderRows(); } else {
                        $body.prepend(rows.map(function (r, i) { return rowHtml(r, i); }).join(''));
                        $('#btnSaveOpname').prop('disabled', false);
                        $('#btnClearList').show();
                        applySearchFilter();
                    }
                    flashProduct(productId);
                }).fail(function (xhr) {
                    alert(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal memuat produk.');
                }).always(function () { busy = false; $scan.trigger('focus'); });
            }

            function restoreSetList() {
                productOrder = readList();
                if (!productOrder.length) { renderEmptyState(emptyMessage()); return; }
                renderEmptyState('Memuat daftar opname...');
                fetchProducts(productOrder).done(function (rows) {
                    allStockData = rows;
                    productOrder = productOrder.filter(function (id) { return rows.some(function (r) { return r.product_id === id; }); });
                    sortByOrder();
                    saveList();
                    renderRows();
                }).fail(function () { renderEmptyState('Gagal memuat daftar. Silakan scan ulang produknya.'); });
            }

            function hideSugg() { $sugg.hide().empty(); suggItems = []; suggIdx = -1; }
            function setActive(i) {
                suggIdx = i;
                var $it = $sugg.find('.it').removeClass('active').eq(i).addClass('active');
                if ($it.length && $it[0].scrollIntoView) $it[0].scrollIntoView({ block: 'nearest' });
            }
            function renderSugg(list, q) {
                suggItems = list; suggIdx = -1;
                if (!list.length) { $sugg.html('<div class="empty">Tidak ada produk hasil Set Stock untuk "' + escapeHtml(q) + '".</div>').show(); return; }
                $sugg.html(list.map(function (p, i) {
                    return '<div class="it" data-i="' + i + '">' + escapeHtml(p.name) +
                        '<small>' + escapeHtml(p.code) + ' &middot; stok ' + fmt(p.qty) + ' ' + escapeHtml(p.satuan) + (productOrder.indexOf(p.id) !== -1 ? ' &middot; sudah di daftar' : '') + '</small></div>';
                }).join('')).show();
            }
            function search(q, onDone) {
                var seq = ++suggSeq;
                $.get(searchUrl, { outlet_id: currentOutlet(), q: q }).done(function (res) {
                    if (seq !== suggSeq) return;
                    onDone(res);
                }).fail(function () { if (seq === suggSeq) hideSugg(); });
            }

            $scan.on('input', function () {
                var q = $.trim($scan.val());
                clearTimeout(suggTimer);
                if (!q.length) { suggSeq++; hideSugg(); return; }
                if (q.length < 2 && !/^\d+$/.test(q)) { hideSugg(); return; }
                suggTimer = setTimeout(function () {
                    search(q, function (res) { renderSugg(res.products || [], q); });
                }, 220);
            });
            $scan.on('keydown', function (e) {
                if (e.key === 'ArrowDown' && suggItems.length) { e.preventDefault(); setActive(Math.min(suggIdx + 1, suggItems.length - 1)); return; }
                if (e.key === 'ArrowUp' && suggItems.length) { e.preventDefault(); setActive(Math.max(suggIdx - 1, 0)); return; }
                if (e.key === 'Escape') { hideSugg(); return; }
                if (e.key !== 'Enter') return;
                e.preventDefault();
                if (suggIdx >= 0 && suggItems[suggIdx]) { addProduct(suggItems[suggIdx].id); return; }
                var q = $.trim($scan.val());
                if (!q) return;
                clearTimeout(suggTimer);
                // Enter (scanner): barcode persis langsung ditambahkan; satu-satunya hasil juga ditambahkan.
                search(q, function (res) {
                    var list = res.products || [];
                    if (res.exact) { addProduct(res.exact); }
                    else if (list.length === 1) { addProduct(list[0].id); }
                    else { renderSugg(list, q); }
                });
            });
            $sugg.on('mousedown', '.it', function (e) { e.preventDefault(); var p = suggItems[parseInt($(this).data('i'), 10)]; if (p) addProduct(p.id); });
            $(document).on('click', function (e) { if (!$(e.target).closest('#scanProduct, #scanSugg').length) hideSugg(); });

            $body.on('click', '.btn-remove-product', function () {
                var id = parseInt($(this).closest('tr').data('product-id'), 10);
                productOrder = productOrder.filter(function (x) { return x !== id; });
                allStockData = allStockData.filter(function (r) { return r.product_id !== id; });
                $body.find('tr[data-product-id="' + id + '"]').remove();
                saveList();
                if (!allStockData.length) renderEmptyState(emptyMessage()); else updateNumbers();
            });
            $('#btnClearList').on('click', function () {
                if (!confirm('Kosongkan daftar opname? Angka yang belum disimpan akan hilang.')) return;
                productOrder = []; saveList(); $('#resultPanel').hide();
                renderEmptyState(emptyMessage());
                $scan.trigger('focus');
            });

            // ---------- Mode tampilan ----------
            function applyMode() {
                var setMode = isSetMode() && !!currentOutlet();
                $('#scanBar').toggle(setMode);
                $('#filterKategori, #filterLokasi').closest('.stock-filter').toggle(!isSetMode());
                // Mode Hasil Set Stock: produk dicari lewat kolom scan, jadi kolom "Cari Produk / Batch" disembunyikan.
                if (isSetMode()) { $('#filterSearch').val(''); }
                $('#filterSearch').closest('.stock-filter').toggle(!isSetMode());
                $('#btnClearList').toggle(setMode && productOrder.length > 0);
                if (setMode) setTimeout(function () { $scan.trigger('focus'); }, 50);
            }

            // Sama seperti opname gudang: ganti filter -> reload halaman dengan parameter di URL.
            function reloadWithParams(resetSupplier) {
                var next = new URLSearchParams();
                var outletId = currentOutlet();
                var supplierId = $('#filterSupplier').val();
                var kategori = $('#filterKategori').val();
                var lokasi = $('#filterLokasi').val();

                if (outletId && !fixedOutletId) next.append('outlet_id', outletId);
                if (supplierId && !resetSupplier) next.append('supplier_id', supplierId);
                if (kategori && supplierId !== SET_KEY) next.append('kategori', kategori);
                if (lokasi && supplierId !== SET_KEY) next.append('lokasi', lokasi);

                var query = next.toString();
                window.location.href = pageUrl + (query ? '?' + query : '');
            }

            $body.on('input', '.stock_fisik', function () {
                var row = $(this).closest('tr');
                var physical = parseFloat($(this).val()) || 0;
                var system = parseFloat(row.find('.stock_dikartu').val()) || 0;
                row.find('.selisih').val((physical - system).toFixed(2));
            });

            // Daftar supplier tergantung outlet, jadi ganti outlet mengosongkan supplier.
            $('#filterOutlet').on('change', function () { reloadWithParams(true); });
            $('#filterSupplier, #filterKategori, #filterLokasi').on('change', function () { reloadWithParams(false); });
            $('#filterSearch').on('input', applySearchFilter);
            $('#resetOwnerOpnameFilters').on('click', function () {
                window.location.href = pageUrl + (currentOutlet() && !fixedOutletId ? '?outlet_id=' + encodeURIComponent(currentOutlet()) : '');
            });

            // ---------- Hasil opname yang baru disimpan ----------
            function showResults(results, outletId) {
                if (!results || !results.length) { $('#resultPanel').hide(); return; }
                $('#resultBody').html(results.map(function (r) {
                    var diff = Number(r.difference);
                    return '<tr>' +
                        '<td>' + escapeHtml(r.product_code) + '</td>' +
                        '<td>' + escapeHtml(r.product_name) + '</td>' +
                        '<td>' + escapeHtml(r.batch_number || '-') + '</td>' +
                        '<td class="text-right">' + fmt(r.before) + '</td>' +
                        '<td class="text-right"><b>' + fmt(r.after) + '</b></td>' +
                        '<td class="text-right ' + (diff < 0 ? 'text-red' : 'text-green') + '">' + (diff > 0 ? '+' : '') + fmt(diff) + '</td>' +
                        '<td><a href="' + kartuUrl + '?outlet_id=' + encodeURIComponent(outletId) + '&product_id=' + r.product_id + '" target="_blank">Kartu Stok</a></td>' +
                        '</tr>';
                }).join(''));
                $('#resultInfo').text(' - ' + results.length + ' batch diubah, ' + new Date().toLocaleTimeString('id-ID'));
                $('#resultPanel').show();
            }

            // Setelah simpan: stok di daftar diperbarui dari server, produk TIDAK perlu dicari lagi.
            function refreshAfterSave() {
                if (isSetMode()) {
                    if (!productOrder.length) { renderEmptyState(emptyMessage()); return $.Deferred().resolve().promise(); }
                    return fetchProducts(productOrder).done(function (rows) {
                        allStockData = rows;
                        sortByOrder();
                        renderRows();
                    });
                }
                loadStockData();
                return $.Deferred().resolve().promise();
            }

            $('#btnSaveOpname').on('click', function () {
                var date = $('#tglStockOpname').val();
                var supplierId = $('#filterSupplier').val();

                if (!supplierId) { alert('Pilih Supplier terlebih dahulu.'); return; }
                if (!date) { alert('Tanggal Stock Opname harus diisi.'); return; }

                var items = [];
                $body.find('tr[data-stock-id]').each(function () {
                    var row = $(this);
                    var difference = parseFloat(row.find('.selisih').val()) || 0;
                    if (difference !== 0) {
                        items.push({
                            owner_stock_id: row.data('stock-id'),
                            physical_qty: parseFloat(row.find('.stock_fisik').val()) || 0,
                            keterangan: String(row.find('.keterangan').val() || '').trim()
                        });
                    }
                });

                if (!items.length) { alert('Tidak ada perubahan stock untuk disimpan.'); return; }
                if (!confirm('Simpan ' + items.length + ' penyesuaian stock?')) return;

                var button = $(this);
                button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');

                $.ajax({
                    url: saveUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        _token: '{{ csrf_token() }}',
                        outlet_id: currentOutlet(),
                        supplier_id: supplierId,
                        adjustment_date: date,
                        items: items
                    })
                }).done(function (response) {
                    if (response.success) {
                        showResults(response.results, currentOutlet());
                        refreshAfterSave();
                    } else {
                        alert(response.message || 'Gagal menyimpan stock opname.');
                    }
                }).fail(function (xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Terjadi kesalahan saat menyimpan data.';
                    alert(message);
                }).always(function () {
                    button.html('<i class="fa fa-save"></i> Simpan Opname');
                    if (allStockData.length) button.prop('disabled', false);
                });
            });

            applyMode();
            if (currentOutlet() && initialSupplier) {
                loadStockData();
            } else {
                renderEmptyState(emptyMessage());
            }
        });
    </script>
@endsection
