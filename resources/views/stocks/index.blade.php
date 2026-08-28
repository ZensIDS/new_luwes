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
                                @foreach($kategoriOptions as $kat)
                                    <option value="{{ $kat }}">{{ $kat }}</option>
                                @endforeach
                            </select>
                            <select id="filterLokasi" class="form-control input-sm select2" style="width:auto; min-width:160px;">
                                <option value="">Semua Lokasi</option>
                                @foreach($lokasiOptions as $lok)
                                    <option value="{{ $lok }}">{{ $lok }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Code</th>
                                    <th>Product</th>
                                    <th>Konversi</th>
                                    <th>Harga Beli</th>
                                    <th>Stock Outlet</th>
                                    <th>Qty Reserved</th>
                                    <th>Qty Warehouse</th>
                                    <th>Created</th>
                                    <th>Expired</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
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
        function konversiDisplayFmt(qty, konversiQty, satuanBesar, satuan) {
            satuan = satuan || 'PCS';
            qty = parseInt(qty) || 0;
            if (!konversiQty || !satuanBesar) return null;
            var boxes = Math.floor(qty / konversiQty);
            var rem = qty % konversiQty;
            if (rem === 0) return boxes + ' ' + satuanBesar;
            if (boxes > 0) return boxes + ' ' + satuanBesar + ' ' + rem + ' ' + satuan;
            return qty + ' ' + satuan;
        }

        function qtyWithLabel(qty, row) {
            var k = konversiDisplayFmt(qty, row.konversi_qty, row.satuan_besar, row.satuan);
            return qty + (k ? ' <span class="label label-info">' + k + '</span>' : '');
        }

        function formatRupiah(amount) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(amount || 0);
        }

        $(document).ready(function() {
            if ($.fn.DataTable.isDataTable('#example1')) {
                $('#example1').DataTable().destroy();
            }

            var table = $('#example1').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('stocks.index.data') }}',
                    data: function(d) {
                        d.kategori = $('#filterKategori').val();
                        d.lokasi = $('#filterLokasi').val();
                    }
                },
                order: [[2, 'asc']], // Product name
                columns: [
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    { data: 'code', name: 'products.code' },
                    { data: 'product_name', name: 'products.name' },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            var k = konversiDisplayFmt(row.qty_warehouse, row.konversi_qty, row.satuan_besar, row.satuan);
                            return k || '-';
                        }
                    },
                    {
                        data: 'harga_beli',
                        name: 's.harga_beli',
                        render: function(data, type, row) {
                            return '<button type="button" class="btn btn-xs btn-info btn-price-history" ' +
                                'data-toggle="modal" data-target="#priceHistoryModal" data-id="' + row.product_id + '">' +
                                formatRupiah(data) + '</button>';
                        }
                    },
                    {
                        data: 'stock_outlet',
                        orderable: false,
                        render: function(data, type, row) { return qtyWithLabel(data, row); }
                    },
                    {
                        data: 'qty_reserved',
                        name: 's.qty_reserved',
                        render: function(data, type, row) { return qtyWithLabel(data, row); }
                    },
                    {
                        data: 'qty_warehouse',
                        name: 'g.total_qty',
                        render: function(data, type, row) { return qtyWithLabel(data, row); }
                    },
                    { data: 'created_at', name: 's.created_at' },
                    { data: 'expired_at', name: 's.expired_at' },
                    {
                        data: 'status',
                        name: 's.status',
                        render: function(data) {
                            var cls = data === 'available' ? 'success' : 'warning';
                            return '<span class="label label-' + cls + '">' + data + '</span>';
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return '<button type="button" class="btn btn-xs btn-primary btn-stock-history" ' +
                                'data-toggle="modal" data-target="#stockHistoryModal" data-id="' + row.stock_id + '">' +
                                '<i class="fa fa-history"></i> History</button>';
                        }
                    },
                ]
            });

            $('#filterKategori, #filterLokasi').on('change', function() {
                table.draw();
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
