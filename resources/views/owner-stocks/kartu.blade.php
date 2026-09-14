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
                        <form id="ownerCardFilterForm" method="GET" action="{{ route('owner-stocks.kartu') }}">
                            <div class="stock-filter-bar">
                                <div class="stock-filter">
                                    <label for="filterOutlet">Outlet</label>
                                    <select id="filterOutlet" name="outlet_id" class="form-control input-sm select2"
                                        {{ auth()->user()->outlet_id ? 'disabled' : '' }}>
                                        <option value="">Semua Outlet</option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}" {{ $selectedOwner?->id == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                    @if (auth()->user()->outlet_id)
                                        <input type="hidden" name="outlet_id" value="{{ auth()->user()->outlet_id }}">
                                    @endif
                                </div>
                                <div class="stock-filter">
                                    <label for="filterKategori">Kategori</label>
                                    <select id="filterKategori" name="kategori" class="form-control input-sm select2">
                                        <option value="">Semua Kategori</option>
                                        @foreach ($categoryOptions as $category)
                                            <option value="{{ $category }}" {{ request('kategori') == $category ? 'selected' : '' }}>{{ $category }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="stock-filter">
                                    <label for="filterLokasi">Lokasi</label>
                                    <select id="filterLokasi" name="lokasi" class="form-control input-sm select2">
                                        <option value="">Semua Lokasi</option>
                                        @foreach ($locationOptions as $location)
                                            <option value="{{ $location }}" {{ request('lokasi') == $location ? 'selected' : '' }}>{{ $location }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="stock-filter">
                                    <label for="filterSupplier">Supplier</label>
                                    <select id="filterSupplier" name="supplier_id" class="form-control input-sm select2">
                                        <option value="">Semua Supplier</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="stock-filter product-filter">
                                    <label for="selectProduct">Produk</label>
                                    <select id="selectProduct" name="product_id" class="form-control input-sm" {{ $products->isEmpty() ? 'disabled' : '' }}>
                                        <option value="">Pilih produk</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>
                                                {{ $product->code }} — {{ $product->name }}
                                            </option>
                                        @endforeach
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
                        </form>
                        <p class="text-muted" style="margin-top:10px; margin-bottom:0;">
                            Semua transaksi dan batch untuk produk terpilih ditampilkan secara default.
                        </p>

                        <div id="card-summary" class="alert alert-info stock-info" style="display:none;"></div>
                        <table id="cardInfoTable" class="table table-bordered table-condensed stock-info" style="display:none;">
                            <tr><td>Nama Produk</td><td><span id="displayProduct">-</span></td></tr>
                            <tr><td>Barcode</td><td><span id="displayCode">-</span></td></tr>
                            <tr><td>Outlet</td><td><span id="displayOutlet">Semua Outlet</span></td></tr>
                        </table>

                        <div class="table-responsive">
                            <table id="kartuTable" class="table table-bordered table-striped stock-table">
                                <thead><tr><th>No</th><th>Tanggal</th><th>Outlet</th><th>Tipe</th><th>Masuk</th><th>Keluar</th><th>Saldo</th><th>Keterangan / Referensi</th></tr></thead>
                                <tbody id="card-rows"><tr><td colspan="8" class="text-center">Pilih produk untuk menampilkan data.</td></tr></tbody>
                            </table>
                        </div>

                        <div id="productStockBreakdown" class="stock-breakdown" style="display:none;">
                            <h4>Rincian Batch <small>(Produk: <span id="breakdownProductName">-</span>)</small></h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-condensed">
                                    <thead><tr><th>Outlet</th><th>Batch</th><th>Serial</th><th>Expired</th><th>HPP</th><th class="text-right">Saldo</th></tr></thead>
                                    <tbody id="breakdownBody"></tbody>
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
    <script>
        $(function () {
            let allTransactions = [];
            let productMeta = {};
            let kartuTable = null;

            function escapeHtml(value) { return $('<div>').text(value == null ? '' : value).html(); }

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

            function resetTable(message) {
                if (kartuTable) { kartuTable.destroy(); kartuTable = null; }
                $('#card-rows').html('<tr><td colspan="8" class="text-center">' + escapeHtml(message) + '</td></tr>');
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
                    var date = String(row.date || '').slice(0, 10);
                    return (!from || date >= from) && (!to || date <= to) && (!type || transactionType(row) === type);
                });
            }

            function renderTable() {
                if (kartuTable) { kartuTable.destroy(); kartuTable = null; }
                var rows = filteredTransactions();
                var body = $('#card-rows').empty();
                if (!rows.length) {
                    body.html('<tr><td colspan="8" class="text-center">Tidak ada pergerakan untuk filter ini.</td></tr>');
                    return;
                }
                rows.forEach(function (row) {
                    var type = row.is_return ? '<span class="label label-warning">Return</span>' : escapeHtml(row.type || '-');
                    body.append('<tr><td></td><td>' + escapeHtml(row.date || '-') + '</td><td>' + escapeHtml(row.outlet || '-') + '</td><td>' + type + '</td><td class="text-right">' + qtyDisplay(row.qty_in) + '</td><td class="text-right">' + qtyDisplay(row.qty_out) + '</td><td class="text-right"><strong>' + qtyDisplay(row.balance) + '</strong></td><td>' + escapeHtml(row.notes || '-') + '</td></tr>');
                });
                kartuTable = $('#kartuTable').DataTable({
                    order: [[1, 'asc']],
                    columnDefs: [{ targets: [0], orderable: false, searchable: false }],
                    columns: [{ data: null, render: function (data, type, row, meta) { return meta.row + 1; } }, null, null, null, null, null, null, null]
                });
            }

            function renderSummary(response) {
                var summary = response.summary || { qty: 0, batches: [] };
                var unit = response.product.satuan || 'unit';
                $('#card-summary').html('<strong>' + escapeHtml(response.product.name) + '</strong>: ' + qtyDisplay(summary.qty) + ' ' + escapeHtml(unit) + ' tersedia · ' + summary.batches.length + ' batch').show();
                $('#displayProduct').text(response.product.name || '-');
                $('#displayCode').text(response.product.code || '-');
                $('#displayOutlet').text($('#filterOutlet option:selected').text() || 'Semua Outlet');
                $('#cardInfoTable').show();
                $('#breakdownProductName').text(response.product.name || '-');
                var batches = (summary.batches || []).map(function (batch) {
                    return '<tr><td>' + escapeHtml(batch.outlet || '-') + '</td><td>' + escapeHtml(batch.batch_number || '-') + '</td><td>' + escapeHtml(batch.serial_number || '-') + '</td><td>' + escapeHtml(batch.expired_at || '-') + '</td><td>' + escapeHtml(new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(batch.hpp || 0)) + '</td><td class="text-right">' + qtyDisplay(batch.qty) + '</td></tr>';
                }).join('');
                $('#breakdownBody').html(batches || '<tr><td colspan="6" class="text-center">Tidak ada batch.</td></tr>');
                $('#productStockBreakdown').show();
            }

            function loadCard() {
                var product = $('#selectProduct').val();
                if (!product) return;
                var button = $('#btnLoadCard');
                button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
                resetTable('Memuat data...');
                $.get('{{ route('owner-stocks.kartu.data') }}', {
                    outlet_id: $('#filterOutlet').val(),
                    product_id: product
                }).done(function (response) {
                    productMeta = response.product || {};
                    allTransactions = response.transactions || [];
                    renderSummary(response);
                    renderTable();
                }).fail(function () {
                    resetTable('Gagal memuat kartu stock.');
                    $('#card-summary').text('Gagal memuat kartu stock.').show();
                }).always(function () {
                    button.prop('disabled', false).html('<i class="fa fa-search"></i> Tampilkan Kartu');
                });
            }

            $('#selectProduct').select2({ width: '100%', placeholder: 'Pilih produk', allowClear: true });
            $('#selectProduct').on('change', function () { $('#btnLoadCard').prop('disabled', !$(this).val()); });
            $('#btnLoadCard').on('click', loadCard);
            $('#filterFrom, #filterTo, #filterType').on('change', function () { if (allTransactions.length) renderTable(); });
            $('#filterOutlet, #filterKategori, #filterLokasi, #filterSupplier').on('change', function () {
                if (this.id !== 'filterOutlet' && this.id !== 'filterKategori' && this.id !== 'filterLokasi' && this.id !== 'filterSupplier') return;
                $('#ownerCardFilterForm').submit();
            });
            $('#resetOwnerCardFilters').on('click', function () { window.location = '{{ route('owner-stocks.kartu') }}'; });

            if ($('#selectProduct').val()) {
                $('#btnLoadCard').prop('disabled', false);
                loadCard();
            }
        });
    </script>
@endsection
