@extends('layouts.master')
@section('title', 'Picking Interface')
@section('container')
    <section class="content-header">
        <h1>Picking: {{ $pickingList->code }}</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-warning">
                    <div class="box-header">
                        <h3 class="box-title">Scan & Pick Items</h3>
                    </div>
                    <div class="box-body">

                        {{-- Location filter bar --}}
                        @php
                            $lokasiList = $pickingList->items
                                ->map(fn($i) => $i->location ?? $i->product?->lokasi)
                                ->unique()->filter()->sort()->values();
                        @endphp
                        <div class="row" style="margin-bottom:12px;">
                            <div class="col-sm-4">
                                <label>Filter Lokasi</label>
                                <select id="filter-lokasi" class="form-control">
                                    <option value="">Semua Lokasi</option>
                                    @foreach ($lokasiList as $lok)
                                        <option value="{{ $lok }}">{{ $lok }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-4">
                                <label>Nama Picker</label>
                                <div style="display:flex;gap:4px;">
                                    <input type="text" id="picker-name-input" class="form-control"
                                        value="{{ $pickingList->picker_name ?? auth()->user()->name }}"
                                        placeholder="Nama Picker">
                                    <button type="button" id="btn-save-picker" class="btn btn-default">
                                        <i class="fa fa-save"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-sm-2" style="padding-top:25px;">
                                <a id="btn-export"
                                   href="{{ route('laporan.pickinglist', $pickingList->id) }}"
                                   class="btn btn-success">
                                    <i class="fa fa-file-excel-o"></i> Export
                                </a>
                            </div>
                        </div>
                        
                        {{-- Global Barcode Scanner --}}
                        <div class="row" style="margin-bottom:12px;">
                            <div class="col-sm-12">
                                <div class="input-group">
                                    <span class="input-group-addon" style="background:#00c0ef;color:#fff;">
                                        <i class="fa fa-barcode"></i> Scan Global
                                    </span>
                                    <input type="text" id="global-barcode-input" class="form-control"
                                        placeholder="Scan barcode di sini — tekan Enter atau scan otomatis"
                                        autofocus
                                        style="font-size:15px; font-weight:bold;">
                                    <span class="input-group-btn">
                                        <button type="button" id="btn-global-scan" class="btn btn-info">
                                            <i class="fa fa-search"></i> Cari
                                        </button>
                                    </span>
                                </div>
                                <small class="text-muted">
                                    <i class="fa fa-info-circle"></i>
                                    Scan akan otomatis mencocokkan dengan item di list dan melakukan validasi.
                                </small>
                            </div>
                            <div class="col-sm-6">
                                <div id="scan-result-info" style="display:none; padding:8px 12px; border-radius:4px; margin-top:2px;">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive text-nowrap">
                            <table class="table table-bordered" id="pick-table">
                                <thead>
                                    <tr>
                                        <th>NO</th>
                                        <th>Kode</th>
                                        <th>Produk</th>
                                        <th>Lokasi</th>
                                        <th>SKU</th>
                                        <th>Qty to Pick</th>
                                        <th>Qty Picked</th>
                                        <th>Validasi Barcode</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($pickingList->items as $item)
                                        @php $lokasi = $item->location ?? $item->product?->lokasi; @endphp
                                        <tr data-lokasi="{{ $lokasi }}"
                                            data-item-id="{{ $item->id }}"
                                            data-product-code="{{ $item->product->code }}"
                                            data-product-name="{{ $item->product->name }}"
                                            data-validasi-url="{{ route('picking-list-items.update', $item->id) }}"
                                            class="{{ $item->is_picked ? 'bg-info' : '' }}">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $item->product->code }}</td>
                                            <td>{{ $item->product->name }}</td>
                                            <td>{{ $lokasi }}</td>
                                            <td>{{ $item->sku ?? '-' }}</td>
                                            <td>
                                                {{ $item->qty_to_pick }}
                                                @php $k = $item->product->konversiDisplay($item->qty_to_pick); @endphp
                                                @if($k !== '-')
                                                    <span class="label label-info">{{ $k }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="number" class="form-control qty-input"
                                                    value="{{ $item->qty_picked }}" min="0"
                                                    max="{{ $item->qty_to_pick }}" style="width:80px;">
                                            </td>
                                            <td>
                                                <div style="display:flex;gap:4px;align-items:center;">
                                                    <input type="text" class="form-control val-barcode-input"
                                                        placeholder="Scan barcode" style="width:120px;">
                                                </div>
                                                <div class="row-error-msg" style="display:none;color:#a94442;font-size:11px;margin-top:3px;"></div>
                                            </td>
                                            <td class="status-cell">
                                                @if ($item->is_picked)
                                                    <span class="label label-success">&#10003; PICKED</span>
                                                @else
                                                    <span class="label label-warning">PENDING</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="8"></td>
                                        <td>
                                            <button type="button" id="btn-bulk-update" class="btn btn-primary">
                                                <i class="fa fa-save"></i> Bulk Update
                                            </button>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                    </div>
                    <div class="box-footer text-center">
                        <form id="form-complete-picking" action="{{ route('picking-lists.complete', $pickingList->id) }}" method="post">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-check"></i> Complete Picking
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('page-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    var baseExportUrl    = '{{ route('laporan.pickinglist', $pickingList->id) }}';
    var bulkUpdateUrl    = '{{ route('picking-lists.bulk-update', $pickingList->id) }}';
    var pickerNameUrl    = '{{ route('picking-lists.update-picker-name', $pickingList->id) }}';
    var csrfToken        = '{{ csrf_token() }}';

    // ── row state helpers ─────────────────────────────────────────────────────

    function markRowSaved($row) {
        $row.removeClass('bg-danger').addClass('bg-info');
        $row.find('.row-error-msg').hide().text('');
        $row.find('.status-cell').html('<span class="label label-warning">PENDING</span>');
    }

    function markRowPicked($row) {
        markRowSaved($row);
        $row.find('.status-cell').html('<span class="label label-success">&#10003; PICKED</span>');
    }

    function markRowError($row, message) {
        $row.removeClass('bg-info').addClass('bg-danger');
        $row.find('.row-error-msg').text(message).show();
    }

    function clearRowState($row) {
        $row.removeClass('bg-info bg-danger');
        $row.find('.row-error-msg').hide().text('');
    }

    // ── picker name ───────────────────────────────────────────────────────────

    $('#btn-save-picker').on('click', function () {
        var $btn  = $(this).prop('disabled', true);
        var name  = $('#picker-name-input').val().trim();
        if (!name) { $btn.prop('disabled', false); return; }

        $.ajax({
            url:      pickerNameUrl,
            type:     'POST',
            dataType: 'json',
            headers:  { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            data:     { _method: 'PATCH', picker_name: name },
            success: function () {
                Swal.fire({ icon: 'success', title: 'Tersimpan', text: 'Nama picker diperbarui.', timer: 1500, showConfirmButton: false });
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Gagal', text: 'Gagal menyimpan nama picker.' });
            },
            complete: function () {
                $btn.prop('disabled', false);
            },
        });
    });

    // ── location filter ───────────────────────────────────────────────────────

    $('#filter-lokasi').on('change', function () {
        var selected = $(this).val();
        $('#pick-table tbody tr[data-lokasi]').each(function () {
            $(this).toggle(!selected || $(this).data('lokasi') == selected);
        });
        $('#btn-export').attr('href', selected
            ? baseExportUrl + '?lokasi=' + encodeURIComponent(selected)
            : baseExportUrl
        );
    });

    function submitBarcodeValidation($row) {
        var productCode  = $row.data('product-code');
        var barcode      = $row.find('.val-barcode-input').val().trim();
        var qtyPicked    = $row.find('.qty-input').val();
        var qtyMax       = parseInt($row.find('.qty-input').attr('max'));

        if (!barcode) {
            markRowError($row, 'Barcode wajib diisi untuk validasi.');
            return false;
        }
        if (barcode != productCode) {
            markRowError($row, 'Barcode tidak cocok. Expected: ' + productCode);
            return false;
        }
        if (parseInt(qtyPicked) > qtyMax) {
            markRowError($row, 'Qty melebihi maksimal (' + qtyMax + ').');
            return false;
        }

        var $input = $row.find('.val-barcode-input');
        $input.prop('disabled', true);

        $.ajax({
            url:      $row.data('validasi-url'),
            type:     'POST',
            dataType: 'json',
            headers:  { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            data:     { _method: 'PATCH', qty_picked: qtyPicked, val_barcode: barcode },
            success: function (res) {
                if (res.is_picked) {
                    markRowPicked($row);
                } else {
                    markRowSaved($row);
                }
            },
            error: function (xhr) {
                var msg = 'Validasi gagal.';
                try { msg = xhr.responseJSON.error || msg; } catch(e) {}
                markRowError($row, msg);
            },
            complete: function () {
                $input.prop('disabled', false);
            },
        });
        return true;
    }

    $(document).on('keydown', '.val-barcode-input', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitBarcodeValidation($(this).closest('tr'));
        }
    });

    $(document).on('change', '.val-barcode-input', function () {
        var value = $(this).val().trim();
        if (value) {
            submitBarcodeValidation($(this).closest('tr'));
        }
    });

    // ── bulk update ───────────────────────────────────────────────────────────
    // Saves qty_picked for all rows. If a barcode is filled in a row and it's
    // wrong, that row is flagged and excluded from the save.

    $('#btn-bulk-update').on('click', function () {
        var $btn      = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        var postData  = { _token: csrfToken };
        var skipIds   = {};

        $('#pick-table tbody tr[data-item-id]').each(function () {
            var $row        = $(this);
            var itemId      = $row.data('item-id');
            var productCode = $row.data('product-code');
            var barcode     = $row.find('.val-barcode-input').val().trim();
            var qtyPicked   = $row.find('.qty-input').val();
            var qtyMax      = parseInt($row.find('.qty-input').attr('max'));

            if (!barcode) {
                markRowError($row, 'Barcode wajib diisi untuk validasi.');
                skipIds[itemId] = true;
                return;
            }
            if (barcode != productCode) {
                markRowError($row, 'Barcode tidak cocok. Expected: ' + productCode);
                skipIds[itemId] = true;
                return;
            }
            if (parseInt(qtyPicked) > qtyMax) {
                markRowError($row, 'Qty melebihi maksimal (' + qtyMax + ').');
                skipIds[itemId] = true;
                return;
            }

            postData['items[' + itemId + '][qty_picked]'] = qtyPicked;
        });

        var pickerName = $('#picker-name-input').val().trim();
        if (pickerName) { postData['picker_name'] = pickerName; }

        var hasItems = Object.keys(postData).some(function (k) { return k.indexOf('items[') === 0; });
        if (!hasItems) {
            $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Bulk Update');
            return;
        }

        $.ajax({
            url:      bulkUpdateUrl,
            type:     'POST',
            dataType: 'json',
            headers:  { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            data:     postData,
            success: function (res) {
                $.each(res.updated || {}, function (itemId, info) {
                    if (skipIds[itemId]) return; // already marked error
                    var $row = $('#pick-table tbody tr[data-item-id="' + itemId + '"]');
                    if (info.is_picked) {
                        markRowPicked($row);
                    } else {
                        markRowSaved($row);
                    }
                });
                $.each(res.errors || {}, function (itemId, msg) {
                    var $row = $('#pick-table tbody tr[data-item-id="' + itemId + '"]');
                    markRowError($row, msg);
                });
            },
            error: function (xhr) {
                var msg = 'Bulk update gagal.';
                try { msg = xhr.responseJSON.error || msg; } catch(e) {}
                Swal.fire({ icon: 'error', title: 'Error', text: msg });
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-save"></i> Bulk Update');
            },
        });
    });

    // ── complete picking ──────────────────────────────────────────────────────
    // Block submission if any rows are not picked. Highlight them and show a
    // SweetAlert listing which products still need to be picked.

    $('#form-complete-picking').on('submit', function (e) {
        e.preventDefault();

        var unpicked = [];
        $('#pick-table tbody tr[data-item-id]').each(function () {
            var isPicked = $(this).find('.status-cell .label-success').length > 0;
            if (!isPicked) {
                $(this).addClass('bg-danger');
                unpicked.push($(this).data('product-name'));
            }
        });

        if (unpicked.length > 0) {
            var listHtml = unpicked.map(function (n) { return '<li style="text-align:left">' + n + '</li>'; }).join('');
            Swal.fire({
                icon:             'warning',
                title:            'Belum Semua Item Dipick!',
                html:             'Item berikut belum selesai:<ul style="margin-top:8px">' + listHtml + '</ul>',
                confirmButtonText: 'OK',
            });
            return;
        }

        // All picked — inject picker_name and submit
        var pickerName = $('#picker-name-input').val().trim();
        if (pickerName) {
            $('<input>').attr({ type: 'hidden', name: 'picker_name', value: pickerName }).appendTo(this);
        }
        this.submit();
    });
    
    // ── global barcode scanner ────────────────────────────────────────────────

        function processGlobalScan(barcode) {
            barcode = barcode.trim();
            if (!barcode) return;
        
            var $matched = null;
        
            // Cari row yang product code-nya cocok dengan barcode
            $('#pick-table tbody tr[data-item-id]').each(function() {
                var productCode = String($(this).data('product-code')).trim();
                if (productCode === barcode) {
                    // Cek apakah row ini visible (filter lokasi aktif)
                    if ($(this).is(':visible')) {
                        $matched = $(this);
                        return false; // break loop
                    } else {
                        // Ada di list tapi ter-filter lokasi — tetap proses
                        $matched = $(this);
                        return false;
                    }
                }
            });
        
            if (!$matched) {
                // Produk tidak ada di picking list
                Swal.fire({
                    icon: 'error',
                    title: 'Tidak Ditemukan',
                    html: '<strong>' + barcode + '</strong> tidak ada dalam picking list ini.',
                    timer: 2500,
                    showConfirmButton: false,
                });
                $('#global-barcode-input').val('').focus();
                return;
            }
        
            var isPicked = $matched.find('.status-cell .label-success').length > 0;
        
            if (isPicked) {
                // Sudah di-pick sebelumnya
                var productName = $matched.data('product-name');
                Swal.fire({
                    icon: 'info',
                    title: 'Sudah Di-pick',
                    html: '<strong>' + productName + '</strong> sudah berstatus PICKED.',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end',
                });
                $('#global-barcode-input').val('').focus();
                return;
            }
        
            // Isi barcode input di row tersebut
            var $barcodeInput = $matched.find('.val-barcode-input');
            $barcodeInput.val(barcode);
        
            // Scroll ke row tersebut dengan highlight sementara
            $matched.addClass('bg-warning');
            $('html, body').animate({
                scrollTop: $matched.offset().top - 100
            }, 300, function() {
                setTimeout(function() { $matched.removeClass('bg-warning'); }, 800);
            });
        
            // Jalankan validasi barcode row tersebut
            var qtyPicked  = $matched.find('.qty-input').val() || 1;
            var qtyMax     = parseInt($matched.find('.qty-input').attr('max'));
            var productCode = $matched.data('product-code');
            var productName = $matched.data('product-name');
        
            // Pastikan qty_picked minimal 1
            if (parseInt(qtyPicked) < 1) {
                $matched.find('.qty-input').val(1);
                qtyPicked = 1;
            }
        
            if (parseInt(qtyPicked) > qtyMax) {
                Swal.fire({
                    icon: 'error',
                    title: 'Qty Melebihi Batas',
                    html: '<strong>' + productName + '</strong><br>Max: ' + qtyMax,
                    timer: 2500,
                    showConfirmButton: false,
                });
                $('#global-barcode-input').val('').focus();
                return;
            }
        
            // AJAX validasi
            $.ajax({
                url:      $matched.data('validasi-url'),
                type:     'POST',
                dataType: 'json',
                headers:  { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                data:     { _method: 'PATCH', qty_picked: qtyPicked, val_barcode: barcode },
                success: function(res) {
                    if (res.is_picked) {
                        markRowPicked($matched);
                        Swal.fire({
                            icon: 'success',
                            title: 'PICKED!',
                            html: '<strong>' + productName + '</strong> berhasil di-pick.',
                            timer: 1800,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end',
                        });
                    } else {
                        markRowSaved($matched);
                        Swal.fire({
                            icon: 'success',
                            title: 'Tersimpan',
                            html: '<strong>' + productName + '</strong> qty picked diperbarui.',
                            timer: 1800,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end',
                        });
                    }
                },
                error: function(xhr) {
                    var msg = 'Validasi gagal.';
                    try { msg = xhr.responseJSON.error || msg; } catch(e) {}
                    markRowError($matched, msg);
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        html: '<strong>' + productName + '</strong><br>' + msg,
                        timer: 2500,
                        showConfirmButton: false,
                    });
                },
                complete: function() {
                    $('#global-barcode-input').val('').focus();
                }
            });
        }
        
        // Trigger scan saat Enter
        $('#global-barcode-input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                processGlobalScan($(this).val());
            }
        });
        
        // Trigger scan saat klik tombol
        $('#btn-global-scan').on('click', function() {
            processGlobalScan($('#global-barcode-input').val());
        });
        
        // Auto-focus kembali ke scan global kalau user klik area lain
        // (kecuali kalau lagi focus di input lain yang penting)
        $(document).on('click', function(e) {
            var $target = $(e.target);
            var isOtherInput = $target.is('input, textarea, select, button, a') ||
                               $target.closest('button, a, .select2').length;
            if (!isOtherInput) {
                $('#global-barcode-input').focus();
            }
        });
        
        // Focus ke scan global saat halaman load
        $(document).ready(function() {
            $('#global-barcode-input').focus();
        });
</script>
@endsection
