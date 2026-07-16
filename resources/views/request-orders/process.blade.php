@extends('layouts.master')
@section('title', 'Proses Request Order')
@section('container')
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
                        <div><strong>Total Item:</strong> {{ $pickingList->items->count() }} produk</div>
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
                            0 / {{ $pickingList->items->count() }} Picked
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
                            {{-- PERBAIKAN UTAMA: Looping data dari draf pickingList items --}}
                            @foreach ($pickingList->items as $index => $item)
                            <tr id="item-row-{{ $item->id }}"
                                data-item-id="{{ $item->id }}"
                                data-product-code="{{ $item->product->code }}"
                                class="{{ $item->is_picked == 1 ? 'success' : '' }}">

                                <td class="text-center text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <strong>{{ $item->product->name }}</strong>
                                    <br><small class="text-muted">{{ $item->product->code }}</small>
                                </td>
                                <td>{{ $item->qty_to_pick }}</td>

                                {{-- Menggunakan kondisi is_picked (0 atau 1) --}}
                                <td class="status-cell">
                                    @if($item->is_picked == 1)
                                        <span class="label label-success"><i class="fa fa-check"></i> PICKED</span>
                                    @else
                                        <span class="label label-warning">PENDING</span>
                                    @endif
                                </td>

                                <td class="stock-info-cell">
                                    @if($item->is_picked == 1 && $item->stock)
                                        <small class="text-muted">
                                            {{ $item->stock->sku ?? '-' }}
                                            @if($item->stock->expired_at)
                                                | Exp: {{ \Carbon\Carbon::parse($item->stock->expired_at)->format('d/m/Y') }}
                                            @endif
                                        </small>
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
<script>
$(document).ready(function() {
    const scanUrl = "{{ route('request-orders.scan-pick', $requestOrder->id) }}";
    const completeUrl = "{{ route('request-orders.complete-ship', $requestOrder->id) }}";

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
                    highlightRow(response.item_id);
                }
                else if (response.success) {
                    showFeedback('success', response.message);

                    // 1. UPDATE BARIS INDUK UTAMA
                    response.items.forEach(function(subItem) {
                        if (!subItem.is_child) {
                            let expires = subItem.expired_at !== '-' ? ` | Exp: ${subItem.expired_at}` : '';
                            let $row = $(`#item-row-${subItem.id}`);

                            $row.addClass('success');
                            $row.find('td:nth-child(3)').text(subItem.qty); // Update qty baris induk ke porsi SKU pertama
                            $row.find('.status-cell').html('<span class="label label-success"><i class="fa fa-check"></i> PICKED</span>');
                            $row.find('.stock-info-cell').html(`<small class="text-muted">${subItem.sku ?? '-'} ${expires}</small>`);

                            // Tambahkan data-attribute pendukung untuk memudahkan pengelompokan subtotal nanti
                            $row.attr('data-group-code', subItem.product_code);
                            $row.attr('data-pure-qty', subItem.qty);
                        }
                    });

                    // 2. MENYISIPKAN BARIS SPLIT (CHILD) TEPAT DI BAWAH INDUKNYA
                    response.items.forEach(function(subItem) {
                        if (subItem.is_child) {
                            let expires = subItem.expired_at !== '-' ? ` | Exp: ${subItem.expired_at}` : '';

                            // Hapus baris child dengan ID yang sama jika sebelumnya sudah pernah dirender (mencegah duplikasi)
                            $(`#item-row-${subItem.id}`).remove();

                            // Cari baris terakhir dari kelompok produk yang sama agar split SKU berurutan ke bawah
                            let $targetRow = $(`tr[data-group-code="${subItem.product_code}"]`).last();

                            if ($targetRow.length === 0) {
                                $targetRow = $(`tr:contains("${subItem.product_code}")`).first();
                            }

                            let newRowHtml = `
                                <tr id="item-row-${subItem.id}" data-item-id="${subItem.id}" data-group-code="${subItem.product_code}" data-pure-qty="${subItem.qty}" class="success" style="background-color: #fafafa;">
                                    <td class="text-center text-muted">-</td>
                                    <td style="padding-left: 25px;">
                                        <i class="fa fa-level-up fa-rotate-90 text-muted" style="margin-right: 5px;"></i>
                                        <strong>${subItem.product_name}</strong>
                                        <br><small class="text-muted">${subItem.product_code} (Split SKU)</small>
                                    </td>
                                    <td>${subItem.qty}</td>
                                    <td class="status-cell">
                                        <span class="label label-success"><i class="fa fa-check"></i> PICKED</span>
                                    </td>
                                    <td class="stock-info-cell">
                                        <small class="text-muted">${subItem.sku ?? '-'} ${expires}</small>
                                    </td>
                                </tr>
                            `;

                            if ($targetRow.length > 0) {
                                $targetRow.after(newRowHtml);
                            } else {
                                $('tbody').append(newRowHtml);
                            }
                        }
                    });

                    // 3. KALKULASI DINAMIS UNTUK BARIS TOTAL SUM PER PRODUK
                    // Ambil semua kode produk unik yang ada di dalam response kali ini
                    let uniqueProductCodes = [...new Set(response.items.map(item => item.product_code))];

                    uniqueProductCodes.forEach(function(prodCode) {
                        // Hapus baris total summary lama untuk produk ini jika sudah ada sebelumnya
                        $(`tr.summary-row[data-summary-code="${prodCode}"]`).remove();

                        let totalQtyPicked = 0;
                        let productName = '';

                        // Hitung total akumulasi qty dari baris induk dan semua baris split-nya
                        $(`tr[data-group-code="${prodCode}"]`).each(function() {
                            let qty = parseInt($(this).attr('data-pure-qty')) || 0;
                            totalQtyPicked += qty;
                            if (!productName) {
                                productName = $(this).find('strong').first().text();
                            }
                        });

                        // Buat baris Summary Total baru dengan style warna abu-abu tipis pembeda
                        let summaryRowHtml = `
                            <tr class="summary-row" data-summary-code="${prodCode}" style="background-color: #f4f4f4; font-weight: bold; border-top: 2px solid #ddd;">
                                <td class="text-center"><i class="fa fa-calculator text-muted"></i></td>
                                <td>TOTAL ${productName.toUpperCase()}</td>
                                <td style="font-size: 15px; color: #00a65a;">${totalQtyPicked} pcs</td>
                                <td class="text-center"><span class="label label-success">READY</span></td>
                                <td class="text-muted" style="font-size: 11px; font-weight: normal; vertical-align: middle;">Gabungan Semua SKU</td>
                            </tr>
                        `;

                        // Letakkan baris total summary tepat di bawah baris terakhir dari kelompok produk tersebut
                        $(`tr[data-group-code="${prodCode}"]`).last().after(summaryRowHtml);
                    });

                    updatePickedBadge();
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

    function highlightRow(itemId) {
        let $row = $(`#item-row-${itemId}`);
        let originalBg = $row.css('background-color');
        $row.css('background-color', '#f39c12');
        setTimeout(() => {
            $row.css('background-color', originalBg);
        }, 1200);
    }

    function updatePickedBadge() {
        let totalItems = $('tbody tr').length;
        let pickedItems = $('tbody tr.success').length;
        $('#picked-count-badge').text(`${pickedItems} / ${totalItems} Picked`);
    }

    updatePickedBadge();

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
