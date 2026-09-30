@extends('layouts.master')

@section('title', 'Kartu Stock Toko')

@section('container')
    <style>
        .stock-filter-bar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
        .stock-filter { min-width:155px; margin:0; }
        .stock-filter.product-filter { min-width:280px; flex:1 1 280px; }
        .stock-filter label { display:block; margin-bottom:4px; font-size:12px; color:#666; }
        .stock-filter .form-control, .stock-filter .select2-container { min-width:155px; }
        .stock-filter.product-filter .select2-container { width:100% !important; }
        .stock-filter-actions { display:flex; gap:6px; align-items:flex-end; flex-wrap:wrap; }
        .stock-table th, .stock-table td { vertical-align:middle !important; }
        .stock-info { margin:15px 0; }
        .stock-info td:first-child { width:150px; font-weight:600; }
        .stock-breakdown { margin-top:20px; }
        .stock-breakdown h4 { margin-top:0; }
        @media (max-width:767px) {
            .stock-filter, .stock-filter.product-filter, .stock-filter .form-control, .stock-filter .select2-container { width:100% !important; }
            .stock-filter-actions { width:100%; }
            .stock-filter-actions .btn { flex:1; }
        }
    </style>

    <section class="content-header">
        <h1>Kartu Stock Toko <small>Pergerakan stok per outlet</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><strong>KARTU STOCK TOKO</strong></h3>
                    </div>
                    <div class="box-body">
                        <div class="stock-filter-bar">
                            <div class="stock-filter">
                                <label for="filterOutlet">Outlet</label>
                                <select id="filterOutlet" class="form-control input-sm select2"
                                    {{ auth()->user()->outlet_id ? 'disabled' : '' }}>
                                    <option value="">Semua Outlet</option>
                                    @foreach ($outlets as $outlet)
                                        <option value="{{ $outlet->id }}" {{ $selectedOwner?->id == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterKategori">Kategori</label>
                                <select id="filterKategori" class="form-control input-sm select2">
                                    <option value="">Semua Kategori</option>
                                    @foreach ($categoryOptions as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterLokasi">Lokasi</label>
                                <select id="filterLokasi" class="form-control input-sm select2">
                                    <option value="">Semua Lokasi</option>
                                    @foreach ($locationOptions as $location)
                                        <option value="{{ $location }}">{{ $location }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterSupplier">Supplier</label>
                                <select id="filterSupplier" class="form-control input-sm select2">
                                    <option value="">Semua Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter product-filter">
                                <label for="selectProduct">Produk</label>
                                <select id="selectProduct" class="form-control input-sm" style="width:100%">
                                    <option value="">-- Pilih Produk --</option>
                                    @if ($selectedProduct)
                                        <option value="{{ $selectedProduct->id }}" selected>{{ $selectedProduct->name }} | {{ $selectedProduct->code }}</option>
                                    @endif
                                </select>
                            </div>
                            <div class="stock-filter-actions">
                                <button type="button" id="btnLoadCard" class="btn btn-primary btn-sm" disabled>
                                    <i class="fa fa-search"></i> Tampilkan Kartu
                                </button>
                                <button type="button" id="resetOwnerCardFilters" class="btn btn-default btn-sm">
                                    <i class="fa fa-refresh"></i> Reset
                                </button>
                            </div>
                        </div>
                        <div class="stock-filter-bar" style="margin-top:10px;">
                            <div class="stock-filter">
                                <label for="filterFrom">Tanggal Mulai</label>
                                <input type="date" id="filterFrom" class="form-control input-sm">
                            </div>
                            <div class="stock-filter">
                                <label for="filterTo">Tanggal Selesai</label>
                                <input type="date" id="filterTo" class="form-control input-sm">
                            </div>
                            <div class="stock-filter">
                                <label for="filterType">Tipe Pergerakan</label>
                                <select id="filterType" class="form-control input-sm select2">
                                    <option value="">Semua Tipe</option>
                                    <option value="return">Return</option>
                                    <option value="delivery">Delivery</option>
                                    <option value="sale">Sale</option>
                                    <option value="adjustment">Adjustment</option>
                                </select>
                            </div>
                        </div>
                        <p class="text-muted" style="margin-top:10px; margin-bottom:0;">
                            Ketik minimal 2 huruf nama produk (atau awal barcode) untuk mencari produk. Filter outlet, kategori, lokasi, dan supplier mempersempit hasil pencarian.
                        </p>

                        <div id="card-summary" class="alert alert-info stock-info" style="display:none;"></div>
                        <table id="cardInfoTable" class="table table-bordered table-condensed stock-info" style="display:none;">
                            <tr><td>Nama Produk</td><td><span id="displayProduct">-</span></td></tr>
                            <tr><td>Barcode</td><td><span id="displayCode">-</span></td></tr>
                            <tr><td>Supplier</td><td><span id="displaySupplier">-</span></td></tr>
                            <tr><td>Outlet</td><td><span id="displayOutlet">Semua Outlet</span></td></tr>
                        </table>

                        <div class="table-responsive">
                            <table id="kartuTable" class="table table-bordered table-striped stock-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>Outlet</th>
                                        <th>Batch</th>
                                        <th>Tipe</th>
                                        <th>Stok Awal</th>
                                        <th>Masuk</th>
                                        <th>Keluar</th>
                                        <th>Stok Akhir</th>
                                        <th>HPP (Rp)</th>
                                        <th>Nilai Persediaan</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody id="card-rows">
                                    <tr><td colspan="12" class="text-center">Pilih produk untuk menampilkan data.</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div id="productStockBreakdown" class="stock-breakdown" style="display:none;">
                            <h4>Rincian Stok per Batch <small>(Produk: <span id="breakdownProductName">-</span>)</small></h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-condensed">
                                    <thead>
                                        <tr>
                                            <th>Outlet</th>
                                            <th>Batch</th>
                                            <th>Serial</th>
                                            <th>Expired</th>
                                            <th>Supplier</th>
                                            <th class="text-right">HPP</th>
                                            <th class="text-right">Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody id="breakdownBody"></tbody>
                                    <tfoot id="breakdownFoot"></tfoot>
                                </table>
                            </div>
                            <p class="text-muted" style="font-size:12px;">
                                <b>Saldo</b> = stok toko real saat ini (sama dengan menu Stock Toko), bukan hasil hitungan log pergerakan.
                            </p>
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
            const cardDataUrl = '{{ route('owner-stocks.kartu.data') }}';
            const searchUrl = '{{ route('owner-stocks.kartu.search') }}';
            const idrFormat = new Intl.NumberFormat('id-ID');

            let allTransactions = [];
            let productMeta = {};
            let kartuTable = null;

            function escapeHtml(value) { return $('<div>').text(value == null ? '' : value).html(); }
            function formatRupiah(amount) { return idrFormat.format(amount || 0); }
            function currentOutlet() { return $('#filterOutlet').val() || fixedOutletId || ''; }

            function konversiDisplay(qty) {
                qty = parseInt(qty) || 0;
                if (!productMeta.konversi_qty || !productMeta.satuan_besar) return null;
                var boxes = Math.floor(qty / productMeta.konversi_qty);
                var rem = qty % productMeta.konversi_qty;
                if (rem === 0) return boxes + ' ' + productMeta.satuan_besar;
                if (boxes > 0) return boxes + ' ' + productMeta.satuan_besar + ' ' + rem + ' ' + (productMeta.satuan || 'PCS');
                return qty + ' ' + (productMeta.satuan || 'PCS');
            }

            function qtyDisplay(qty) {
                var converted = konversiDisplay(qty);
                return escapeHtml(qty) + (converted ? ' <span class="label label-info">' + escapeHtml(converted) + '</span>' : '');
            }

            function destroyTable() {
                if (kartuTable) { kartuTable.destroy(); kartuTable = null; }
            }

            function resetTable(message) {
                destroyTable();
                $('#card-rows').html('<tr><td colspan="12" class="text-center">' + escapeHtml(message) + '</td></tr>');
            }

            function clearResult() {
                allTransactions = [];
                productMeta = {};
                resetTable('Pilih produk untuk menampilkan data.');
                $('#card-summary, #cardInfoTable, #productStockBreakdown').hide();
            }

            function transactionType(row) {
                if (row.is_return) return 'return';
                var type = String(row.type || '').toLowerCase();
                if (type.indexOf('delivery') !== -1 || type.indexOf('transfer') !== -1 || type.indexOf('in') !== -1) return 'delivery';
                if (type.indexOf('sale') !== -1 || type.indexOf('out') !== -1) return 'sale';
                if (type.indexOf('adjust') !== -1) return 'adjustment';
                return type;
            }

            function filteredTransactions() {
                var from = $('#filterFrom').val();
                var to = $('#filterTo').val();
                var type = $('#filterType').val();
                return allTransactions.filter(function (row) {
                    var date = String(row.tanggal || '').slice(0, 10);
                    return (!from || date >= from) && (!to || date <= to) && (!type || transactionType(row) === type);
                });
            }

            // DataTable memakai data array (bukan ribuan <tr> di DOM), jadi hanya satu halaman
            // yang digambar browser walaupun produk punya puluhan ribu pergerakan.
            function renderTable() {
                destroyTable();
                var rows = filteredTransactions().map(function (row, index) {
                    return $.extend({}, row, { no: index + 1 });
                });

                if (!rows.length) {
                    $('#card-rows').html('<tr><td colspan="12" class="text-center">Tidak ada pergerakan untuk filter ini.</td></tr>');
                    return;
                }

                $('#card-rows').empty();
                kartuTable = $('#kartuTable').DataTable({
                    data: rows,
                    deferRender: true,
                    pageLength: 25,
                    order: [], // pertahankan urutan dari server (tanggal, lalu id)
                    columns: [
                        { data: 'no', orderable: false, searchable: false },
                        { data: 'tanggal', render: function (data) { return escapeHtml(data || '-'); } },
                        { data: 'outlet', render: function (data) { return escapeHtml(data || '-'); } },
                        { data: 'batch', render: function (data) { return '<span class="label label-default">' + escapeHtml(data || '-') + '</span>'; } },
                        { data: 'type', render: function (data, type, row) {
                            return row.is_return ? '<span class="label label-warning">Return</span>' : escapeHtml(data || '-');
                        } },
                        { data: 'stok_awal', className: 'text-right', render: function (data) { return qtyDisplay(data); } },
                        { data: 'masuk', className: 'text-right', render: function (data) { return qtyDisplay(data); } },
                        { data: 'keluar', className: 'text-right', render: function (data) { return qtyDisplay(data); } },
                        { data: 'stok_akhir', className: 'text-right', render: function (data) { return '<strong>' + qtyDisplay(data) + '</strong>'; } },
                        { data: 'harga', className: 'text-right', render: function (data) { return formatRupiah(data); } },
                        { data: 'nilai', className: 'text-right', render: function (data) { return '<strong>' + formatRupiah(data) + '</strong>'; } },
                        { data: 'keterangan', render: function (data) { return '<small>' + escapeHtml(data || '-') + '</small>'; } }
                    ]
                });
            }

            function renderSummary(response) {
                var summary = response.product_summary || { total_qty: 0, breakdown: [] };
                var product = response.product || {};
                var unit = product.satuan || 'unit';

                $('#card-summary').html('<strong>' + escapeHtml(product.name) + '</strong>: ' + qtyDisplay(summary.total_qty) + ' ' + escapeHtml(unit) + ' tersedia &middot; ' + (summary.batch_count || 0) + ' batch').show();
                $('#displayProduct').text(product.name || '-');
                $('#displayCode').text(product.code || '-');
                $('#displaySupplier').text(product.suppliers || '-');
                $('#displayOutlet').text($('#filterOutlet option:selected').text() || 'Semua Outlet');
                $('#cardInfoTable').show();

                $('#breakdownProductName').text(product.name || '-');
                var $body = $('#breakdownBody').empty();
                var $foot = $('#breakdownFoot').empty();

                if (!summary.breakdown || !summary.breakdown.length) {
                    $body.html('<tr><td colspan="7" class="text-center">Tidak ada batch.</td></tr>');
                } else {
                    var html = summary.breakdown.map(function (item) {
                        return '<tr>' +
                            '<td>' + escapeHtml(item.outlet || '-') + '</td>' +
                            '<td>' + escapeHtml(item.batch || '-') + '</td>' +
                            '<td>' + escapeHtml(item.serial_number || '-') + '</td>' +
                            '<td>' + escapeHtml(item.expired_at || '-') + '</td>' +
                            '<td>' + escapeHtml(item.supplier || '-') + '</td>' +
                            '<td class="text-right">' + formatRupiah(item.hpp) + '</td>' +
                            '<td class="text-right">' + qtyDisplay(item.qty) + '</td>' +
                            '</tr>';
                    }).join('');
                    $body.html(html);

                    $foot.html(
                        '<tr>' +
                        '<th colspan="6" class="text-right">TOTAL STOK TOKO (SEMUA BATCH)</th>' +
                        '<th class="text-right">' + qtyDisplay(summary.total_qty) + '</th>' +
                        '</tr>' +
                        '<tr>' +
                        '<th colspan="6" class="text-right">TOTAL NILAI PERSEDIAAN</th>' +
                        '<th class="text-right">' + formatRupiah(summary.total_nilai) + '</th>' +
                        '</tr>'
                    );
                }
                $('#productStockBreakdown').show();
            }

            function loadCard() {
                var productId = $('#selectProduct').val();
                if (!productId) return;

                var button = $('#btnLoadCard');
                button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
                resetTable('Memuat data...');

                $.get(cardDataUrl, {
                    outlet_id: currentOutlet(),
                    product_id: productId
                }).done(function (response) {
                    productMeta = response.product || {};
                    allTransactions = response.transactions || [];
                    renderSummary(response);
                    renderTable();
                }).fail(function () {
                    resetTable('Gagal memuat kartu stock.');
                    $('#card-summary').text('Gagal memuat kartu stock.').show();
                }).always(function () {
                    button.prop('disabled', !$('#selectProduct').val()).html('<i class="fa fa-search"></i> Tampilkan Kartu');
                });
            }

            // Select2 AJAX: produk dicari di server (20 per halaman), bukan dimuat semua ke halaman.
            $('#selectProduct').select2({
                placeholder: '-- Pilih Produk --',
                width: '100%',
                allowClear: true,
                minimumInputLength: 2,
                ajax: {
                    url: searchUrl,
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return {
                            q: params.term,
                            page: params.page || 1,
                            outlet_id: currentOutlet(),
                            kategori: $('#filterKategori').val(),
                            lokasi: $('#filterLokasi').val(),
                            supplier_id: $('#filterSupplier').val()
                        };
                    },
                    processResults: function (data) {
                        return { results: data.results, pagination: data.pagination };
                    },
                    cache: true
                },
                language: {
                    inputTooShort: function () { return 'Ketik minimal 2 huruf untuk mencari produk...'; },
                    searching: function () { return 'Mencari...'; },
                    noResults: function () { return 'Produk tidak ditemukan'; }
                }
            });

            $('#selectProduct').on('change', function () {
                $('#btnLoadCard').prop('disabled', !$(this).val());
            });
            $('#btnLoadCard').on('click', loadCard);

            // Ganti filter -> pilihan produk dikosongkan supaya pencarian berikutnya memakai filter baru.
            $('#filterOutlet, #filterKategori, #filterLokasi, #filterSupplier').on('change', function () {
                $('#selectProduct').val(null).trigger('change');
                clearResult();
            });

            $('#filterFrom, #filterTo, #filterType').on('change', function () {
                if (allTransactions.length) renderTable();
            });

            $('#resetOwnerCardFilters').on('click', function () {
                window.location = '{{ route('owner-stocks.kartu') }}';
            });

            if ($('#selectProduct').val()) {
                $('#btnLoadCard').prop('disabled', false);
                loadCard();
            }
        });
    </script>
@endsection