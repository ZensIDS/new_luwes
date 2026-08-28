@extends('layouts.master')

@section('title', 'Request Orders')

@section('container')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>
            OUTLET REQUESTS STOCK
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        @if (auth()->user()->role !== 'admin-gudang')
                        <a href="{{ route('request-orders.create') }}" class="btn btn-md bg-green">Tambah</a>
                        @endif
                        <div class="pull-right" style="display:flex; align-items:center; gap:8px;">
                            <label class="control-label" style="margin:0;">Filter Outlet:</label>
                            <select id="outlet-filter" class="select2" style="min-width:220px;">
                                <option value="">-- Semua Outlet --</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div><!-- /.box-header -->
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode Request</th>
                                    <th>Owner (Outlet)</th>
                                    <th>Requested By</th>
                                    <th>Tanggal Request</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
            </div><!-- /.col -->
        </div><!-- /.row -->
    </section><!-- /.content -->
@endsection

@section('page-script')
<script>
    $(function () {
        if ($.fn.DataTable.isDataTable('#example1')) {
            $('#example1').DataTable().destroy();
        }

        var table = $('#example1').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{ route('request-orders.index.data') }}',
                data: function(d) {
                    d.outlet_id = $('#outlet-filter').val();
                }
            },
            order: [[4, 'desc']], // Tanggal Request terbaru dulu, setara ->orderBy('created_at', 'desc') lama
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                { data: 'code', name: 'request_orders.code' },
                { data: 'owner', name: 'outlets.name' },
                { data: 'requested_by', orderable: false },
                { data: 'request_date', name: 'request_orders.request_date' },
                { data: 'status_html', name: 'request_orders.status' },
                { data: 'items_html', orderable: false, searchable: false },
                { data: 'aksi_html', orderable: false, searchable: false },
            ]
        });

        $('#outlet-filter').on('change', function () {
            table.draw();
        });

        $(document).on('click', '.btn-toggle-ro-items', function() {
            var id     = $(this).data('target');
            var state  = $(this).data('state');
            var $extra = $('.extra-item-ro-' + id);
            var $badge = $(this).find('.label');

            if (state === 'closed') {
                $extra.show();
                $badge.text('Tutup');
                $(this).data('state', 'open');
            } else {
                $extra.hide();
                $badge.text('Selengkapnya (' + $extra.length + ')');
                $(this).data('state', 'closed');
            }
        });
    });
</script>
@endsection
