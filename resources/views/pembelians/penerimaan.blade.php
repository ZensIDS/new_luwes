@extends('layouts.master')
@section('title', 'Penerimaan Barang')
@section('container')
    @php
        $isLocked = $pembelian->receipt_status === 'completed' && $pembelian->stocks->count() > 0;
    @endphp

    <section class="content-header">
        <h1>Penerimaan Barang <small>{{ $pembelian->code }}</small></h1>
        <ol class="breadcrumb">
            <li><a href="{{ route('penerimaan.index') }}">Penerimaan Barang</a></li>
            <li class="active">{{ $pembelian->code }}</li>
        </ol>
    </section>

    <section class="content">
        <form
            action="{{ $isLocked ? route('pembelian.update-penerimaan', $pembelian) : route('pembelian.store-penerimaan', $pembelian) }}"
            method="POST"
            enctype="multipart/form-data">
            @csrf

            <div class="row">
                <div class="col-md-12">
                    <div class="box {{ $isLocked ? 'box-success' : 'box-warning' }}">
                        <div class="box-header with-border">
                            <h3 class="box-title">
                                <i class="fa fa-cubes"></i> Input Barang Diterima
                            </h3>
                            <div class="box-tools pull-right">
                                @if($isLocked)
                                    <span class="label label-success">
                                        <i class="fa fa-lock"></i> Tersimpan
                                    </span>
                                @else
                                    <span class="label label-warning">
                                        <i class="fa fa-pencil"></i> Belum disimpan
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="box-body table-responsive text-nowrap" style="padding:0">
                            {{-- Barcode Scanner Highlight --}}
                            <div style="padding:10px 15px; border-bottom:1px solid #ddd; background:#fafafa;">
                                <div class="input-group" style="max-width:400px;">
                                    <span class="input-group-addon" style="background:#00c0ef;color:#fff;">
                                        <i class="fa fa-barcode"></i>
                                    </span>
                                    <input type="text"
                                        id="scan-highlight-input"
                                        class="form-control"
                                        placeholder="Scan barcode untuk highlight produk..."
                                        autocomplete="off">
                                    <span class="input-group-btn">
                                        <button type="button" id="btn-clear-highlight" class="btn btn-default" title="Clear highlight">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <i class="fa fa-info-circle"></i> Scan barcode untuk langsung menemukan & highlight produk di tabel.
                                </small>
                            </div>
                            <table class="table table-bordered table-striped" style="margin:0">
                                <thead>
                                    <tr>
                                        <th class="text-center">No</th>
                                        <th>Product</th>
                                        <th>Satuan</th>
                                        <th>Qty PO</th>
                                        <th>SKU</th>
                                        <th>Expired</th>
                                        <th>Qty Terima</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pembelian->pembelianProducts as $item)
                                        @php
                                            $existingStocks = $pembelian->stocks()
                                                ->where('product_id', $item->product_id)
                                                ->get();
                                            if ($existingStocks->isEmpty()) {
                                                $existingStocks = collect([null]);
                                            }
                                        @endphp
                                        @foreach ($existingStocks as $stockIndex => $stock)
                                            <tr data-row-index="{{ $loop->parent?->index }}_{{ $stockIndex }}" data-product-code="{{ $item->product->code }}">
                                                <td class="text-center text-muted">
                                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                                        <input type="checkbox"
                                                            class="form-check-input m-0 chk-autosave"
                                                            data-row-index="{{ $loop->parent?->index }}_{{ $stockIndex }}"
                                                            data-product-id="{{ $item->product_id }}"
                                                            data-stock-id="{{ $stock->id ?? '' }}"
                                                            {{ $stock ? 'checked' : '' }}>
                                                        <small>{{ $loop->parent?->iteration }}.{{ $loop->iteration }}</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong>{{ $item->product->name }}</strong>
                                                    <br><p style="font-size:14px;"><strong>{{ $item->product->code }}</strong></p>

                                                    @if(!$isLocked)
                                                        <input type="hidden"
                                                            name="items[{{ $loop->parent?->index }}_{{ $stockIndex }}][product_id]"
                                                            value="{{ $item->product_id }}">
                                                        <input type="hidden"
                                                            id="stock-id-{{ $loop->parent?->index }}_{{ $stockIndex }}"
                                                            name="items[{{ $loop->parent?->index }}_{{ $stockIndex }}][stock_id]"
                                                            value="{{ $stock->id ?? '' }}">
                                                    @endif
                                                </td>
                                                <td>{{ $item->product->satuan ?? '-' }}</td>
                                                <td>{{ $item->qty }} <span class="label label-info">{{ $item->product?->konversiDisplay($item->qty) }}</span></td>
                                                <td>
                                                    @if($isLocked)
                                                        <span class="text-success"><strong>{{ $stock->sku ?? '-' }}</strong></span>
                                                    @else
                                                        @php $skuValue = $stock->sku ?? $skuMap[$item->product_id] ?? ''; @endphp
                                                        <input type="text"
                                                            name="items[{{ $loop->parent?->index }}_{{ $stockIndex }}][sku]"
                                                            class="form-control input-sm input-sku"
                                                            placeholder="SKU"
                                                            value="{{ old('items.' . $loop->parent?->index . '_' . $stockIndex . '.sku', $skuValue) }}"
                                                            readonly
                                                            required>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($isLocked)
                                                        <span class="text-muted">{{ $stock?->expired_at?->format('d/m/Y') ?? '-' }}</span>
                                                    @else
                                                        <div class="input-group input-group-sm" style="min-width:170px">
                                                            <input type="date"
                                                                name="items[{{ $loop->parent?->index }}_{{ $stockIndex }}][expired_at]"
                                                                class="form-control input-sm input-expired"
                                                                value="{{ old('items.' . $loop->parent?->index . '_' . $stockIndex . '.expired_at', $stock?->expired_at?->format('Y-m-d')) }}">
                                                            <span class="input-group-btn">
                                                                <button type="button"
                                                                    class="btn btn-success btn-sm btn-update-expired"
                                                                    data-stock-id="{{ $stock->id ?? '' }}"
                                                                    title="Update tanggal expired"
                                                                    {{ !$stock ? 'disabled' : '' }}>
                                                                    <i class="fa fa-check"></i>
                                                                </button>
                                                            </span>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($isLocked)
                                                        <span class="label label-success">{{ $stock->qty ?? 0 }}</span>
                                                        <span class="label label-info">{{ $item->product?->konversiDisplay($stock->qty ?? 0) }}</span>
                                                    @else
                                                        <input type="number"
                                                            name="items[{{ $loop->parent?->index }}_{{ $stockIndex }}][qty_diterima]"
                                                            class="form-control input-sm text-center input-qty"
                                                            min="0" max="{{ $item->qty }}"
                                                            value="{{ old('items.' . $loop->parent?->index . '_' . $stockIndex . '.qty_diterima', $stock->qty ?? $item->qty) }}"
                                                            {{ $stock ? 'readonly' : '' }}
                                                            required>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($isLocked)
                            <div class="box-footer text-muted">
                                <i class="fa fa-lock"></i> Items sudah tersimpan dan tidak bisa diubah.
                                @if($pembelian->stocks->count())
                                    <a href="{{ route('laporan.penerimaan', [$pembelian->id, 'po']) }}" class="btn btn-info btn-xs pull-right">
                                        <i class="fa fa-file-excel-o"></i> Export Pembelian
                                    </a>
                                @endif
                            </div>
                        @else
                            <div class="box-footer text-muted">
                                <i class="fa fa-info-circle"></i> Isi SKU & qty lalu klik <strong>Simpan Penerimaan</strong>.
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-md-6">
                            {{-- Detail Penerimaan --}}
                            <div class="box box-primary">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-clipboard"></i> Detail Penerimaan</h3>
                                    @if($isLocked)
                                        <div class="box-tools pull-right">
                                            <span class="label label-success"><i class="fa fa-lock"></i> Items Terkunci</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="box-body">
                                    <div class="form-group">
                                        <label>Nomor Pembelian <span class="text-danger">*</span></label>
                                        <input type="text" name="code_gr" class="form-control"
                                            value="{{ old('code_gr', $pembelian->code_gr ?? str_replace('PO', 'PEMBELIAN', $pembelian->code)) }}"
                                            required>
                                    </div>
                                    <div class="form-group">
                                        <label>Tanggal Penerimaan <span class="text-danger">*</span></label>
                                        <input type="datetime-local" name="receipt_date" class="form-control"
                                            value="{{ old('receipt_date', $pembelian->receipt_date?->format('Y-m-d\TH:i') ?? now()->format('Y-m-d\TH:i')) }}"
                                            required>
                                    </div>
                                    <div class="form-group">
                                        <label>PIC Penerima <span class="text-danger">*</span></label>
                                        <input type="text" name="receipt_pic" class="form-control"
                                            value="{{ old('receipt_pic', $pembelian->receipt_pic ?? auth()->user()->name) }}"
                                            required>
                                    </div>
                                    <div class="form-group">
                                        <label>Status Penerimaan <span class="text-danger">*</span></label>
                                        <select name="receipt_status" class="form-control" required>
                                            <option value="draft"      {{ old('receipt_status', $pembelian->receipt_status) == 'draft'     ? 'selected' : '' }}>Draft</option>
                                            <option value="validated"  {{ old('receipt_status', $pembelian->receipt_status) == 'validated' ? 'selected' : '' }}>Validated</option>
                                            <option value="completed"  {{ old('receipt_status', $pembelian->receipt_status) == 'completed' ? 'selected' : '' }}>Completed</option>
                                        </select>
                                        @if(!$isLocked)
                                            <span class="help-block">
                                                <i class="fa fa-info-circle"></i> Set ke <strong>Completed</strong> untuk publish stok ke gudang. Setelah itu items tidak bisa diubah.
                                            </span>
                                        @endif
                                    </div>
                                    <div class="form-group">
                                        <label>Bukti Foto <small class="text-muted">(opsional)</small></label>
                                        <input type="file" name="receipt_photo" class="form-control" accept="image/*">
                                        <small class="text-muted">JPG/PNG, max 2MB</small>
                                        @if($pembelian->receipt_photo)
                                            <div style="margin-top:6px">
                                                <a href="{{ asset('storage/' . $pembelian->receipt_photo) }}" target="_blank">
                                                    <img src="{{ asset('storage/' . $pembelian->receipt_photo) }}"
                                                        style="max-width:100%; max-height:120px; border-radius:4px; border:1px solid #ddd;">
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <a href="{{ route('penerimaan.index') }}" class="btn btn-default">
                                        <i class="fa fa-arrow-left"></i> Kembali
                                    </a>
                                    <button type="submit" class="btn btn-primary pull-right">
                                        <i class="fa fa-save"></i>
                                        {{ $isLocked ? 'Update Detail' : 'Simpan Penerimaan' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            {{-- PO Info --}}
                            <div class="box box-default">
                                <div class="box-header with-border">
                                    <h3 class="box-title"><i class="fa fa-file-text-o"></i> Info Purchase Order</h3>
                                    <div class="box-tools pull-right">
                                        <button type="button" class="btn btn-box-tool" data-widget="collapse">
                                            <i class="fa fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="box-body">
                                    <table class="table table-condensed" style="margin:0">
                                        <tr><th width="130">Kode PO</th><td><strong>{{ $pembelian->code }}</strong></td></tr>
                                        <tr><th>Supplier</th><td>{{ $pembelian->supplier->name }}</td></tr>
                                        <tr><th>Total</th><td>Rp {{ $pembelian->total }}</td></tr>
                                        <tr>
                                            <th>Status Bayar</th>
                                            <td>
                                                @php $ps = $pembelian->pembelianTransaction?->status ?? 'unpaid'; @endphp
                                                <span class="label label-{{ $ps === 'paid' ? 'success' : ($ps === 'partial' ? 'warning' : 'danger') }}">
                                                    {{ strtoupper($ps) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </section>
@endsection

@section('page-script')
<script>
    $(document).on('change', '.chk-autosave', function() {
        let $checkbox = $(this);
        let $row = $checkbox.closest('tr');

        if (!$checkbox.is(':checked')) {
            return; // hanya trigger saat dicentang, bukan saat di-uncheck
        }

        let productId  = $checkbox.data('product-id');
        let stockId    = $checkbox.data('stock-id');
        let sku        = $row.find('.input-sku').val();
        let expiredAt  = $row.find('.input-expired').val();
        let qty        = $row.find('.input-qty').val();

        if (!sku || qty === '' || qty === null) {
            alert('SKU dan Qty Diterima wajib diisi sebelum menyimpan.');
            $checkbox.prop('checked', false);
            return;
        }

        $checkbox.prop('disabled', true);
        $row.css('opacity', '0.6');

        $.ajax({
            url: "{{ route('pembelian.penerimaan.save-item', $pembelian->id) }}",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                product_id: productId,
                stock_id: stockId,
                sku: sku,
                expired_at: expiredAt,
                qty_diterima: qty,
            },
            dataType: 'json',
            success: function(response) {
                $row.css('opacity', '1');
                $checkbox.prop('disabled', false);

                if (response.success) {
                    $checkbox.data('stock-id', response.stock_id);

                    // PENTING: update hidden input stock_id supaya submit form utama tahu ini UPDATE bukan CREATE
                    let rowIndex = $checkbox.data('row-index');
                    $(`#stock-id-${rowIndex}`).val(response.stock_id);

                    $row.addClass('bg-light-green');
                    // SKU & Qty dikunci setelah tersimpan, tapi Expired tetap bisa diedit lewat tombol terpisah
                    $row.find('.input-sku, .input-qty').prop('readonly', true);
                    $row.find('.btn-update-expired')
                        .prop('disabled', false)
                        .data('stock-id', response.stock_id);
                } else {
                    alert(response.message);
                    $checkbox.prop('checked', false);
                }
            },
            error: function(xhr) {
                $row.css('opacity', '1');
                $checkbox.prop('disabled', false);
                $checkbox.prop('checked', false);

                let msg = 'Gagal menyimpan item.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }
        });
    });

    $(document).on('click', '.btn-update-expired', function() {
        let $btn      = $(this);
        let $row      = $btn.closest('tr');
        let stockId   = $btn.data('stock-id');
        let expiredAt = $row.find('.input-expired').val();

        if (!stockId) {
            alert('Item ini belum tersimpan. Centang checkbox terlebih dahulu sebelum update expired.');
            return;
        }

        let $icon = $btn.find('i');
        $btn.prop('disabled', true);
        $icon.removeClass('fa-check').addClass('fa-spinner fa-spin');

        $.ajax({
            url: "{{ route('pembelian.penerimaan.update-expired', $pembelian->id) }}",
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                stock_id: stockId,
                expired_at: expiredAt,
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false);
                $icon.removeClass('fa-spinner fa-spin').addClass('fa-check');

                if (response.success) {
                    $row.find('.input-expired').css('background-color', '#dff0d8');
                    setTimeout(function() {
                        $row.find('.input-expired').css('background-color', '');
                    }, 1000);
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false);
                $icon.removeClass('fa-spinner fa-spin').addClass('fa-calendar');

                let msg = 'Gagal update expired.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }
        });
    });
</script>
<script>
    var $lastHighlighted = null;

    function clearHighlight() {
        if ($lastHighlighted) {
            $lastHighlighted.each(function() {
                $(this).css('background-color', '').css('outline', '');
            });
            $lastHighlighted = null;
        }
        $('#scan-highlight-input').val('');
    }

    function processHighlightScan(barcode) {
        barcode = barcode.trim().toLowerCase();
        if (!barcode) return;

        clearHighlight();

        var $matched = $('table tbody tr').filter(function() {
            var productCode = ($(this).data('product-code') || '').toString().trim().toLowerCase();
            return productCode === barcode;
        });

        console.log('Barcode:', barcode);
        console.log('Matched rows:', $matched.length);

        if ($matched.length === 0) {
            $('#scan-highlight-input')
                .css('border-color', '#e74c3c')
                .attr('placeholder', '"' + barcode + '" tidak ditemukan di list');

            setTimeout(function() {
                $('#scan-highlight-input')
                    .css('border-color', '')
                    .attr('placeholder', 'Scan barcode untuk highlight produk...')
                    .val('');
            }, 2000);
            return;
        }

        $matched.each(function() {
            $(this).css('background-color', '#fff3cd').css('outline', '2px solid #f39c12');
        });

        $lastHighlighted = $matched;

        var $firstMatch = $matched.first();
        var offsetTop = $firstMatch.offset().top;

        // Cari scroll container yang sebenarnya (AdminLTE pakai .content-wrapper)
        var $scrollContainer = $('body');
        var possibleContainers = ['.content-wrapper', '.wrapper', 'main', '.main-content'];

        $.each(possibleContainers, function(i, selector) {
            var $el = $(selector);
            if ($el.length && $el[0].scrollHeight > $el[0].clientHeight) {
                $scrollContainer = $el;
                return false;
            }
        });

        console.log('Scroll container:', $scrollContainer[0].tagName, $scrollContainer[0].className);

        var containerScrollTop = $scrollContainer.scrollTop();
        var containerOffsetTop = $scrollContainer.offset() ? $scrollContainer.offset().top : 0;
        var targetScroll = containerScrollTop + offsetTop - containerOffsetTop - 120;

        console.log('Container scrollTop:', containerScrollTop);
        console.log('Container offsetTop:', containerOffsetTop);
        console.log('Final target:', targetScroll);

        $scrollContainer.animate({
            scrollTop: targetScroll
        }, 500, function() {
            var flashCount = 0;
            var flashInterval = setInterval(function() {
                $firstMatch.css('background-color', flashCount % 2 === 0 ? '#ffe082' : '#fff3cd');
                flashCount++;
                if (flashCount >= 6) {
                    clearInterval(flashInterval);
                    $firstMatch.css('background-color', '#fff3cd');
                }
            }, 150);
        });
    }

    $('#scan-highlight-input').on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            processHighlightScan($(this).val());
        }
    });

    var scanTimer = null;
    $('#scan-highlight-input').on('input', function() {
        clearTimeout(scanTimer);
        var val = $(this).val();
        if (val.length >= 3) {
            scanTimer = setTimeout(function() {
                processHighlightScan(val);
            }, 300);
        }
    });

    $('#btn-clear-highlight').on('click', function() {
        clearHighlight();
        $('#scan-highlight-input').focus();
    });

    // Auto focus ke scan input saat halaman load
    $(document).ready(function() {
        $('#scan-highlight-input').focus();
    });

    // Auto focus kembali setelah scan selesai (setelah timeout not found)
    // sudah ter-handle karena input tidak di-clear otomatis

    // Kalau user klik area kosong (bukan input/button lain), kembalikan focus ke scan
    $(document).on('click', function(e) {
        var $target = $(e.target);
        var isOtherInteractive = $target.is('input, textarea, select, button, a, label') ||
                                 $target.closest('button, a, label, .select2').length;
        if (!isOtherInteractive) {
            $('#scan-highlight-input').focus();
        }
    });
</script>
@endsection
