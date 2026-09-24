@extends('layouts.master')
@section('title', 'Delivery Order Detail')
@section('container')
    <section class="content-header">
        <h1>Delivery Order: {{ $deliveryOrder->code }}</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Informasi Pengiriman</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="200">Kode DO</th>
                                <td>{{ $deliveryOrder->code }}</td>
                            </tr>
                            <tr>
                                <th>Request Order</th>
                                <td>{{ $deliveryOrder->requestOrder->code }}</td>
                            </tr>
                            <tr>
                                <th>Owner/Outlet</th>
                                <td>{{ $deliveryOrder->owner->name }}</td>
                            </tr>
                            <tr>
                                <th>Prepared By</th>
                                <td>{{ $deliveryOrder->preparedBy->name }}</td>
                            </tr>
                            <tr>
                                <th>Delivery Date</th>
                                <td>{{ $deliveryOrder->delivery_date->format('d-m-Y') }}</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    @if ($deliveryOrder->status == 'draft')
                                        <span class="label label-default">Draft</span>
                                    @elseif ($deliveryOrder->status == 'sent')
                                        <span class="label label-info">Sent</span>
                                    @elseif ($deliveryOrder->status == 'delivered')
                                        <span class="label label-success">Delivered</span>
                                    @endif
                                </td>
                            </tr>
                            @if ($deliveryOrder->received_date)
                                <tr>
                                    <th>Received Date</th>
                                    <td>{{ $deliveryOrder->received_date->format('d-m-Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <th>Received By</th>
                                    <td>{{ $deliveryOrder->receivedBy->name ?? '-' }}</td>
                                </tr>
                            @endif
                            @if ($deliveryOrder->photo_path)
                                <tr>
                                    <th>Photo Proof</th>
                                    <td>
                                        <a href="{{ asset('storage/' . $deliveryOrder->photo_path) }}" target="_blank">
                                            <img src="{{ asset('storage/' . $deliveryOrder->photo_path) }}"
                                                style="max-width: 200px;">
                                        </a>
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Items</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Expired</th>
                                    <th class="text-center">Qty Pick</th>
                                    <th class="text-center">Qty Kirim</th>
                                    <th>Konversi</th>
                                    <th>Harga Beli</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $total = 0; @endphp
                                @foreach ($deliveryOrder->items as $item)
                                    @php
                                        $qtyBilling = $item->qty_sent ?? $item->qty;
                                        $subtotal   = $qtyBilling * $item->harga_beli;
                                        $total      += $subtotal;
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item->product->name }}</td>
                                        <td>{{ $item->sku ?? '-' }}</td>
                                        <td>{{ $item->expired_at ? $item->expired_at->format('d-m-Y') : '-' }}</td>
                                        <td class="text-center">{{ $item->qty }}</td>
                                        <td class="text-center">
                                            @if($item->qty_sent !== null)
                                                <strong>{{ $item->qty_sent }}</strong>
                                                @if($item->qty_sent < $item->qty)
                                                    <span class="label label-warning">-{{ $item->qty - $item->qty_sent }}</span>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $item->product?->konversiDisplay($item->qty_sent ?? $item->qty) ?? '-' }}</td>
                                        <td>Rp {{ number_format($item->harga_beli, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="8" class="text-right">TOTAL</th>
                                    <th>Rp {{ number_format($total, 0, ',', '.') }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="box-footer">
                        <a href="{{ route('delivery-orders.index') }}" class="btn btn-default">Back</a>
                        @if (auth()->user()->role !== 'admin-gudang' && ($deliveryOrder->status == 'draft' || $deliveryOrder->status == 'sent'))
                            <button class="btn btn-success" data-toggle="modal"
                                data-target="#sendModal{{ $deliveryOrder->id }}">
                                <i class="fa fa-truck"></i> Delivery Completed
                            </button>

                            @include('delivery-orders._send-modal', ['do' => $deliveryOrder])
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
@section('page-script')
    @include('delivery-orders._send-modal-script')
@endsection
