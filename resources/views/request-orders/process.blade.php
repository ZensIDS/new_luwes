@extends('layouts.master')
@section('title', 'Proses Request Order')
@section('container')
@php
    $groupedItems = $pickingList->items->groupBy('product_id');
    $totalProducts = $groupedItems->count();
@endphp
<section class="content-header">
    <h1>Proses & Kirim <small>{{ $requestOrder->code }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('request-orders.index') }}">Request Order</a></li>
        <li class="active">Proses {{ $requestOrder->code }}</li>
    </ol>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">

            {{-- Info RO --}}
            <div class="box box-default">
                <div class="box-body">
                    <div style="display:flex; gap:24px; flex-wrap:wrap; align-items:center;">
                        <div><strong>Kode RO:</strong> {{ $requestOrder->code }}</div>
                        <div><strong>Outlet:</strong> {{ $requestOrder->owner->name }}</div>
                        <div><strong>Tanggal:</strong> {{ $requestOrder->request_date->format('d/m/Y') }}</div>
                        <div>
                            <strong>Status:</strong>
                            <span class="label label-warning">{{ strtoupper($requestOrder->status) }}</span>
                        </div>
                        <div><strong>Total Item:</strong> {{ $totalProducts }} produk</div>
                    </div>
                </div>
                <hr>
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-barcode"></i> Scan Barcode</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-addon" style="background:#f39c12; color:#fff;">
                                    <i class="fa fa-barcode"></i>
                                </span>
                                <input type="text"
                                    id="global-scan-input"
                                    class="form-control"
                                    placeholder="Scan atau ketik barcode di sini..."
                                    autofocus
                                    autocomplete="off">
                                <span class="input-group-btn">
                                    <button type="button" id="btn-manual-scan" class="btn btn-warning btn-flat">
                                        <i class="fa fa-search"></i> Scan
                                    </button>
                                </span>
                            </div>
                            <small class="text-muted" style="display: block; margin-top: 5px;">
                                <i class="fa fa-info-circle"></i>
                                Scan barcode atau ketik manual lalu tekan <strong>Enter</strong>.
                            </small>
                        </div>
                        <div class="col-md-7">
                            <div id="scan-feedback" style="display:none; padding:6px 12px; border-radius:4px; font-size:13px; font-weight:bold;"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabel Item --}}
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-list"></i> Daftar Item</h3>
                    <div class="box-tools pull-right">
                        <span class="badge" id="picked-count-badge" style="background:#00a65a; font-size:13px; padding:5px 10px;">
                            0 / {{ $totalProducts }} Picked
                        </span>
                    </div>
                </div>
                <div class="box-body table-responsive" style="padding:0;">
                    <table class="table table-bordered table-striped" style="margin:0;">
                        <thead>
                            <tr>
                                <th width="40" class="text-center">No</th>
                                <th>Produk</th>
                                <th>Qty Diminta</th>
                                <th>Status</th>
                                <th>Stok Terpilih</th>
                            </tr>
                        </thead>
                        <tbody>
                            {{-- Grouping per produk: baris split-SKU (FEFO/FIFO) digabung jadi 1 baris,
                                 detail SKU-nya ditaruh di kolom Stok Terpilih. --}}
                            @foreach ($groupedItems as $productId => $group)
                                @php
                                    $primary = $group->first();
                                    $product = $primary->product;
                                    $totalQty = $group->sum('qty_to_pick');
                                    $isPicked = $group->every(fn($i) => $i->is_picked == 1);
                                @endphp
                                <tr id="item-row-{{ $productId }}"
                                    data-item-id="{{ $primary->id }}"
                                    data-product-id="{{ $productId }}"
                                    data-product-code="{{ $product->code }}"
                                    class="{{ $isPicked ? 'success' : '' }}">

                                    <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                    <td>
                                        <strong>{{ $product->name }}</strong>
                                        <br><p style="font-size: 14px;"><b>{{ $product->code }}</b></p>
                                    </td>
                                    <td class="qty-diminta-cell">
                                        <div class="input-group input-group-md" style="min-width:150px">
                                            <input type="text"
                                                inputmode="numeric"
                                                pattern="[0-9]*"
                                                autocomplete="off"
                                                class="form-control input-md input-qty-diminta"
                                                value="{{ $totalQty }}">
                                            <span class="input-group-btn">
                                                <button type="button"
                                                    class="btn btn-warning btn-sm btn-update-qty"
                                                    data-item-id="{{ $primary->id }}"
                                                    title="Update qty diminta">
                                                    <i class="fa fa-check"></i>
                                                </button>
                                            </span>
                                        </div>
                                    </td>

                                    <td class="status-cell">
                                        @if($isPicked)
                                            <span class="label label-success"><i class="fa fa-check"></i> PICKED</span>
                                        @else
                                            <span class="label label-warning">PENDING</span>
                                        @endif
                                    </td>

                                    <td class="stock-info-cell">
                                        @if($isPicked && $group->count())
                                            <ul style="margin:0; padding-left:16px; font-size:12px;">
                                                @foreach($group as $g)
                                                    <li><strong>
                                                        {{ $g->sku ?? '-' }} — {{ $g->qty_picked }} pcs
                                                        @if($g->stock && $g->stock->expired_at)
                                                            | Exp: {{ \Carbon\Carbon::parse($g->stock->expired_at)->format('d/m/Y') }}
                                                        @endif
                                                    </strong>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <small class="text-muted">-</small>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="box-footer text-right">
                    <a href="{{ route('request-orders.index') }}" class="btn btn-default">Kembali</a>
                    <button type="button" id="btn-complete-picking" class="btn btn-success">
                        <i class="fa fa-paper-plane"></i> Selesai & Kirim
                    </button>
                </div>
            </div>

        </div>
    </div>
