@extends('layouts.master')

@section('title', 'Kartu Stok')

@section('container')
    <style>
        .stock-filter-bar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
        .stock-filter { min-width:160px; margin:0; }
        .stock-filter.product-filter { min-width:280px; flex:1 1 280px; }
        .stock-filter label { display:block; margin-bottom:4px; font-size:12px; color:#666; }
        .stock-filter .form-control, .stock-filter .select2-container { min-width:160px; }
        .stock-filter.product-filter .select2-container { width:100% !important; }
        .stock-filter-actions { display:flex; gap:6px; align-items:flex-end; flex-wrap:wrap; }
        .stock-table th, .stock-table td { vertical-align:middle !important; }
        .stock-table .btn { margin:1px 0; }
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
        <h1>Kartu Stok <small>Riwayat pergerakan stok gudang</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><strong>KARTU STOK</strong></h3>
                    </div>
                    <div class="box-body">
                        <div class="stock-filter-bar">
                            <div class="stock-filter">
                                <label for="filterKategori">Kategori</label>
                                <select id="filterKategori" class="form-control input-sm select2">
                                    <option value="">Semua Kategori</option>
                                    @foreach($kategoriOptions as $category)
                                        <option value="{{ $category }}">{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterLokasi">Lokasi</label>
                                <select id="filterLokasi" class="form-control input-sm select2">
                                    <option value="">Semua Lokasi</option>
                                    @foreach($lokasiOptions as $location)
                                        <option value="{{ $location }}">{{ $location }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterSupplier">Supplier</label>
                                <select id="filterSupplier" class="form-control input-sm select2">
                                    <option value="">Semua Supplier</option>
                                    @foreach($supplierOptions as $supplier)
                                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter product-filter">
                                <label for="selectProduct">Produk</label>
                                <select id="selectProduct" class="form-control input-sm">
                                    <option value="">Pilih produk</option>
                                </select>
                            </div>
                            <div class="stock-filter-actions">
                                <button id="btnLoadKartu" class="btn btn-primary btn-sm" disabled>
                                    <i class="fa fa-search"></i> Tampilkan Kartu
                                </button>
                                <a id="btnExportKartu" href="#" class="btn btn-success btn-sm" style="pointer-events:none; opacity:.6;">
                                    <i class="fa fa-file-excel-o"></i> Excel
                                </a>
                                <a id="btnExportPdfKartu" href="#" target="_blank" class="btn btn-danger btn-sm" style="pointer-events:none; opacity:.6;">
                                    <i class="fa fa-file-pdf-o"></i> PDF
                                </a>
                            </div>
                        </div>
                        <p class="text-muted" style="margin-top:10px; margin-bottom:0;">
                            Semua SKU dan transaksi untuk produk terpilih ditampilkan secara default.
                        </p>

                        <div id="productSummary" class="alert alert-info stock-info" style="display:none;"></div>
                        <table class="table table-bordered table-condensed stock-info" style="display:none;" id="productInfoTable">
                            <tr><td>Nama Produk</td><td><span id="displayProduct">-</span></td></tr>
                            <tr><td>Barcode</td><td><span id="displayCode">-</span></td></tr>
                            <tr><td>Supplier</td><td><span id="displaySupplier">-</span></td></tr>
                        </table>

                        <div class="table-responsive">
                            <table id="kartuTable" class="table table-bordered table-striped stock-table">
                                <thead>
                                    <tr>
                                        <th>No</th><th>Tanggal</th><th>SKU</th><th>Stok Awal</th><th>Masuk</th>
                                        <th>Keluar</th><th>Stok Akhir</th><th>Harga Satuan (Rp)</th>
                                        <th>Nilai Persediaan</th><th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <tr><td colspan="10" class="text-center">Pilih produk untuk menampilkan data.</td></tr>
                                </tbody>
                                <tfoot>
                                    <tr id="rowTotalStokProduk" style="display:none;">
                                        <th colspan="8" class="text-right">TOTAL STOK PRODUK (SEMUA SKU)</th>
                                        <th id="totalStokProduk">0</th><th></th>
                                    </tr>
                                    <tr>
                                        <th colspan="8" class="text-right">TOTAL NILAI PERSEDIAAN</th>
                                        <th id="totalPersediaan">0</th><th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div id="productStockBreakdown" class="stock-breakdown" style="display:none;">
                            <h4>Rincian Stok per SKU <small>(Produk: <span id="breakdownProductName">-</span>)</small></h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-condensed">
                                    <thead><tr><th>SKU</th><th>Supplier</th><th class="text-right">Qty Tersedia</th></tr></thead>
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
            let productMeta = {};
            let allTransactions = [];
            let kartuTable = null;

            function escapeHtml(value) {
                return $('<div>').text(value == null ? '' : value).html();
            }

            function konversiDisplay(qty, konversiQty, satuanBesar, satuan) {
                satuan = satuan || 'PCS';
                qty = parseInt(qty) || 0;
                if (!konversiQty || !satuanBesar) return null;
                var boxes = Math.floor(qty / konversiQty);
                var rem = qty % konversiQty;
                if (rem === 0) return boxes + ' ' + satuanBesar;
                if (boxes > 0) return boxes + ' ' + satuanBesar + ' ' + rem + ' ' + satuan;
                return qty + ' ' + satuan;
            }

            function fmtQty(qty) {
                var converted = konversiDisplay(qty, productMeta.konversi_qty, productMeta.satuan_besar, productMeta.satuan);
                return escapeHtml(qty) + (converted ? ' <span class="label label-info">' + escapeHtml(converted) + '</span>' : '');
            }

            function formatRupiah(amount) {
                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(amount || 0);
            }

            function resetKartuTable(message) {
                if (kartuTable) {
                    kartuTable.destroy();
                    kartuTable = null;
                }
                $('#tableBody').html('<tr><td colspan="10" class="text-center">' + escapeHtml(message) + '</td></tr>');
                $('#totalPersediaan').text('0');
            }

            function renderKartuTable() {
                if (kartuTable) {
                    kartuTable.destroy();
                    kartuTable = null;
                }

                var rows = allTransactions;
                var tbody = $('#tableBody').empty();
                if (!rows.length) {
                    tbody.html('<tr><td colspan="10" class="text-center">Tidak ada transaksi untuk produk ini.</td></tr>');
                } else {
                    rows.forEach(function (item) {
                        tbody.append('<tr>' +
                            '<td></td>' +
                            '<td>' + escapeHtml(item.tanggal) + '</td>' +
                            '<td><span class="label label-default">' + escapeHtml(item.sku) + '</span></td>' +
                            '<td class="text-right">' + fmtQty(item.stok_awal) + '</td>' +
                            '<td class="text-right">' + fmtQty(item.masuk) + '</td>' +
                            '<td class="text-right">' + fmtQty(item.keluar) + '</td>' +
                            '<td class="text-right"><strong>' + fmtQty(item.stok_akhir) + '</strong></td>' +
                            '<td class="text-right">' + escapeHtml(formatRupiah(item.harga)) + '</td>' +
                            '<td class="text-right"><strong>' + escapeHtml(formatRupiah(item.nilai)) + '</strong></td>' +
                            '<td><small>' + escapeHtml(item.keterangan || '-') + '</small></td>' +
                            '</tr>');
                    });
                    kartuTable = $('#kartuTable').DataTable({
                        order: [[1, 'asc']],
                        columnDefs: [{ targets: [0], orderable: false, searchable: false }],
                        columns: [{ data: null, render: function (data, type, row, meta) { return meta.row + 1; } }, null, null, null, null, null, null, null, null, null]
                    });
                }

                var latest = rows.length ? rows[rows.length - 1].nilai : 0;
                $('#totalPersediaan').text(formatRupiah(latest));
            }

            function renderProductStockSummary(summary, product) {
                $('#productSummary').html('<strong>' + escapeHtml(product.name) + '</strong> — ' +
                    fmtQtyStandalone(summary.total_qty) + ' tersedia dari ' + (summary.breakdown || []).length + ' SKU.').show();
                $('#totalStokProduk').html(fmtQtyStandalone(summary.total_qty));
                $('#rowTotalStokProduk').show();
                $('#breakdownProductName').text(product.name);
                var breakdown = (summary.breakdown || []).map(function (item) {
                    return '<tr><td>' + escapeHtml(item.sku) + '</td><td>' + escapeHtml(item.supplier) + '</td><td class="text-right">' + fmtQtyStandalone(item.qty_available) + '</td></tr>';
                }).join('');
                $('#breakdownBody').html(breakdown || '<tr><td colspan="3" class="text-center">Tidak ada data SKU.</td></tr>');
                $('#productStockBreakdown').show();
            }

            function fmtQtyStandalone(qty) {
                var converted = konversiDisplay(qty, productMeta.konversi_qty, productMeta.satuan_besar, productMeta.satuan);
                return escapeHtml(qty) + (converted ? ' <span class="label label-info">' + escapeHtml(converted) + '</span>' : '');
            }

            $('#selectProduct').select2({
                placeholder: 'Pilih produk',
                allowClear: true,
                width: '100%',
                minimumInputLength: 0,
                ajax: {
                    url: '{{ route('stocks.search') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            page: params.page || 1,
                            kategori: $('#filterKategori').val(),
                            lokasi: $('#filterLokasi').val(),
                            supplier_id: $('#filterSupplier').val()
                        };
                    },
                    processResults: function (data) { return { results: data.results, pagination: data.pagination }; },
                    cache: true
                }
            });

            $('#selectProduct').on('change', function () {
                $('#btnLoadKartu').prop('disabled', !$(this).val());
                if (!$(this).val()) {
                    resetKartuTable('Pilih produk untuk menampilkan data.');
                    $('#productSummary, #productInfoTable, #productStockBreakdown').hide();
                }
            });

            $('#filterKategori, #filterLokasi, #filterSupplier').on('change', function () {
                $('#selectProduct').val(null).trigger('change');
            });

            $('#btnLoadKartu').on('click', function () {
                var productId = $('#selectProduct').val();
                if (!productId) return;
                var button = $(this);
                button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
                resetKartuTable('Memuat data...');

                $.get('{{ route('stock.kartu.data') }}', { product_id: productId })
                    .done(function (response) {
                        productMeta = response.product || {};
                        allTransactions = response.transactions || [];
                        $('#displayProduct').text(productMeta.name || '-');
                        $('#displayCode').text(productMeta.code || '-');
                        $('#displaySupplier').text(productMeta.suppliers || '-');
                        $('#productInfoTable').show();
                        renderProductStockSummary(response.product_summary || { total_qty: 0, breakdown: [] }, productMeta);
                        renderKartuTable();
                        $('#btnExportKartu').attr('href', '{{ route('laporan.kartu-stok') }}/' + productId).css({ 'pointer-events': 'auto', opacity: 1 });
                        $('#btnExportPdfKartu').attr('href', '{{ url('laporan/pdf/kartu-stok') }}/' + productId).css({ 'pointer-events': 'auto', opacity: 1 });
                    })
                    .fail(function () { alert('Gagal memuat data kartu stok.'); })
                    .always(function () { button.prop('disabled', false).html('<i class="fa fa-search"></i> Tampilkan Kartu'); });
            });
        });
    </script>
@endsection
