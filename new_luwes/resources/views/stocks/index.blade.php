@extends('layouts.master')
@section('title', 'Stocks')
@section('container')
    <section class="content-header">
        <h1>Data Stocks</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <select id="filterKategori" class="form-control input-sm select2" style="width:auto; min-width:160px;">
                                <option value="">Semua Kategori</option>
                            </select>
                            <select id="filterLokasi" class="form-control input-sm select2" style="width:auto; min-width:160px;">
                                <option value="">Semua Lokasi</option>
                            </select>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <td>No</td>
                                    <td>SKU</td>
                                    <td>Code</td>
                                    <td>Product</td>
                                    <td>Konversi</td>
                                    <td>Harga Beli</td>
                                    <td>Stock Outlet</td>
                                    <td>Qty Reserved</td>
                                    <td>Qty Warehouse</td>
                                    <td>Created</td>
                                    <td>Expired</td>
                                    <td>Status</td>
                                    <td>Action</td>
                                    <td style="display:none;">Kategori</td>
                                    <td style="display:none;">Lokasi</td>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($stocks as $stock)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $stock->sku }}</td>
                                        <td>{{ $stock->serial_number ?? $stock->product->code }}</td>
                                        <td>{{ $stock->product->name }}</td>
                                        <td>{{ $stock->product->konversi_string }}</td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-info btn-price-history"
                                                data-toggle="modal" data-target="#priceHistoryModal"
                                                data-id="{{ $stock->product_id }}">
                                                @currency($stock->harga_beli ?? 0)
                                            </button>
                                        </td>
                                        <td>
                                            @php $v = $stock->ownerStock?->qty ?? 0; $k = $stock->product->konversiDisplay($v); @endphp
                                            {{ $v }} @if($k !== '-') <span class="label label-info">{{ $k }}</span>@endif
                                        </td>
                                        <td>
                                            @php $v = $stock->qty_reserved ?? 0; $k = $stock->product->konversiDisplay($v); @endphp
                                            {{ $v }} @if($k !== '-') <span class="label label-info">{{ $k }}</span>@endif
                                        </td>
                                        <td>
                                            @php $v = $stock->qty_available ?? 0; $k = $stock->product->konversiDisplay($v); @endphp
                                            {{ $v }} @if($k !== '-') <span class="label label-info">{{ $k }}</span>@endif
                                        </td>
                                        <td>{{ $stock->created_at?->format('h:i a / d-M-Y') }}</td>
                                        <td>{{ $stock->expired_at?->format('d-M-Y') }}</td>
                                        <td>
                                            <span
                                                class="label label-{{ $stock->status == 'available' ? 'success' : 'warning' }}">
                                                {{ $stock->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-xs btn-primary btn-stock-history"
                                                data-toggle="modal" data-target="#stockHistoryModal"
                                                data-id="{{ $stock->id }}">
                                                <i class="fa fa-history"></i> History
                                            </button>
                                        </td>
                                        <td style="display:none;">{{ $stock->product->category?->name ?? '' }}</td>
                                        <td style="display:none;">{{ $stock->product->lokasi ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <!-- Price History Modal -->
                        <div class="modal fade" id="priceHistoryModal" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        <h4 class="modal-title">Price History (Harga Beli)</h4>
                                    </div>
                                    <div class="modal-body">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>User</th>
                                                    <th>Change</th>
                                                </tr>
                                            </thead>
                                            <tbody id="priceHistoryBody">
                                                <tr>
                                                    <td colspan="3" class="text-center">Loading...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stock History Modal -->
                        <div class="modal fade" id="stockHistoryModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        <h4 class="modal-title">Stock History</h4>
                                    </div>
                                    <div class="modal-body">
                                        <h5><b>Activity Log</b></h5>
                                        <div class="table-responsive text-nowrap">
                                            <table id="example2" class="table table-bordered table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>User</th>
                                                        <th>Event</th>
                                                        <th>Changes</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="activityBody">
                                                    <tr>
                                                        <td colspan="4" class="text-center">Loading...</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <h5><b>Stock Movements</b></h5>
                                        <div class="table-responsive text-nowrap">
                                            <table id="example3" class="table table-bordered table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>User</th>
                                                        <th>Type</th>
                                                        <th>In</th>
                                                        <th>Out</th>
                                                        <th>Balance</th>
                                                        <th>Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="movementBody">
                                                    <tr>
                                                        <td colspan="7" class="text-center">Loading...</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div> {{-- box-body --}}
                </div>
            </div>
        </div>
    </section>
@endsection
@section('page-script')
    <script>
        $(document).ready(function() {
            // Destroy pre-initialised DataTables (from master) to avoid column mismatch
            if ($.fn.DataTable.isDataTable('#example1')) {
                $('#example1').DataTable().destroy();
            }
            if ($.fn.DataTable.isDataTable('#example2')) {
                $('#example2').DataTable().destroy();
            }
            if ($.fn.DataTable.isDataTable('#example3')) {
                $('#example3').DataTable().destroy();
            }

            var table = $('#example1').DataTable({
                columnDefs: [{ visible: false, targets: [13, 14] }]
            });

            // Populate Kategori dropdown (column 13)
            table.column(13).data().unique().sort().each(function(val) {
                if (val && String(val).trim() !== '') {
                    $('#filterKategori').append($('<option>', { value: val, text: val }));
                }
            });

            // Populate Lokasi dropdown (column 14)
            table.column(14).data().unique().sort().each(function(val) {
                if (val && String(val).trim() !== '') {
                    $('#filterLokasi').append($('<option>', { value: val, text: val }));
                }
            });

            function escReg(val) {
                return val.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, '\\$&');
            }

            $('#filterKategori').on('change', function() {
                var val = $(this).val();
                table.column(13).search(val ? '^' + escReg(val) + '$' : '', true, false).draw();
            });

            $('#filterLokasi').on('change', function() {
                var val = $(this).val();
                table.column(14).search(val ? '^' + escReg(val) + '$' : '', true, false).draw();
            });

            $('#priceHistoryModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var modal = $(this);
                modal.find('#priceHistoryBody').html(
                    '<tr><td colspan="3" class="text-center">Loading...</td></tr>');

                $.ajax({
                    url: '/product/' + id + '/price-history',
                    method: 'GET',
                    success: function(res) {
                        var rows = '';
                        if (res.data && res.data.length) {
                            res.data.forEach(function(item) {
                                var change = item.event === 'created' ?
                                    'Created → ' + Number(item.new).toLocaleString() :
                                    Number(item.old).toLocaleString() + ' → ' + Number(
                                        item.new).toLocaleString();
                                rows += '<tr><td>' + item.date + '</td><td>' + item
                                    .user + '</td><td>' + change + '</td></tr>';
                            });
                        } else {
                            rows =
                                '<tr><td colspan="3" class="text-center">No changes found.</td></tr>';
                        }
                        modal.find('#priceHistoryBody').html(rows);
                    },
                    error: function() {
                        modal.find('#priceHistoryBody').html(
                            '<tr><td colspan="3" class="text-center text-danger">Error loading data.</td></tr>'
                        );
                    }
                });
            });

            $('#stockHistoryModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');

                $('#activityBody').html('<tr><td colspan="4" class="text-center">Loading...</td></tr>');
                $('#movementBody').html('<tr><td colspan="7" class="text-center">Loading...</td></tr>');

                if ($.fn.DataTable.isDataTable('#example2')) {
                    $('#example2').DataTable().destroy();
                }
                if ($.fn.DataTable.isDataTable('#example3')) {
                    $('#example3').DataTable().destroy();
                }

                $.get('/stock/' + id + '/history', function (res) {
                    var aRows = '';
                    if (res.activities.length) {
                        res.activities.forEach(function (item) {
                            var changes = '';
                            if (item.event === 'created') {
                                changes = 'Stock created';
                            } else {
                                var old = item.properties.old || {};
                                var attr = item.properties.attributes || {};
                                changes = Object.keys(attr).map(function (k) {
                                    return k + ': ' + (old[k] ?? '?') + ' → ' + attr[k];
                                }).join('<br>');
                            }
                            aRows += '<tr><td>' + item.date + '</td><td>' + item.user +
                                '</td><td>' + item.event + '</td><td>' + changes + '</td></tr>';
                        });
                    } else {
                        aRows = '<tr><td colspan="4" class="text-center">No activity found.</td></tr>';
                    }
                    $('#activityBody').html(aRows);

                    var mRows = '';
                    if (res.movements.length) {
                        res.movements.forEach(function (item) {
                            mRows += '<tr><td>' + item.date + '</td><td>' + item.user +
                                '</td><td>' + item.type + '</td><td>' + (item.qty_in ?? 0) +
                                '</td><td>' + (item.qty_out ?? 0) + '</td><td>' + (item.balance ?? 0) +
                                '</td><td>' + (item.notes ?? '-') + '</td></tr>';
                        });
                    } else {
                        mRows = '<tr><td colspan="7" class="text-center">No movements found.</td></tr>';
                    }
                    $('#movementBody').html(mRows);

                    $('#example2').DataTable();
                    $('#example3').DataTable();

                }).fail(function () {
                    $('#activityBody').html('<tr><td colspan="4" class="text-center text-danger">Error loading data.</td></tr>');
                    $('#movementBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading data.</td></tr>');
                });
            });
        });
    </script>
@endsection
