@extends('layouts.master')

@section('title', 'Penerimaan Retur - ' . $refundPembelian->code)

@section('container')
    <section class="content-header">
        <h1>Penerimaan Barang Retur <small>{{ $refundPembelian->code }}</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-12">

                {{-- Info Box --}}
                <div class="box box-default">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-4">
                                <table class="table table-condensed table-borderless">
                                    <tr>
                                        <td class="text-muted" width="40%">No. Retur</td>
                                        <td><strong>{{ $refundPembelian->code }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Supplier</td>
                                        <td>{{ $refundPembelian->supplier->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Tanggal Retur</td>
                                        <td>{{ $refundPembelian->tanggal->format('d M Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Total</td>
                                        <td>@currency($refundPembelian->total)</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Terima Form --}}
                <form action="{{ route('refundPembelian.terima', $refundPembelian->id) }}" method="POST">
                    @csrf
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-inbox"></i> Proses Penerimaan Retur</h3>
                        </div>
                        <div class="box-body">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i>
                                Pilih resolusi untuk setiap item: <strong>Retur Barang</strong> (stok gudang kembali)
                            </div>

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
                                        <th width="200">Resolusi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($refundPembelian->refundPembelianItems as $i => $item)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $item->product->name }}</td>
                                            <td><span class="label label-default">{{ $item->sku ?? '-' }}</span></td>
                                            <td>{{ $item->qty }}</td>
                                            <td>@currency($item->harga)</td>
                                            <td class="item-subtotal" data-subtotal="{{ $item->qty * $item->harga }}">
                                                @currency($item->qty * $item->harga)
                                            </td>
                                            <td>{{ $item->alasan }}</td>
                                            <td>
                                                <div class="btn-group resolution-group" data-item="{{ $item->id }}"
                                                    data-subtotal="{{ $item->qty * $item->harga }}">
                                                    <label class="btn btn-sm btn-default resolution-btn" data-val="barang">
                                                        <input type="radio" name="items[{{ $item->id }}][resolution]"
                                                            value="barang" required>
                                                        <i class="fa fa-cube"></i> Retur Barang
                                                    </label>
                                                    <label class="btn btn-sm btn-default resolution-btn" data-val="uang">
                                                        <input type="radio" name="items[{{ $item->id }}][resolution]"
                                                            value="uang">
                                                        <i class="fa fa-money"></i> Ganti Uang
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            {{-- Kas section (shown if any item = uang) --}}
                            {{-- <div id="kas-section" style="display:none"> --}}
                                {{-- <hr> --}}
                                {{-- <div class="row"> --}}
                                    {{-- <div class="col-md-6"> --}}
                                        {{-- <div class="form-group"> --}}
                                            {{-- <label>Total Ganti Uang</label> --}}
                                            {{-- <input type="text" class="form-control numeral-mask" id="uang-total" readonly --}}
                                                {{-- value="0"> --}}
                                        {{-- </div> --}}
                                    {{-- </div> --}}
                                    {{-- <div class="col-md-6"> --}}
                                        {{-- <div class="form-group"> --}}
                                            {{-- <label>Masuk ke Kas <span class="text-danger">*</span></label> --}}
                                            {{-- <select name="kas_id" id="kas_id" class="form-control select2" --}}
                                                {{-- data-placeholder="Pilih Kas" style="width:100%"> --}}
                                                {{-- <option value="" disabled selected>Pilih Kas</option> --}}
                                                {{-- @foreach ($kasList as $kas) --}}
                                                    {{-- <option value="{{ $kas->id }}">{{ $kas->name }}</option> --}}
                                                {{-- @endforeach --}}
                                            {{-- </select> --}}
                                        {{-- </div> --}}
                                    {{-- </div> --}}
                                {{-- </div> --}}
                            {{-- </div> --}}

                        </div>
                        <div class="box-footer">
                            <a href="{{ route('refundPembelian.show', $refundPembelian->id) }}" class="btn btn-default">
                                Kembali
                            </a>
                            <button type="submit" class="btn btn-success" id="btn-terima" disabled>
                                <i class="fa fa-check-circle"></i> Selesaikan Penerimaan
                            </button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </section>
@endsection

@section('page-script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        $('.numeral-mask').mask("#,##0", {
            reverse: true
        });

        var totalItems = {{ $refundPembelian->refundPembelianItems->count() }};

        // Track resolution per item
        var resolutions = {};

        $(document).on('change', 'input[type=radio][name^="items"]', function() {
            var $group = $(this).closest('.resolution-group');
            var itemId = $group.data('item');
            var val = $(this).val();

            resolutions[itemId] = {
                resolution: val,
                subtotal: parseFloat($group.data('subtotal')) || 0,
            };

            // Highlight selected
            $group.find('.resolution-btn').removeClass('btn-info btn-warning').addClass('btn-default');
            $(this).closest('.resolution-btn')
                .removeClass('btn-default')
                .addClass(val === 'barang' ? 'btn-info' : 'btn-warning');

            updateUangTotal();
            checkAllSelected();
        });

        function updateUangTotal() {
            var total = 0;
            var hasUang = false;

            $.each(resolutions, function(id, data) {
                if (data.resolution === 'uang') {
                    total += data.subtotal;
                    hasUang = true;
                }
            });

            if (hasUang) {
                // $('#kas-section').show();
                $('#uang-total').val(total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','));
                // $('#kas_id').attr('required', true);
            } else {
                $('#kas-section').hide();
                // $('#kas_id').removeAttr('required');
            }
        }

        function checkAllSelected() {
            var selected = Object.keys(resolutions).length;
            $('#btn-terima').prop('disabled', selected < totalItems);
        }
    </script>
@endsection
