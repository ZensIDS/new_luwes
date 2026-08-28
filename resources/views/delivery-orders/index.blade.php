@extends('layouts.master')
@section('title', 'Delivery Orders')
@section('container')
    <section class="content-header">
        <h1>{{ auth()->user()->role === 'staff-outlet' ? 'PENERIMAAN BARANG DO' : 'DELIVERY ORDERS (OUTBOUND)' }}</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    @if (auth()->user()->role !== 'staff-outlet')
                    <div class="box-header">
                        <div class="pull-right" style="display:flex; align-items:center; gap:8px;">
                            <label class="control-label" style="margin:0;">Filter Outlet:</label>
                            <select id="outlet-filter" class="select2" style="min-width:220px;">
                                <option value="">-- Semua Outlet --</option>
                                @foreach ($outlets as $outlet)
                                    <option value="{{ $outlet->id }}">{{ $outlet->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @endif
                    <div class="box-body table-responsive">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode DO</th>
                                    <th>Request Order</th>
                                    <th>Owner/Outlet</th>
                                    <th>Delivery Date</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
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
                url: '{{ route('delivery-orders.index.data') }}',
                data: function(d) {
                    d.outlet_id = $('#outlet-filter').val();
                }
            },
            order: [[4, 'desc']], // Delivery Date terbaru dulu, setara ->orderBy('created_at', 'desc') lama
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                { data: 'code', name: 'delivery_orders.code' },
                { data: 'request_order', orderable: false },
                { data: 'owner', name: 'outlets.name' },
                { data: 'delivery_date', name: 'delivery_orders.delivery_date' },
                { data: 'status_html', name: 'delivery_orders.status' },
                { data: 'aksi_html', orderable: false, searchable: false },
            ]
        });

        $('#outlet-filter').on('change', function () {
            table.draw();
        });
    });
</script>
@include('delivery-orders._send-modal-script')
@endsection