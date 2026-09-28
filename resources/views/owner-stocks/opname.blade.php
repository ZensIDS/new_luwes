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
                                Pilih supplier terlebih dahulu untuk memuat data stock {{ $selectedOwner->name }}. Kategori dan lokasi bersifat opsional.
                            @else
                                Pilih outlet, lalu pilih supplier untuk memuat data stock.
                            @endif
                        </p>

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
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <tr><td colspan="9" class="text-center">{{ $selectedOwner ? 'Silakan pilih Supplier terlebih dahulu.' : 'Pilih outlet terlebih dahulu.' }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="box-footer">
                        <div class="stock-actions">
                            <span id="rowCountInfo" class="text-muted"></span>
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
            const fixedOutletId = @json(auth()->user()->outlet_id);
            const dataUrl = '{{ route('owner-stock-opname.data') }}';
            const saveUrl = '{{ route('owner-stock-opname.save') }}';
            const pageUrl = '{{ route('owner-stock-opname') }}';
            const params = new URLSearchParams(window.location.search);
            const initialSupplier = params.get('supplier_id') || '';
            const $body = $('#tableBody');
            let allStockData = [];

            function escapeHtml(value) { return $('<div>').text(value == null ? '' : value).html(); }
            function currentOutlet() { return $('#filterOutlet').val() || fixedOutletId || ''; }

            function renderEmptyState(message) {
                $body.html('<tr><td colspan="9" class="text-center">' + escapeHtml(message) + '</td></tr>');
                $('#btnSaveOpname').prop('disabled', true);
                $('#rowCountInfo').text('');
                allStockData = [];
            }

            function updateNumbers() {
                var visible = 0;
                $body.find('tr').each(function () {
                    if ($(this).css('display') === 'none') return;
                    visible++;
                    $(this).find('td:first').text(visible);
                });
                $('#rowCountInfo').text(allStockData.length ? visible + ' dari ' + allStockData.length + ' batch ditampilkan' : '');
            }

            function applySearchFilter() {
                var search = String($('#filterSearch').val() || '').toLowerCase().trim();
                $body.find('tr[data-stock-id]').each(function () {
                    var haystack = String($(this).attr('data-search') || '');
                    this.style.display = (!search || haystack.indexOf(search) !== -1) ? '' : 'none';
                });
                updateNumbers();
            }

            // Baris dibangun sekali sebagai satu string (bukan append per baris).
            function renderRows() {
                if (!allStockData.length) {
                    renderEmptyState('Tidak ada data stock untuk filter ini.');
                    return;
                }

                var html = allStockData.map(function (item, index) {
                    var batch = item.batch_number || item.serial_number || '-';
                    var search = [item.product_code, item.product_name, batch].join(' ').toLowerCase();
                    return '<tr data-stock-id="' + item.id + '" data-search="' + escapeHtml(search) + '">' +
                        '<td>' + (index + 1) + '</td>' +
                        '<td><input type="text" class="form-control input-sm" value="' + escapeHtml(item.product_code || '-') + '" disabled></td>' +
                        '<td><input type="text" class="form-control input-sm" value="' + escapeHtml(item.product_name || '-') + '" disabled></td>' +
                        '<td><input type="text" class="form-control input-sm" value="' + escapeHtml(batch) + '" disabled></td>' +
                        '<td><input type="text" class="form-control input-sm" value="' + escapeHtml(item.satuan || 'pcs') + '" disabled></td>' +
                        '<td><input type="number" step="0.01" min="0" class="form-control input-sm stock_fisik" value="' + item.qty + '"></td>' +
                        '<td><input type="number" class="form-control input-sm stock_dikartu" value="' + item.qty + '" disabled></td>' +
                        '<td><input type="number" step="0.01" class="form-control input-sm selisih" value="0" disabled></td>' +
                        '<td><input type="text" class="form-control input-sm keterangan"></td>' +
                        '</tr>';
                }).join('');

                $body.html(html);
                $('#btnSaveOpname').prop('disabled', false);
                applySearchFilter();
            }

            function loadStockData() {
                var outletId = currentOutlet();
                var supplierId = $('#filterSupplier').val();

                if (!outletId) {
                    renderEmptyState('Pilih outlet terlebih dahulu.');
                    return;
                }
                if (!supplierId) {
                    renderEmptyState('Silakan pilih Supplier terlebih dahulu.');
                    return;
                }

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

            // Sama seperti opname gudang: ganti filter -> reload halaman dengan parameter di URL.
            function reloadWithParams(resetSupplier) {
                var next = new URLSearchParams();
                var outletId = currentOutlet();
                var supplierId = $('#filterSupplier').val();
                var kategori = $('#filterKategori').val();
                var lokasi = $('#filterLokasi').val();

                if (outletId && !fixedOutletId) next.append('outlet_id', outletId);
                if (supplierId && !resetSupplier) next.append('supplier_id', supplierId);
                if (kategori) next.append('kategori', kategori);
                if (lokasi) next.append('lokasi', lokasi);

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
                        alert(response.message || 'Stock opname toko berhasil disimpan.');
                        loadStockData();
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

            if (currentOutlet() && initialSupplier) {
                loadStockData();
            } else if (currentOutlet()) {
                renderEmptyState('Silakan pilih Supplier terlebih dahulu.');
            } else {
                renderEmptyState('Pilih outlet terlebih dahulu.');
            }
        });
    </script>
@endsection