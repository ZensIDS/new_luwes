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
                                    <td>No</td>
                                    <td>Kode DO</td>
                                    <td>Request Order</td>
                                    <td>Owner/Outlet</td>
                                    <td>Delivery Date</td>
                                    <td>Status</td>
                                    <td>Aksi</td>
                                </tr>
                            </thead>
                            @foreach ($deliveryOrders as $value)
                                <tr data-outlet="{{ $value->owner_id ?? '' }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $value->code }}</td>
                                    <td>{{ $value->requestOrder->code }}</td>
                                    <td>{{ $value->owner->name }}</td>
                                    <td>{{ $value->delivery_date->format('d-m-Y') }}</td>
                                    <td>
                                        @if ($value->status == 'draft')
                                            <span class="label label-default">Draft</span>
                                        @elseif ($value->status == 'sent')
                                            <span class="label label-info">Sent</span>
                                        @elseif ($value->status == 'delivered')
                                            <span class="label label-success">Delivered</span>
                                        @elseif ($value->status == 'completed')
                                            <span class="label label-primary">Completed</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a class="btn-xs btn btn-default" href="{{ route('delivery-orders.show', $value->id) }}"><i class="fa fa-eye"></i> Detail</a>
                                        @if (auth()->user()->role !== 'admin-gudang' && ($value->status == 'draft' || $value->status == 'sent'))
                                            <button class="btn-xs btn btn-success" data-toggle="modal"
                                                data-target="#sendModal{{ $value->id }}">Delivery Completed</button>

                                            @include('delivery-orders._send-modal', ['do' => $value])
                                        @endif
                                        <a class=" btn-xs btn btn-success" href="{{ route('laporan.delivery-order', $value->id) }}"><i class="fa fa-file-excel-o"></i> Export</a>
                                    </td>
                                </tr>
                            @endforeach
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
        var selectedOutlet = '';

        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
            if (!selectedOutlet) return true;
            var row = $('#example1').DataTable().row(dataIndex).node();
            return String($(row).data('outlet')) === selectedOutlet;
        });

        $('#outlet-filter').on('change', function () {
            selectedOutlet = $(this).val();
            $('#example1').DataTable().draw();
        });
    });
</script>
@include('delivery-orders._send-modal-script')
@endsection
