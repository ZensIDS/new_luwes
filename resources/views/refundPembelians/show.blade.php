@extends('layouts.master')

@section('title', 'Detail Retur - ' . $refundPembelian->code)

@section('container')
    <section class="content-header">
        <h1>
            Detail Retur Pembelian
            @if ($refundPembelian->status === 'retur')
                <span class="label label-danger">Retur</span>
            @else
                <span class="label label-success">Complete</span>
            @endif
        </h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <a href="{{ route('refundPembelian.index') }}" class="btn btn-default">
                            <i class="fa fa-arrow-left"></i> Kembali
                        </a>

                        @if ($refundPembelian->type === 'gudang_ke_supplier' && $refundPembelian->status === 'retur')
                            <a href="{{ route('refundPembelian.terima.form', $refundPembelian->id) }}"
                                class="btn btn-success">
                                <i class="fa fa-inbox"></i> Proses Penerimaan Retur
                            </a>
                        @endif

                        @if ($refundPembelian->type === 'gudang_ke_supplier')
                            <a href="{{ route('laporan.retur-pembelian.single', $refundPembelian->id) }}"
                                class="btn btn-success btn-sm" target="_blank">
                                <i class="fa fa-file-excel-o"></i> Export XLSX
                            </a>
                            <a href="{{ route('laporan.pdf.retur-pembelian-single', $refundPembelian->id) }}"
                                class="btn btn-danger btn-sm" target="_blank">
                                <i class="fa fa-file-pdf-o"></i> Export PDF
                            </a>
                        @else
                            <a href="{{ route('laporan.retur-outlet.single', $refundPembelian->id) }}"
                                class="btn btn-success btn-sm" target="_blank">
                                <i class="fa fa-file-excel-o"></i> Export XLSX
                            </a>
                            <a href="{{ route('laporan.pdf.retur-outlet-single', $refundPembelian->id) }}"
                                class="btn btn-danger btn-sm" target="_blank">
                                <i class="fa fa-file-pdf-o"></i> Export PDF
                            </a>
                        @endif
                    </div>

                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-condensed">
                                    <tr>
                                        <td class="text-muted" width="40%">Kode Retur</td>
                                        <td><strong>{{ $refundPembelian->code }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Jenis Retur</td>
                                        <td>
                                            @if ($refundPembelian->type === 'gudang_ke_supplier')
                                                <span class="label label-warning">Gudang → Supplier</span>
                                            @else
                                                <span class="label label-info">Outlet → Gudang</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Tanggal</td>
                                        <td>{{ $refundPembelian->tanggal->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Operator</td>
                                        <td>{{ $refundPembelian->user->name ?? '-' }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-condensed">
                                    @if ($refundPembelian->type === 'gudang_ke_supplier')
                                        <tr>
                                            <td class="text-muted" width="40%">Supplier</td>
                                            <td>{{ $refundPembelian->supplier->name ?? '-' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Total Retur</td>
                                            <td><strong>@currency($refundPembelian->total)</strong></td>
                                        </tr>
                                        @if ($refundPembelian->kas)
                                            <tr>
                                                <td class="text-muted">Kas Diterima</td>
                                                <td>{{ $refundPembelian->kas->name }}</td>
                                            </tr>
                                        @endif
                                    @else
                                        <tr>
                                            <td class="text-muted" width="40%">Outlet</td>
                                            <td>{{ $refundPembelian->outlet->name ?? '-' }}</td>
                                        </tr>
                                        @if ($refundPembelian->pembelian)
                                            <tr>
                                                <td class="text-muted">No. PO</td>
                                                <td>{{ $refundPembelian->pembelian->code }}</td>
                                            </tr>
                                        @endif
                                    @endif
                                </table>
                            </div>
                        </div>

                        <hr>
                        <h4>Daftar Item Retur</h4>

                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Produk</th>
                                    <th>SKU</th>
                                    <th>Qty</th>
                                    <th>Harga Satuan</th>
                                    <th>Subtotal</th>
                                    <th>Alasan</th>
                                    @if ($refundPembelian->type === 'gudang_ke_supplier')
                                        <th>Resolusi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($refundPembelian->refundPembelianItems as $item)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $item->product->name }}</td>
                                        <td><span class="label label-default">{{ $item->sku ?? '-' }}</span></td>
                                        <td>{{ $item->qty }}</td>
                                        <td>@currency($item->harga)</td>
                                        <td>@currency($item->qty * $item->harga)</td>
                                        <td>{{ $item->alasan }}</td>
                                        @if ($refundPembelian->type === 'gudang_ke_supplier')
                                            <td>
                                                @if ($item->resolution === 'barang')
                                                    <span class="label label-info">
                                                        <i class="fa fa-cube"></i> Retur Barang
                                                    </span>
                                                @elseif ($item->resolution === 'uang')
                                                    <span class="label label-warning">
                                                        <i class="fa fa-money"></i> Ganti Uang
                                                    </span>
                                                @else
                                                    <span class="label label-default">Menunggu</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="{{ $refundPembelian->type === 'gudang_ke_supplier' ? 5 : 4 }}"
                                        class="text-right"><strong>Total</strong></td>
                                    <td colspan="1"><strong>@currency($refundPembelian->total)</strong></td>
                                    <td colspan="{{ $refundPembelian->type === 'gudang_ke_supplier' ? 2 : 1 }}"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
