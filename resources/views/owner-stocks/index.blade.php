@extends('layouts.master')

@section('title', 'Stock Toko')

@section('container')
    <style>
        .stock-filter-bar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; }
        .stock-filter { min-width:150px; margin:0; }
        .stock-filter label { display:block; margin-bottom:4px; font-size:12px; color:#666; }
        .stock-filter .form-control, .stock-filter .select2-container { min-width:150px; }
        .stock-filter-search { min-width:230px; }
        .stock-action-bar { margin-bottom:15px; display:flex; gap:8px; flex-wrap:wrap; }
        .stock-summary { margin:12px 0 0; color:#666; }
        .stock-summary strong { color:#333; }
        .stock-table th, .stock-table td { vertical-align:middle !important; }
        .stock-table .btn { margin:1px 0; }
        .history-section-title { margin:18px 0 8px; font-weight:600; }
        .modal .table { margin-bottom:0; }
        @media (max-width:767px) {
            .stock-filter, .stock-filter-search, .stock-filter .form-control, .stock-filter .select2-container { width:100% !important; }
        }
    </style>

    <section class="content-header">
        <h1>Stock Toko <small>Saldo stok per outlet</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <div class="stock-action-bar">
                            @if (in_array(auth()->user()->role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet']))
                                <a href="{{ route('delivery-orders.index') }}" class="btn btn-default btn-sm"><i class="fa fa-truck"></i> Riwayat Pengiriman Toko</a>
                                <a href="{{ route('outlet-purchases.create', ['outlet_id' => request('outlet_id')]) }}" class="btn btn-warning btn-sm"><i class="fa fa-shopping-cart"></i> Belanja Langsung → Tambah Stock</a>
                            @endif
                            @if (in_array(auth()->user()->role, ['superadmin', 'admin-gudang', 'owner']))
                                <a href="{{ route('outlet-prices.index') }}" class="btn btn-primary btn-sm"><i class="fa fa-money"></i> Atur Harga Jual POS</a>
                            @endif
                        </div>
                        <div class="stock-filter-bar">
                            <div class="stock-filter">
                                <label for="filterOutlet">Outlet</label>
                                <select id="filterOutlet" class="form-control input-sm select2"
                                    {{ auth()->user()->outlet_id ? 'disabled' : '' }}>
                                    <option value="">Pilih outlet terlebih dahulu</option>
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
                                        <option value="{{ $category }}" {{ request('kategori') == $category ? 'selected' : '' }}>{{ $category }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterLokasi">Lokasi</label>
                                <select id="filterLokasi" class="form-control input-sm select2">
                                    <option value="">Semua Lokasi</option>
                                    @foreach ($locationOptions as $location)
                                        <option value="{{ $location }}" {{ request('lokasi') == $location ? 'selected' : '' }}>{{ $location }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterSupplier">Supplier</label>
                                <select id="filterSupplier" class="form-control input-sm select2">
                                    <option value="">Semua Supplier</option>
                                    @foreach ($supplierOptions as $supplier)
                                        <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterSumber">Sumber</label>
                                <select id="filterSumber" class="form-control input-sm select2">
                                    <option value="">Semua Sumber</option>
                                    @foreach ($sourceOptions as $source)
                                        <option value="{{ $source }}" {{ request('sumber') == $source ? 'selected' : '' }}>
                                            {{ str_contains(strtolower($source), 'delivery') ? 'Delivery Order' : (str_contains(strtolower($source), 'purchase') ? 'Belanja Langsung' : $source) }}
                                        </option>
                                    @endforeach
                                    <option value="multiple">Multiple sources</option>
                                </select>
                            </div>
                            <div class="stock-filter">
                                <label for="filterStatus">Status</label>
                                <select id="filterStatus" class="form-control input-sm select2">
                                    <option value="">Semua Status</option>
                                    <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>Available</option>
                                    <option value="empty" {{ request('status') === 'empty' ? 'selected' : '' }}>Empty</option>
                                    <option value="expired" {{ request('status') === 'expired' ? 'selected' : '' }}>Expired</option>
                                </select>
                            </div>
                            <button type="button" id="resetOwnerFilters" class="btn btn-default btn-sm">
                                <i class="fa fa-refresh"></i> Reset
                            </button>
                        </div>
                        <p class="stock-summary">
                            @if ($selectedOwner)
                                <strong>{{ $selectedOwner->name }}</strong> — gunakan pencarian tabel atau filter untuk mempersempit data.
                            @else
                                <strong>Pilih outlet terlebih dahulu</strong> untuk menampilkan stock toko.
                            @endif
                        </p>
                    </div>

                    <div class="box-body table-responsive">
                        <table id="{{ $selectedOwner ? 'example1' : 'owner-stock-empty-table' }}" class="table table-bordered table-striped stock-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Outlet</th>
                                    <th>Code</th>
                                    <th>Product</th>
                                    <th>Kategori</th>
                                    <th>Supplier</th>
                                    <th>Sumber</th>
                                    <th>HPP</th>
                                    <th>Masuk</th>
                                    <th>Keluar/Terjual</th>
                                    <th>Adjustment</th>
                                    <th>Saldo</th>
                                    <th>Expired</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if (!$selectedOwner)
                                    <tr><td colspan="15" class="text-center text-muted">Pilih outlet terlebih dahulu.</td></tr>
                                @else
                                @foreach ($stocks as $stock)
                                    @php
                                        $sourceType = strtolower((string) $stock->source_type);
                                        $sourceLabel = str_contains($sourceType, 'delivery') ? 'Delivery Order'
                                            : (str_contains($sourceType, 'purchase') ? 'Belanja Langsung' : ($stock->source_type ?: 'Manual'));
                                        $stockStatus = $stock->expired_at && $stock->expired_at->lt(today())
                                            ? 'expired'
                                            : ($stock->qty > 0 ? 'available' : 'empty');
                                    @endphp
                                    <tr data-outlet="{{ $stock->owner_id }}"
                                        data-category="{{ strtolower((string) $stock->category) }}"
                                        data-location="{{ strtolower((string) $stock->lokasi) }}"
                                        data-suppliers="{{ implode(',', $stock->supplier_ids ?? []) }}"
                                        data-source="{{ strtolower((string) $stock->source_type) }}"
                                        data-status="{{ $stockStatus }}">
                                        <td></td>
                                        <td>{{ $stock->owner?->name ?? '-' }}</td>
                                        <td>{{ $stock->product?->code ?? '-' }}</td>
                                        <td>{{ $stock->product?->name ?? '-' }}</td>
                                        <td>{{ $stock->category ?: '-' }}</td>
                                        <td>{{ $stock->suppliers ?: '-' }}</td>
                                        <td>
                                            @if ($stock->source_id && str_contains($sourceType, 'delivery'))
                                                <a href="{{ route('delivery-orders.show', $stock->source_id) }}">{{ $sourceLabel }} #{{ $stock->source_id }}</a>
                                            @elseif ($stock->source_id && str_contains($sourceType, 'purchase'))
                                                <a href="{{ route('outlet-purchases.show', $stock->source_id) }}">{{ $sourceLabel }} #{{ $stock->source_id }}</a>
                                            @elseif ($sourceType === 'multiple')
                                                Multiple sources
                                            @else
                                                {{ $sourceLabel }}{{ $stock->source_id ? ' #' . $stock->source_id : '' }}
                                            @endif
                                            @if (($stock->batch_count ?? 1) > 1)
                                                <br><small class="text-muted">{{ $stock->batch_count }} batches combined</small>
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-info btn-price-history"
                                                data-toggle="modal" data-target="#priceHistoryModal"
                                                data-id="{{ $stock->product_id }}">@currency($stock->hpp)</button>
                                        </td>
                                        <td>{{ (int) ($stock->qty_in_total ?? 0) }}</td>
                                        <td>{{ (int) ($stock->qty_out_total ?? 0) }}</td>
                                        <td>{{ (int) (($stock->adjustment_in_total ?? 0) - ($stock->adjustment_out_total ?? 0)) }}</td>
                                        <td><strong>{{ $stock->qty }}</strong> {{ $stock->product?->satuan }}</td>
                                        <td>{{ optional($stock->expired_at)->format('Y-m-d') ?: '-' }}</td>
                                        <td><span class="label label-{{ $stockStatus === 'available' ? 'success' : ($stockStatus === 'expired' ? 'danger' : 'default') }}">{{ $stockStatus }}</span></td>
                                        <td>
                                            <a class="btn btn-xs btn-info" href="{{ route('owner-stocks.kartu', ['outlet_id' => $stock->owner_id, 'product_id' => $stock->product_id]) }}">
                                                <i class="fa fa-list"></i> Kartu
                                            </a>
                                            <button type="button" class="btn btn-xs btn-primary owner-stock-history"
                                                data-outlet="{{ $stock->owner_id }}" data-product="{{ $stock->product_id }}"
                                                data-toggle="modal" data-target="#ownerStockHistoryModal">
                                                <i class="fa fa-history"></i> History
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" id="priceHistoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Price History (Harga Beli)</h4>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered table-condensed">
                        <thead><tr><th>Date</th><th>User</th><th>Change</th></tr></thead>
                        <tbody id="priceHistoryBody"><tr><td colspan="3" class="text-center">Loading...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ownerStockHistoryModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Outlet Stock History</h4>
                </div>
                <div class="modal-body">
                    <h5 class="history-section-title">Activity Log</h5>
                    <div class="table-responsive">
                        <table id="ownerActivityTable" class="table table-bordered table-condensed">
                            <thead><tr><th>Date</th><th>User</th><th>Event</th><th>Changes</th></tr></thead>
                            <tbody id="owner-stock-activity"><tr><td colspan="4" class="text-center">Loading...</td></tr></tbody>
                        </table>
                    </div>
                    <h5 class="history-section-title">Stock Movements</h5>
                    <div class="table-responsive">
                        <table id="ownerMovementTable" class="table table-bordered table-condensed">
                            <thead><tr><th>Date</th><th>User</th><th>Type</th><th>In</th><th>Out</th><th>Balance</th><th>Notes</th></tr></thead>
                            <tbody id="owner-stock-movements"><tr><td colspan="7" class="text-center">Loading...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('page-script')
    <script>
        $(function () {
            const hasOwner = @json((bool) $selectedOwner);

            function escapeHtml(value) {
                return $('<div>').text(value == null ? '' : value).html();
            }

            if ($.fn.DataTable.isDataTable('#example1')) {
                $('#example1').DataTable().destroy();
            }

            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (!settings.nTable || settings.nTable.id !== 'example1') return true;

                var row = settings.aoData[dataIndex] && settings.aoData[dataIndex].nTr;
                if (!row) return true;

                var matches = function (id, attribute) {
                    var value = $(id).val();
                    if (!value) return true;
                    var rowValue = String($(row).data(attribute) || '').toLowerCase();
                    return attribute === 'suppliers'
                        ? rowValue.split(',').indexOf(String(value).toLowerCase()) !== -1
                        : rowValue === String(value).toLowerCase();
                };

                return matches('#filterOutlet', 'outlet')
                    && matches('#filterKategori', 'category')
                    && matches('#filterLokasi', 'location')
                    && matches('#filterSupplier', 'suppliers')
                    && matches('#filterSumber', 'source')
                    && matches('#filterStatus', 'status');
            });

            var table = hasOwner ? $('#example1').DataTable({
                    order: [[3, 'asc']],
                    columnDefs: [
                        { targets: [0, 14], orderable: false, searchable: false },
                        { targets: [7, 8, 9, 10, 11], className: 'text-right' }
                    ],
                    columns: [
                        { data: null, render: function (data, type, row, meta) { return meta.row + 1; } },
                        null, null, null, null, null, null, null, null, null, null, null, null, null, null
                    ]
                }) : null;

            $('#filterOutlet, #filterKategori, #filterLokasi, #filterSupplier, #filterSumber, #filterStatus').on('change', function () {
                if (this.id === 'filterOutlet') {
                    const outletId = $(this).val();
                    window.location = '{{ route('owner-stocks.index') }}' + (outletId ? '?outlet_id=' + encodeURIComponent(outletId) : '');
                    return;
                }
                if (table) table.draw();
            });

            $('#resetOwnerFilters').on('click', function () {
                window.location = '{{ route('owner-stocks.index') }}';
            });

            $('#priceHistoryModal').on('show.bs.modal', function (event) {
                var id = $(event.relatedTarget).data('id');
                var modal = $(this);
                modal.find('#priceHistoryBody').html('<tr><td colspan="3" class="text-center">Loading...</td></tr>');

                $.get('/product/' + id + '/price-history')
                    .done(function (response) {
                        var rows = (response.data || []).map(function (item) {
                            var change = item.event === 'created'
                                ? 'Created → ' + Number(item.new).toLocaleString()
                                : Number(item.old).toLocaleString() + ' → ' + Number(item.new).toLocaleString();
                            return '<tr><td>' + escapeHtml(item.date) + '</td><td>' + escapeHtml(item.user) + '</td><td>' + escapeHtml(change) + '</td></tr>';
                        }).join('');
                        modal.find('#priceHistoryBody').html(rows || '<tr><td colspan="3" class="text-center">No changes found.</td></tr>');
                    })
                    .fail(function () {
                        modal.find('#priceHistoryBody').html('<tr><td colspan="3" class="text-center text-danger">Error loading data.</td></tr>');
                    });
            });

            $('#ownerStockHistoryModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var modal = $(this);
                var query = { outlet_id: button.data('outlet'), product_id: button.data('product') };

                ['#ownerActivityTable', '#ownerMovementTable'].forEach(function (selector) {
                    if ($.fn.DataTable.isDataTable(selector)) $(selector).DataTable().destroy();
                });
                $('#owner-stock-activity').html('<tr><td colspan="4" class="text-center">Loading...</td></tr>');
                $('#owner-stock-movements').html('<tr><td colspan="7" class="text-center">Loading...</td></tr>');

                $.get('{{ route('owner-stocks.history') }}', query)
                    .done(function (response) {
                        var activities = (response.activities || []).map(function (item) {
                            var properties = item.properties || {};
                            var oldValues = properties.old || {};
                            var newValues = properties.attributes || {};
                            var changes = item.event === 'created'
                                ? 'Stock created'
                                : Object.keys(newValues).map(function (key) {
                                    return escapeHtml(key) + ': ' + escapeHtml(oldValues[key] ?? '-') + ' → ' + escapeHtml(newValues[key] ?? '-');
                                }).join('<br>');
                            return '<tr><td>' + escapeHtml(item.date || '-') + '</td><td>' + escapeHtml(item.user || 'System') + '</td><td>' + escapeHtml(item.event || '-') + '</td><td>' + (changes || '-') + '</td></tr>';
                        }).join('');
                        var movements = (response.movements || []).map(function (item) {
                            return '<tr><td>' + escapeHtml(item.date || '-') + '</td><td>' + escapeHtml(item.user || 'System') + '</td><td>' + escapeHtml(item.type || '-') + '</td><td>' + (item.qty_in || 0) + '</td><td>' + (item.qty_out || 0) + '</td><td>' + (item.balance ?? 0) + '</td><td>' + escapeHtml(item.notes || '-') + '</td></tr>';
                        }).join('');

                        $('#owner-stock-activity').html(activities || '<tr><td colspan="4" class="text-center">No activity found.</td></tr>');
                        $('#owner-stock-movements').html(movements || '<tr><td colspan="7" class="text-center">No movements found.</td></tr>');
                        $('#ownerActivityTable').DataTable({ order: [[0, 'asc']] });
                        $('#ownerMovementTable').DataTable({ order: [[0, 'asc']] });
                    })
                    .fail(function () {
                        $('#owner-stock-activity').html('<tr><td colspan="4" class="text-center text-danger">Unable to load history.</td></tr>');
                        $('#owner-stock-movements').html('<tr><td colspan="7" class="text-center text-danger">Unable to load history.</td></tr>');
                    });
            });
        });
    </script>
@endsection
