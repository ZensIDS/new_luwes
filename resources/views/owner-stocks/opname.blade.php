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
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><strong>STOCK OPNAME TOKO</strong></h3>
                    </div>
                    <div class="box-body">
                        <form id="ownerOpnameOutletForm" method="GET" action="{{ route('owner-stock-opname') }}">
                            <div class="stock-filter-bar">
                                <div class="stock-filter">
                                    <label for="filterOutlet">Outlet</label>
                                    <select id="filterOutlet" name="outlet_id" class="form-control input-sm select2"
                                        {{ auth()->user()->outlet_id ? 'disabled' : '' }}>
                                        <option value="">Pilih outlet</option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}" {{ $selectedOwner?->id == $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                    @if (auth()->user()->outlet_id)
                                        <input type="hidden" name="outlet_id" value="{{ auth()->user()->outlet_id }}">
                                    @endif
                                </div>
                                <div class="stock-filter">
                                    <label for="tglStockOpname">Tanggal Stock Opname</label>
                                    <input type="date" id="tglStockOpname" class="form-control input-sm" value="{{ date('Y-m-d') }}">
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
                                <div class="stock-filter search-filter">
                                    <label for="filterSearch">Cari Produk / Batch / SKU</label>
                                    <input type="search" id="filterSearch" class="form-control input-sm" placeholder="Ketik untuk mencari...">
                                </div>
                                <button type="button" id="resetOwnerOpnameFilters" class="btn btn-default btn-sm">
                                    <i class="fa fa-refresh"></i> Reset
                                </button>
                            </div>
                        </form>
                        <p class="text-muted" style="margin:10px 0 15px;">
                            @if ($selectedOwner)
                                Semua stok {{ $selectedOwner->name }} ditampilkan secara default. Supplier, kategori, dan lokasi bersifat opsional.
                            @else
                                Pilih outlet untuk memuat data opname. Supplier, kategori, dan lokasi bersifat opsional.
                            @endif
                        </p>

                        <div class="table-responsive">
                            <table id="ownerOpnameTable" class="table table-bordered table-striped stock-table">
                                <thead>
                                    <tr>
                                        <th>No</th><th>Code</th><th>Product</th><th>Batch / SKU</th><th>Supplier</th>
                                        <th>Satuan</th><th>Stock Fisik</th><th>Stock di Kartu</th><th>Selisih</th><th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <tr><td colspan="10" class="text-center">{{ $selectedOwner ? 'Memuat data stock...' : 'Pilih outlet terlebih dahulu.' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="stock-actions">
                            <span class="spacer"></span>
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
            const hasOwner = @json((bool) $selectedOwner);
            let allStockData = [];

            function escapeHtml(value) { return $('<div>').text(value == null ? '' : value).html(); }

            function setState(message, enabled) {
                $('#tableBody').html('<tr><td colspan="10" class="text-center">' + escapeHtml(message) + '</td></tr>');
                $('#btnSaveOpname').prop('disabled', !enabled);
                allStockData = [];
            }

            function calculateDifference(row) {
                var physical = parseFloat(row.find('.stock_fisik').val()) || 0;
                var system = parseFloat(row.find('.stock_dikartu').val()) || 0;
                row.find('.selisih').val((physical - system).toFixed(2));
            }

            function updateNumbers() {
                $('#tableBody tr:visible').each(function (index) { $(this).find('td:first').text(index + 1); });
            }

            function applySearchFilter() {
                var search = String($('#filterSearch').val() || '').toLowerCase().trim();
                $('#tableBody tr').each(function () {
                    var row = $(this);
                    if (!row.find('.stock_fisik').length) return;
                    row.toggle(!search || String(row.data('search') || '').indexOf(search) !== -1);
                });
                updateNumbers();
            }

            function renderRows() {
                var body = $('#tableBody').empty();
                if (!allStockData.length) {
                    body.html('<tr><td colspan="10" class="text-center">Tidak ada data stock untuk filter ini.</td></tr>');
                    $('#btnSaveOpname').prop('disabled', true);
                    return;
                }
                allStockData.forEach(function (item, index) {
                    var batch = item.batch_number || item.serial_number || '-';
                    var search = [item.product_code, item.product_name, batch, item.supplier, item.kategori, item.lokasi].join(' ').toLowerCase();
                    body.append('<tr data-search="' + escapeHtml(search) + '">' +
                        '<td>' + (index + 1) + '</td>' +
                        '<td>' + escapeHtml(item.product_code || '-') + '</td>' +
                        '<td>' + escapeHtml(item.product_name || '-') + '</td>' +
                        '<td>' + escapeHtml(batch) + '</td>' +
                        '<td>' + escapeHtml(item.supplier || '-') + '</td>' +
                        '<td>' + escapeHtml(item.satuan || 'pcs') + '</td>' +
                        '<td><input type="number" step="0.01" min="0" class="form-control input-sm stock_fisik" value="' + item.qty + '"></td>' +
                        '<td><input type="number" class="form-control input-sm stock_dikartu" value="' + item.qty + '" disabled></td>' +
                        '<td><input type="number" step="0.01" class="form-control input-sm selisih" value="0" disabled></td>' +
                        '<td><input type="text" class="form-control input-sm keterangan"></td>' +
                        '</tr>');
                    body.find('tr:last').attr('data-stock-id', item.id);
                });
                $('#tableBody .stock_fisik').on('input', function () { calculateDifference($(this).closest('tr')); });
                $('#btnSaveOpname').prop('disabled', false);
                applySearchFilter();
            }

            function loadStockData() {
                if (!hasOwner || !$('#filterOutlet').val()) {
                    setState('Pilih outlet terlebih dahulu.', false);
                    return;
                }
                setState('Memuat data...', false);
                $.get('{{ route('owner-stock-opname.data') }}', {
                    outlet_id: $('#filterOutlet').val(),
                    supplier_id: $('#filterSupplier').val(),
                    kategori: $('#filterKategori').val(),
                    lokasi: $('#filterLokasi').val()
                }).done(function (response) {
                    allStockData = response.stocks || [];
                    renderRows();
                }).fail(function () {
                    setState('Gagal memuat data. Silakan coba lagi.', false);
                });
            }

            $('#filterOutlet').on('change', function () { $('#ownerOpnameOutletForm').submit(); });
            $('#filterKategori, #filterLokasi, #filterSupplier').on('change', loadStockData);
            $('#filterSearch').on('input', applySearchFilter);
            $('#resetOwnerOpnameFilters').on('click', function () {
                $('#filterKategori, #filterLokasi, #filterSupplier').val('').trigger('change.select2');
                $('#filterSearch').val('');
                loadStockData();
            });

            $('#btnSaveOpname').on('click', function () {
                var date = $('#tglStockOpname').val();
                if (!date) { alert('Tanggal Stock Opname harus diisi.'); return; }
                var items = [];
                $('#tableBody tr').each(function () {
                    var row = $(this);
                    var difference = parseFloat(row.find('.selisih').val()) || 0;
                    if (row.data('stock-id') && difference !== 0) {
                        items.push({
                            owner_stock_id: row.data('stock-id'),
                            physical_qty: parseFloat(row.find('.stock_fisik').val()) || 0,
                            keterangan: String(row.find('.keterangan').val() || '').trim()
                        });
                    }
                });
                if (!items.length) { alert('Tidak ada perubahan stock untuk disimpan.'); return; }
                if (!confirm('Simpan ' + items.length + ' penyesuaian stock?')) return;
                $.ajax({
                    url: '{{ route('owner-stock-opname.save') }}', method: 'POST', contentType: 'application/json',
                    data: JSON.stringify({
                        _token: '{{ csrf_token() }}', outlet_id: $('#filterOutlet').val(),
                        supplier_id: $('#filterSupplier').val() || null, adjustment_date: date, items: items
                    })
                }).done(function (response) {
                    if (response.success) { alert(response.message || 'Stock opname toko berhasil disimpan.'); loadStockData(); }
                    else alert(response.message || 'Gagal menyimpan stock opname.');
                }).fail(function () { alert('Terjadi kesalahan saat menyimpan data.'); });
            });

            if (hasOwner) loadStockData();
        });
    </script>
@endsection