</section>
@endsection

@section('page-script')
<style>
    @keyframes blinkHighlight {
        0%, 49%   { background-color: #ffeb3b !important; outline: 2px solid #f39c12; }
        50%, 100% { background-color: transparent; outline: 2px solid transparent; }
    }
    .blink-target > td {
        animation: blinkHighlight 0.35s steps(1, end) 6;
    }
</style>
<script>
$(document).ready(function() {
    const scanUrl = "{{ route('request-orders.scan-pick', $requestOrder->id) }}";
    const completeUrl = "{{ route('request-orders.complete-ship', $requestOrder->id) }}";
    const updateQtyUrl = "{{ route('request-orders.update-qty', $requestOrder->id) }}";

    const $scanInput = $('#global-scan-input');
    const $feedback = $('#scan-feedback');
    const $btnManual = $('#btn-manual-scan');
    const $btnComplete = $('#btn-complete-picking');

    $('form').on('submit', function(e) {
        e.preventDefault();
    });

    setTimeout(function() {
        $scanInput.focus();
    }, 300);

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.btn, input').length) {
            $scanInput.focus();
        }
    });

    $scanInput.on('keydown', function(e) {
        if (e.keyCode === 13 || e.which === 13) {
            e.preventDefault();
            e.stopPropagation();
            performScan();
        }
    });

    $btnManual.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        performScan();
    });

    function performScan() {
        let barcode = $scanInput.val().trim();

        if (barcode === '' || barcode === undefined) {
            showFeedback('danger', 'Barcode tidak boleh kosong.');
            return;
        }

        $feedback.hide().removeClass('bg-green bg-yellow bg-red').html('');

        $.ajax({
            url: scanUrl,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                barcode: barcode
            },
            dataType: 'json',
            beforeSend: function() {
                $btnManual.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
            },
            success: function(response) {
                $scanInput.val('').focus();
                $btnManual.prop('disabled', false).html('<i class="fa fa-search"></i> Scan');

                if (response.already) {
                    showFeedback('warning', response.message);
                    scrollAndBlink(response.product_code || barcode);
                }
                else if (response.success) {
                    showFeedback('success', response.message);

                    // Semua SKU hasil split (FEFO/FIFO) digabung jadi 1 baris per produk.
                    // Qty Diminta TIDAK diubah di sini (nilainya tidak berubah oleh proses split),
                    // hanya Status & daftar Stok Terpilih yang diperbarui.
                    let $row = $(`tr[data-product-code="${barcode}"]`);

                    let stockListHtml = '<ul style="margin:0; padding-left:16px; font-size:12px;" class="text-muted">';
                    response.items.forEach(function(subItem) {
                        let expires = subItem.expired_at && subItem.expired_at !== '-' ? ` | Exp: ${subItem.expired_at}` : '';
                        stockListHtml += `<li>${subItem.sku ?? '-'} — ${subItem.qty} pcs${expires}</li>`;
                    });
                    stockListHtml += '</ul>';

                    $row.addClass('success');
                    $row.find('.status-cell').html('<span class="label label-success"><i class="fa fa-check"></i> PICKED</span>');
                    $row.find('.stock-info-cell').html(stockListHtml);

                    updatePickedBadge();
                    scrollAndBlink(barcode);
                }
            },
            error: function(xhr) {
                $scanInput.val('').focus();
                $btnManual.prop('disabled', false).html('<i class="fa fa-search"></i> Scan');

                let msg = 'Terjadi kesalahan sistem atau barcode tidak terdaftar.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                showFeedback('danger', msg);
            }
        });
    }

    function showFeedback(type, message) {
        let alertClass = type === 'success' ? 'bg-green' : (type === 'warning' ? 'bg-yellow' : 'bg-red');
        let icon = type === 'success' ? 'check' : (type === 'warning' ? 'exclamation-triangle' : 'ban');

        $feedback.removeClass('bg-green bg-yellow bg-red')
            .addClass(alertClass)
            .html(`<i class="icon fa fa-${icon}"></i> ${message}`)
            .fadeIn();

        if (type !== 'danger') {
            setTimeout(() => { $feedback.fadeOut(); }, 3500);
        }
    }

    function highlightRow(productCode) {
        let $row = $(`tr[data-product-code="${productCode}"]`);
        let originalBg = $row.css('background-color');
        $row.css('background-color', '#f39c12');
        setTimeout(() => {
            $row.css('background-color', originalBg);
        }, 1200);
    }

    function scrollAndBlink(productCode) {
        let $row = $(`tr[data-product-code="${productCode}"]`);
        if ($row.length === 0) return;

        $row.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });

        setTimeout(function() {
            // Reset dulu supaya animasi restart walau class masih nempel dari scan sebelumnya
            $row.removeClass('blink-target');
            void $row.get(0).offsetWidth; // force reflow, trik supaya browser "lupa" state animasi lama
            $row.addClass('blink-target');

            setTimeout(function() {
                $row.removeClass('blink-target');
            }, 2300);
        }, 200);
    }

    function updatePickedBadge() {
        let totalItems = $('tbody tr').length;
        let pickedItems = $('tbody tr.success').length;
        $('#picked-count-badge').text(`${pickedItems} / ${totalItems} Picked`);
    }

    updatePickedBadge();

    // Batasi input Qty Diminta hanya menerima digit angka (input teks, bukan number, biar aman dari scroll)
    $(document).on('input', '.input-qty-diminta', function() {
        let $input = $(this);
        let cleaned = $input.val().replace(/[^0-9]/g, '');
        if (cleaned !== $input.val()) {
            $input.val(cleaned);
        }
    });

    $(document).on('click', '.btn-update-qty', function() {
        let $btn = $(this);
        let $row = $btn.closest('tr');
        let $input = $row.find('.input-qty-diminta');
        let itemId = $btn.data('item-id');
        let newQty = $input.val().trim();

        if (newQty === '' || !/^[0-9]+$/.test(newQty)) {
            alert('Qty diminta harus berupa angka.');
            return;
        }

        let $icon = $btn.find('i');
        $btn.prop('disabled', true);
        $icon.removeClass('fa-check').addClass('fa-spinner fa-spin');

        $.ajax({
            url: updateQtyUrl,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                item_id: itemId,
                qty_to_pick: newQty,
            },
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false);
                $icon.removeClass('fa-spinner fa-spin').addClass('fa-check');

                if (response.success) {
                    $input.val(response.qty_to_pick_total);
                    $input.css('background-color', '#dff0d8');
                    setTimeout(function() {
                        $input.css('background-color', '');
                    }, 1000);

                    // Sinkronkan ulang Status & Stok Terpilih sesuai alokasi FEFO/FIFO yang baru
                    if (response.is_picked) {
                        $row.addClass('success');
                        $row.find('.status-cell').html('<span class="label label-success"><i class="fa fa-check"></i> PICKED</span>');

                        let stockListHtml = '<ul style="margin:0; padding-left:16px; font-size:12px;" class="text-muted">';
                        (response.stock_items || []).forEach(function(s) {
                            let expires = s.expired_at && s.expired_at !== '-' ? ` | Exp: ${s.expired_at}` : '';
                            stockListHtml += `<li>${s.sku ?? '-'} — ${s.qty} pcs${expires}</li>`;
                        });
                        stockListHtml += '</ul>';
                        $row.find('.stock-info-cell').html(stockListHtml);
                    } else {
                        $row.removeClass('success');
                        $row.find('.status-cell').html('<span class="label label-warning">PENDING</span>');
                        $row.find('.stock-info-cell').html('<small class="text-muted">-</small>');
                    }

                    updatePickedBadge();
                } else {
                    alert(response.message);
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false);
                $icon.removeClass('fa-spinner fa-spin').addClass('fa-check');

                let msg = 'Gagal update qty diminta.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }
        });
    });

    $btnComplete.on('click', function(e) {
        e.preventDefault();

        if (!confirm('Apakah Anda yakin semua item telah selesai di-pick dan siap diproses kirim?')) {
            return;
        }

        $.ajax({
            url: completeUrl,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            dataType: 'json',
            beforeSend: function() {
                $btnComplete.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memproses...');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.message);
                    window.location.href = response.redirect;
                }
            },
            error: function(xhr) {
                $btnComplete.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Selesai & Kirim');
                let msg = 'Gagal menyelesaikan picking.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                alert(msg);
            }
        });
    });
});
</script>
@endsection
