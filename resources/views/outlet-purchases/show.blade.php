@extends('layouts.master')

@section('title', $purchase->isPending() ? 'Penerimaan Barang Pembelian Langsung' : 'Detail Pembelian Langsung')

@section('container')
    <section class="content-header">
        <h1>
            {{ $purchase->isPending() ? 'Penerimaan Barang' : 'Detail Pembelian Langsung' }}
            <small>{{ $purchase->code }}</small>
        </h1>
    </section>
    <section class="content">
        <div class="box box-primary">
            <div class="box-body">
                <dl class="dl-horizontal">
                    <dt>Kode</dt>
                    <dd>{{ $purchase->code }}</dd>
                    <dt>Outlet</dt>
                    <dd>{{ $purchase->outlet?->name }}</dd>
                    <dt>Supplier</dt>
                    <dd>{{ $purchase->supplier?->name }}</dd>
                    <dt>Tanggal</dt>
                    <dd>{{ optional($purchase->purchase_date)->format('Y-m-d') }}</dd>
                    <dt>No. Nota</dt>
                    <dd>{{ $purchase->invoice_number ?: '-' }}</dd>
                    <dt>Status</dt>
                    <dd>
                        @if ($purchase->isPending())
                            <span class="label label-warning">Menunggu Penerimaan</span>
                        @else
                            <span class="label label-success">Completed</span>
                        @endif
                    </dd>
                    @if ($purchase->received_at)
                        <dt>Diterima</dt>
                        <dd>{{ $purchase->received_at->format('d-m-Y H:i') }} oleh {{ $purchase->receiver?->name ?? '-' }}</dd>
                    @endif
                    @if ($purchase->notes)
                        <dt>Catatan</dt>
                        <dd>{{ $purchase->notes }}</dd>
                    @endif
                </dl>

                @if ($purchase->isPending())
                    <div class="alert alert-warning">
                        Periksa barang yang datang, sesuaikan <strong>Qty Diterima</strong>, dan isi <strong>Expired</strong> bila barang punya tanggal kadaluarsa. Stock toko baru bertambah setelah penerimaan diselesaikan
                        dan transaksi tidak bisa diedit lagi.
                    </div>
                @endif

                <form id="receive-form" method="POST" action="{{ route('outlet-purchases.receive', $purchase) }}">
                    @csrf
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-center">Qty Dipesan</th>
                                    <th class="text-center" style="width:130px">Qty Diterima</th>
                                    <th>HPP</th>
                                    <th>Batch</th>
                                    <th{!! $purchase->isPending() ? ' style="width:160px"' : '' !!}>Expired{{ $purchase->isPending() ? ' (opsional)' : '' }}</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($purchase->items as $item)
                                    <tr>
                                        <td>{{ $item->product?->code }} — {{ $item->product?->name }}</td>
                                        <td class="text-center">
                                            {{ $item->qty }}
                                            @php $konversi = $item->product?->konversiDisplay($item->qty) ?? '-'; @endphp
                                            @if ($konversi !== '-')
                                                <span class="label label-info">{{ $konversi }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($purchase->isPending())
                                                <input type="number" name="items[{{ $item->id }}][qty_received]"
                                                    class="form-control input-sm text-center"
                                                    value="{{ old('items.' . $item->id . '.qty_received', $item->qty) }}"
                                                    min="0" max="{{ $item->qty }}" required>
                                            @else
                                                <strong>{{ $item->qty_received ?? $item->qty }}</strong>
                                                @if ($item->qty_received !== null && $item->qty_received < $item->qty)
                                                    <span class="label label-warning">-{{ $item->qty - $item->qty_received }}</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td>@currency($item->harga_beli)</td>
                                        <td>{{ $item->batch_number }}</td>
                                        <td>
                                            @if ($purchase->isPending())
                                                <input type="date" name="items[{{ $item->id }}][expired_at]"
                                                    class="form-control input-sm"
                                                    value="{{ old('items.' . $item->id . '.expired_at', $item->expired_at?->format('Y-m-d')) }}">
                                            @else
                                                {{ $item->expired_at ? $item->expired_at->format('d-m-Y') : '-' }}
                                            @endif
                                        </td>
                                        <td>@currency($item->subtotal)</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="6" class="text-right">
                                        Subtotal{{ $purchase->isPending() ? ' (berdasarkan qty dipesan)' : '' }}
                                    </th>
                                    <th>@currency($purchase->subtotal)</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </form>
            </div>
            <div class="box-footer">
                <a class="btn btn-default" href="{{ route('outlet-purchases.index') }}">Kembali</a>
                <a class="btn btn-success" href="{{ route('outlet-purchases.print', $purchase) }}"><i class="fa fa-print"></i> Print</a>
                @if ($purchase->isPending())
                    <a class="btn btn-warning" href="{{ route('outlet-purchases.edit', $purchase) }}"><i class="fa fa-pencil"></i> Edit</a>
                    <button type="button" class="btn btn-success pull-right" id="btn-confirm-receive">
                        <i class="fa fa-check"></i> Penerimaan Selesai
                    </button>
                @endif
            </div>
        </div>
    </section>
@endsection

@section('page-script')
    @if ($purchase->isPending())
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            $(function () {
                $('#btn-confirm-receive').on('click', function () {
                    var form = document.getElementById('receive-form');
                    if (!form.reportValidity()) { return; }

                    Swal.fire({
                        icon: 'question',
                        title: 'Konfirmasi Penerimaan',
                        text: 'Selesaikan penerimaan {{ $purchase->code }}? Stock toko akan bertambah sesuai qty diterima dan transaksi tidak bisa diedit lagi.',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Selesai',
                        cancelButtonText: 'Batal',
                        confirmButtonColor: '#5cb85c',
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            $('#btn-confirm-receive').prop('disabled', true);
                            form.submit();
                        }
                    });
                });
            });
        </script>
    @endif
@endsection