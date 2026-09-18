@extends('layouts.master')

@section('title', 'Stock Opname')

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
        @media (max-width:767px) {
            .stock-filter, .stock-filter.search-filter, .stock-filter .form-control, .stock-filter .select2-container { width:100% !important; }
            .stock-actions .spacer { display:none; }
            .stock-actions .btn, .stock-actions form { width:100%; }
            .stock-actions form .btn { width:100%; }
        }
    </style>

    <section class="content-header">
        <h1>Stock Opname <small>Penyesuaian stok gudang</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><strong>STOCK OPNAME</strong></h3>
                    </div>
                    <div class="box-body">
                        <div class="stock-filter-bar">
                            <div class="stock-filter">
                                <label for="tglStockOpname">Tanggal Stock Opname</label>
                                <input type="date" id="tglStockOpname" class="form-control input-sm" value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="stock-filter">
                                <label for="filterKategori">Kategori</label>
                                <select id="filterKategori" class="form-control input-sm select2">
                                    <option value="">Semua Kategori</option>
                                    @foreach($kategoriOptions as $category)
                                        <option value="{{ $category }}" {{ request('kategori') == $category ? 'selected' : '' }}>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterLokasi">Lokasi</label>
                                <select id="filterLokasi" class="form-control input-sm select2">
                                    <option value="">Semua Lokasi</option>
                                    @foreach($lokasiOptions as $location)
                                        <option value="{{ $location }}" {{ request('lokasi') == $location ? 'selected' : '' }}>{{ $location }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterSupplier">Supplier <span class="text-danger">*</span></label>
                                <select id="filterSupplier" class="form-control input-sm select2">
                                    <option value="">Pilih supplier terlebih dahulu</option>
                                    @foreach($supplierOptions as $supplier)
                                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter search-filter">
                                <label for="filterSearch">Cari Produk / SKU</label>
                                <input type="search" id="filterSearch" class="form-control input-sm" placeholder="Ketik untuk mencari...">
                            </div>
                            <button type="button" id="resetOpnameFilters" class="btn btn-default btn-sm">
                                <i class="fa fa-refresh"></i> Reset
                            </button>
                        </div>
                        <p class="text-muted" style="margin:10px 0 15px;">
                            Pilih supplier terlebih dahulu untuk memuat data stock opname. Kategori dan lokasi bersifat opsional.
                        </p>

                        <div class="table-responsive">
                            <table id="datatable" class="table table-bordered table-striped stock-table">
                                <thead>
                                    <tr>
                                        <th>No</th><th>Barcode</th><th>Product</th><th>SKU</th><th>Satuan</th>
                                        <th>Stock Fisik</th><th>Stock di Kartu</th><th>Selisih</th><th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <tr><td colspan="9" class="text-center">Memuat data stock...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="stock-actions">
                            <button id="tambahBaris" class="btn btn-primary btn-sm" disabled>
                                <i class="fa fa-plus-circle"></i> Tambah Baris
                            </button>
                            <span class="spacer"></span>
                            <a id="btnExportTemplate" href="#" class="btn btn-default btn-sm disabled" aria-disabled="true">
                                <i class="fa fa-file-excel-o"></i> Export Template
                            </a>
                            <form method="GET" action="{{ route('laporan.stock-opname') }}" style="display:inline;">
                                <input type="hidden" name="tanggal" id="exportTanggal" value="{{ date('Y-m-d') }}">
                                <input type="hidden" name="lokasi" id="exportLokasi" value="{{ request('lokasi') }}">
                                <input type="hidden" name="kategori" id="exportKategori" value="{{ request('kategori') }}">
                                <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-file-excel-o"></i> Export Laporan</button>
                            </form>
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
            let allStockData = [];

            function escapeHtml(value) { return $('<div>').text(value == null ? '' : value).html(); }

            function setState(message, enabled) {
                $('#tableBody').html('<tr><td colspan="9" class="text-center">' + escapeHtml(message) + '</td></tr>');
                $('#tambahBaris, #btnSaveOpname').prop('disabled', !enabled);
                allStockData = [];
            }

            function updateNumbers() {
                $('#tableBody tr:visible').each(function (index) { $(this).find('td:first').text(index + 1); });
            }

            function applySearchFilter() {
                var search = String($('#filterSearch').val() || '').toLowerCase().trim();
                $('#tableBody tr').each(function () {
                    var row = $(this);
                    if (!row.find('.stock_fisik').length && !row.find('.select-stock').length) return;
                    row.toggle(!search || String(row.data('search') || '').indexOf(search) !== -1);
                });
                updateNumbers();
            }

            function calculateDifference(row) {
                var physical = parseFloat(row.find('.stock_fisik').val()) || 0;
                var system = parseFloat(row.find('.stock_dikartu').val()) || 0;
                row.find('.selisih').val((physical - system).toFixed(2));
            }

            function renderRows() {
                var body = $('#tableBody').empty();
                if (!allStockData.length) {
                    body.html('<tr><td colspan="9" class="text-center">Tidak ada data stock untuk filter ini.</td></tr>');
                    $('#tambahBaris, #btnSaveOpname').prop('disabled', true);
                    return;
                }

                allStockData.forEach(function (item, index) {
                    var search = [item.product_code, item.product_name, item.sku, item.supplier, item.kategori, item.lokasi].join(' ').toLowerCase();
                    body.append('<tr data-search="' + escapeHtml(search) + '">' +
                        '<td>' + (index + 1) + '</td>' +
                        '<td><input type="text" class="form-control input-sm" value="' + escapeHtml(item.product_code) + '" disabled></td>' +
                        '<td><input type="text" class="form-control input-sm product-name" value="' + escapeHtml(item.product_name) + '" data-stock-id="' + item.id + '" disabled></td>' +
                        '<td><input type="text" class="form-control input-sm sku" value="' + escapeHtml(item.sku) + '" disabled></td>' +
                        '<td><input type="text" class="form-control input-sm satuan" value="' + escapeHtml(item.satuan) + '" disabled></td>' +
                        '<td><input type="number" step="0.01" min="0" class="form-control input-sm stock_fisik" value="' + item.qty + '"></td>' +
                        '<td><input type="number" class="form-control input-sm stock_dikartu" value="' + item.qty + '" disabled></td>' +
                        '<td><input type="number" step="0.01" class="form-control input-sm selisih" value="0" disabled></td>' +
                        '<td><input type="text" class="form-control input-sm keterangan" value="' + escapeHtml(item.keterangan || '') + '"></td>' +
                        '</tr>');
                });
                $('#tableBody .stock_fisik').on('input', function () { calculateDifference($(this).closest('tr')); });
                $('#tambahBaris, #btnSaveOpname').prop('disabled', false);
                applySearchFilter();
            }

            function loadStockData() {
                if (!$('#filterSupplier').val()) {
                    setState('Pilih supplier terlebih dahulu.', false);
                    return;
                }
                setState('Memuat data...', false);
                $.get('{{ route('stock.opname.data') }}', {
                    kategori: $('#filterKategori').val(),
                    lokasi: $('#filterLokasi').val(),
                    supplier_id: $('#filterSupplier').val()
                }).done(function (response) {
                    allStockData = response.stocks || [];
                    renderRows();
                }).fail(function () {
                    setState('Gagal memuat data. Silakan coba lagi.', false);
                });
            }

            function updateExportLinks() {
                var params = new URLSearchParams();
                var kategori = $('#filterKategori').val();
                var lokasi = $('#filterLokasi').val();
                var supplier = $('#filterSupplier').val();
                if (kategori) params.set('kategori', kategori);
                if (lokasi) params.set('lokasi', lokasi);
                if (supplier) params.set('supplier_id', supplier);
                var query = params.toString();
                $('#btnExportTemplate')
                    .attr('href', supplier ? '{{ route('stock.opname.export-template') }}' + (query ? '?' + query : '') : '#')
                    .toggleClass('disabled', !supplier)
                    .attr('aria-disabled', supplier ? 'false' : 'true');
                $('#exportLokasi').val(lokasi);
                $('#exportKategori').val(kategori);
            }

            $('#filterKategori, #filterLokasi, #filterSupplier').on('change', function () {
                updateExportLinks();
                loadStockData();
            });
            $('#filterSearch').on('input', applySearchFilter);
            $('#tglStockOpname').on('change', function () { $('#exportTanggal').val($(this).val()); });
            $('#resetOpnameFilters').on('click', function () {
                $('#filterKategori, #filterLokasi, #filterSupplier').val('').trigger('change.select2');
                $('#filterSearch').val('');
                updateExportLinks();
                loadStockData();
            });

            $('#btnExportTemplate').on('click', function (event) {
                if (!$('#filterSupplier').val()) event.preventDefault();
            });

            $('#tambahBaris').on('click', function (event) {
                event.preventDefault();
                if (!allStockData.length) return;
                var options = allStockData.map(function (item) {
                    return '<option value="' + item.id + '" data-product-code="' + escapeHtml(item.product_code) + '" data-product-name="' + escapeHtml(item.product_name) + '" data-sku="' + escapeHtml(item.sku) + '" data-satuan="' + escapeHtml(item.satuan) + '" data-qty="' + item.qty + '">' + escapeHtml(item.product_name + ' - SKU: ' + item.sku) + '</option>';
                }).join('');
                $('#tableBody').append('<tr data-search=""><td></td><td><input type="text" class="form-control input-sm product-code" disabled></td><td><select class="form-control input-sm select-stock"><option value="">Pilih stock</option>' + options + '</select></td><td><input type="text" class="form-control input-sm sku" disabled></td><td><input type="text" class="form-control input-sm satuan" disabled></td><td><input type="number" step="0.01" min="0" class="form-control input-sm stock_fisik" value="0"></td><td><input type="number" class="form-control input-sm stock_dikartu" disabled></td><td><input type="number" step="0.01" class="form-control input-sm selisih" value="0" disabled></td><td><input type="text" class="form-control input-sm keterangan"></td></tr>');
                var row = $('#tableBody tr:last');
                row.find('.select-stock').on('change', function () {
                    var option = $(this).find(':selected');
                    row.find('.product-code').val(option.data('product-code') || '');
                    row.find('.sku').val(option.data('sku') || '');
                    row.find('.satuan').val(option.data('satuan') || '');
                    row.find('.stock_dikartu').val(option.data('qty') || 0);
                    row.find('.stock_fisik').val(option.data('qty') || 0);
                    calculateDifference(row);
                    row.attr('data-search', [option.data('product-code'), option.data('product-name'), option.data('sku')].join(' ').toLowerCase());
                });
                row.find('.stock_fisik').on('input', function () { calculateDifference(row); });
                updateNumbers();
            });

            $('#btnSaveOpname').on('click', function () {
                var date = $('#tglStockOpname').val();
                if (!date) { alert('Tanggal Stock Opname harus diisi.'); return; }
                var items = [];
                $('#tableBody tr').each(function () {
                    var row = $(this);
                    var stockId = row.find('.product-name').data('stock-id') || row.find('.select-stock').val();
                    var difference = parseFloat(row.find('.selisih').val()) || 0;
                    if (stockId && difference !== 0) {
                        items.push({
                            stock_id: stockId,
                            selisih: difference,
                            system_qty: parseFloat(row.find('.stock_dikartu').val()) || 0,
                            physical_qty: parseFloat(row.find('.stock_fisik').val()) || 0,
                            keterangan: String(row.find('.keterangan').val() || '').trim()
                        });
                    }
                });
                if (!items.length) { alert('Tidak ada perubahan stock untuk disimpan.'); return; }
                if (!confirm('Simpan ' + items.length + ' penyesuaian stock?')) return;
                $.ajax({
                    url: '{{ route('stock.opname.save') }}', method: 'POST', contentType: 'application/json',
                    data: JSON.stringify({ _token: '{{ csrf_token() }}', adjustment_date: date, items: items })
                }).done(function (response) {
                    if (response.success) { alert(response.message || 'Stock opname berhasil disimpan.'); loadStockData(); }
                    else alert(response.message || 'Gagal menyimpan stock opname.');
                }).fail(function () { alert('Terjadi kesalahan saat menyimpan data.'); });
            });

            updateExportLinks();
            loadStockData();
        });
    </script>
@endsection
