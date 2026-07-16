@extends('layouts.master')

@section('title', 'Tambah Retur Pembelian')

@section('container')
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah Retur Pembelian</h3>
                    </div>

                    <form action="{{ route('refundPembelian.store') }}" method="POST" id="refund-form">
                        @csrf

                        {{-- Hidden type field, updated when tab changes --}}
                        <input type="hidden" name="type" id="type" value="{{ $isStaffOutlet ? 'outlet_ke_gudang' : 'gudang_ke_supplier' }}">

                        <div class="box-body">

                            {{-- ── Type Tab Selector (hidden for staff-outlet) ── --}}
                            @if (!$isStaffOutlet)
                            <ul class="nav nav-tabs" id="typeTab" style="margin-bottom:20px">
                                <li class="active">
                                    <a href="#tab-supplier" data-toggle="tab" data-type="gudang_ke_supplier">
                                        <i class="fa fa-arrow-up"></i> Gudang ke Supplier
                                    </a>
                                </li>
                                <li>
                                    <a href="#tab-outlet" data-toggle="tab" data-type="outlet_ke_gudang">
                                        <i class="fa fa-arrow-down"></i> Outlet ke Gudang
                                    </a>
                                </li>
                            </ul>
                            @endif

                            {{-- ── Common Fields ── --}}
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Kode Retur</label>
                                        <input type="text" class="form-control" name="code"
                                            value="{{ old('code', $code) }}" required>
                                        @error('code')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Tanggal</label>
                                        <input type="date" class="form-control" name="tanggal"
                                            value="{{ old('tanggal', date('Y-m-d')) }}" required>
                                        @error('tanggal')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="tab-content">

                                {{-- ══════════════════════════════════════════════
                            TAB 1: Gudang ke Supplier (hidden for staff-outlet)
                            ══════════════════════════════════════════════ --}}
                                <div class="tab-pane {{ $isStaffOutlet ? '' : 'active' }}" id="tab-supplier"
                                    style="{{ $isStaffOutlet ? 'display:none' : '' }}">
                                    <div class="form-group">
                                        <label>Supplier <span class="text-danger">*</span></label>
                                        <select id="supplier_id" class="form-control select2" name="supplier_id"
                                            data-placeholder="Pilih Supplier" style="width:100%">
                                            <option value="" selected>Pilih Supplier</option>
                                            @foreach ($suppliers as $s)
                                                <option value="{{ $s->id }}"
                                                    {{ old('supplier_id') == $s->id ? 'selected' : '' }}>
                                                    {{ $s->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('supplier_id')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div id="supplier-product-area" style="display:none">
                                        <h4>Daftar Produk Gudang</h4>
                                        <div class="text-muted small" style="margin-bottom:8px">
                                            Produk otomatis dari stok gudang supplier. Hapus baris yang tidak diretur.
                                        </div>
                                        <div id="supplier-bulk-bar" style="display:none; margin-bottom:8px">
                                            <button type="button" class="btn btn-danger btn-sm" id="btn-bulk-delete-supplier">
                                                <i class="fa fa-trash"></i> Hapus Terpilih (<span id="supplier-selected-count">0</span>)
                                            </button>
                                            <button type="button" class="btn btn-default btn-sm" id="btn-clear-supplier">
                                                <i class="fa fa-times"></i> Batalkan Pilihan
                                            </button>
                                        </div>
                                        <div class="table-responsive text-nowrap">
                                            <table class="table table-bordered table-striped" id="tbl-supplier">
                                                <thead class="bg-light-blue">
                                                    <tr>
                                                        <th width="30" class="text-center">
                                                            <input type="checkbox" id="chk-all-supplier" style="cursor:pointer" title="Pilih Semua">
                                                        </th>
                                                        <th>Produk</th>
                                                        <th width="80">SKU/Batch</th>
                                                        <th width="70">No. PO</th>
                                                        <th width="70">Tersedia</th>
                                                        <th width="80">Qty Retur</th>
                                                        <th width="110">Harga Satuan</th>
                                                        <th>Alasan</th>
                                                        <th width="60">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="supplier-repeater"></tbody>
                                            </table>
                                        </div>
                                        <div class="form-group">
                                            <label>Total Retur (IDR)</label>
                                            <input type="text" class="form-control numeral-mask" name="total"
                                                id="total-supplier" readonly value="0">
                                        </div>
                                    </div>
                                    <div id="supplier-loading" style="display:none" class="text-center text-muted">
                                        <i class="fa fa-spinner fa-spin"></i> Memuat produk...
                                    </div>
                                    <div id="supplier-empty" style="display:none" class="alert alert-warning">
                                        Tidak ada stok gudang untuk supplier ini.
                                    </div>
                                </div>

                                {{-- ══════════════════════════════════════════════
                            TAB 2: Outlet ke Gudang
                            ══════════════════════════════════════════════ --}}
                                <div class="tab-pane {{ $isStaffOutlet ? 'active' : '' }}" id="tab-outlet">
                                    <div class="form-group">
                                        <label>Outlet <span class="text-danger">*</span></label>
                                        <select id="outlet_id" class="form-control select2" name="outlet_id"
                                            data-placeholder="Pilih Outlet" style="width:100%"
                                            {{ $isStaffOutlet ? '' : 'disabled' }}>
                                            <option value="" selected>Pilih Outlet</option>
                                            @foreach ($outlets as $o)
                                                <option value="{{ $o->id }}"
                                                    {{ (old('outlet_id') ?? $staffOutletId) == $o->id ? 'selected' : '' }}>
                                                    {{ $o->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('outlet_id')
                                            <div class="text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div id="outlet-product-area" style="display:none">
                                        <h4>Daftar Produk Outlet</h4>
                                        <div class="text-muted small" style="margin-bottom:8px">
                                            Produk otomatis dari stok outlet. Hapus baris yang tidak diretur.
                                        </div>
                                        <div id="outlet-bulk-bar" style="display:none; margin-bottom:8px">
                                            <button type="button" class="btn btn-danger btn-sm" id="btn-bulk-delete-outlet">
                                                <i class="fa fa-trash"></i> Hapus Terpilih (<span id="outlet-selected-count">0</span>)
                                            </button>
                                            <button type="button" class="btn btn-default btn-sm" id="btn-clear-outlet">
                                                <i class="fa fa-times"></i> Batalkan Pilihan
                                            </button>
                                        </div>
                                        <div class="table-responsive text-nowrap">
                                            <table class="table table-bordered table-striped" id="tbl-outlet">
                                                <thead class="bg-green">
                                                    <tr>
                                                        <th width="30" class="text-center">
                                                            <input type="checkbox" id="chk-all-outlet" style="cursor:pointer" title="Pilih Semua">
                                                        </th>
                                                        <th>Produk</th>
                                                        <th width="80">SKU/Batch</th>
                                                        <th width="90">No. DO</th>
                                                        <th width="70">Tersedia</th>
                                                        <th width="80">Qty Retur</th>
                                                        <th>Alasan</th>
                                                        <th width="60">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="outlet-repeater"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div id="outlet-loading" style="display:none" class="text-center text-muted">
                                        <i class="fa fa-spinner fa-spin"></i> Memuat produk...
                                    </div>
                                    <div id="outlet-empty" style="display:none" class="alert alert-warning">
                                        Tidak ada stok outlet untuk delivery order ini.
                                    </div>
                                </div>

                            </div>{{-- end tab-content --}}

                        </div>{{-- end box-body --}}

                        <div class="box-footer">
                            <a href="{{ route('refundPembelian.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary" id="btn-submit">
                                <i class="fa fa-save"></i> Simpan Terpilih
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('page-script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        var dtSupplier = null;
        var dtOutlet   = null;
        var supplierSelected = new Set();
        var outletSelected   = new Set();
        var supplierXhr = null;
        var outletXhr   = null;

        // ── Tab switching ──────────────────────────────────────────────────────────
        $('#typeTab a[data-toggle="tab"]').on('shown.bs.tab', function() {
            var type = $(this).data('type');
            $('#type').val(type);
            if (type === 'gudang_ke_supplier') {
                $('#outlet_id, #delivery_order_id').prop('disabled', true).removeAttr('required');
                $('#supplier_id').prop('disabled', false);
            } else {
                $('#supplier_id').prop('disabled', true).removeAttr('required');
                $('#outlet_id').prop('disabled', false).attr('required', true);
            }
            checkSubmit();
        });

        // ── Numeral mask ──────────────────────────────────────────────────────────
        function applyMask() {
            $('.numeral-mask').mask("#,##0", { reverse: true });
        }
        applyMask();

        // ── Submit guard ──────────────────────────────────────────────────────────
        function checkSubmit() {
            $('#btn-submit').prop('disabled', false);
        }

        function setRowSelectionState($row, isSelected) {
            $row.find('input, select, textarea').not('.row-check-supplier, .row-check-outlet').each(function() {
                var $input = $(this);

                if ($input.data('original-required') === undefined) {
                    $input.data('original-required', $input.prop('required'));
                }

                if ($input.is('[type="hidden"]')) {
                    $input.prop('disabled', !isSelected);
                    return;
                }

                if ($input.data('original-required')) {
                    $input.prop('required', isSelected);
                } else {
                    $input.prop('required', false);
                }
            });
        }

        function syncSelectionRowState($checkbox) {
            setRowSelectionState($checkbox.closest('tr'), $checkbox.is(':checked'));
        }

        function prepareRowsForSubmit() {
            $('#supplier-repeater tr, #outlet-repeater tr').each(function() {
                var $row = $(this);
                var $checkbox = $row.find('.row-check-supplier, .row-check-outlet').first();
                var isSelected = $checkbox.is(':checked');
                var isActiveTypeRow =
                    ($('#type').val() === 'gudang_ke_supplier' && $checkbox.hasClass('row-check-supplier')) ||
                    ($('#type').val() === 'outlet_ke_gudang' && $checkbox.hasClass('row-check-outlet'));

                $row.find('input, select, textarea').not('.row-check-supplier, .row-check-outlet').each(function() {
                    var $input = $(this);

                    if ($input.data('original-required') === undefined) {
                        $input.data('original-required', $input.prop('required'));
                    }

                    if ($input.is('[type="hidden"]')) {
                        $input.prop('disabled', !(isSelected && isActiveTypeRow));
                        return;
                    }

                    $input.prop('disabled', !(isSelected && isActiveTypeRow));
                    $input.prop('required', !!($input.data('original-required') && isSelected && isActiveTypeRow));
                });
            });
        }

        // ── Remove row (individual) ───────────────────────────────────────────────
        function removeRow(btn) {
            var row = $(btn).closest('tr');
            var sc  = row.find('.row-check-supplier');
            var oc  = row.find('.row-check-outlet');
            if (sc.length && dtSupplier) {
                supplierSelected.delete(String(sc.data('stock-id')));
                dtSupplier.row(row).remove().draw(false);
                updateSupplierBulkBar();
                syncChkAllSupplier();
            } else if (oc.length && dtOutlet) {
                outletSelected.delete(String(oc.data('stock-id')));
                dtOutlet.row(row).remove().draw(false);
                updateOutletBulkBar();
                syncChkAllOutlet();
            } else {
                row.remove();
            }
            recalcTotal();
            checkSubmit();
        }

        // ── Recalc total (supplier only) ──────────────────────────────────────────
        function recalcTotal() {
            var total = 0;
            $('#supplier-repeater tr').each(function() {
                var qty   = parseInt($(this).find('.input-qty').val()) || 0;
                var harga = parseInt($(this).find('.input-harga').val().replace(/,/g, '')) || 0;
                total += qty * harga;
            });
            $('#total-supplier').val(total.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','));
        }

        // ── SUPPLIER: helpers ─────────────────────────────────────────────────────
        function updateSupplierBulkBar() {
            var n = supplierSelected.size;
            $('#supplier-bulk-bar').toggle(n > 0);
            $('#supplier-selected-count').text(n);
        }

        function syncChkAllSupplier() {
            if (!dtSupplier) return;
            var visible = dtSupplier.rows({ search: 'applied' });
            var total   = visible.count();
            var selCnt  = 0;
            visible.nodes().each(function(node) {
                if (supplierSelected.has(String($(node).find('.row-check-supplier').data('stock-id')))) selCnt++;
            });
            var chk = document.getElementById('chk-all-supplier');
            if (!chk) return;
            chk.checked       = total > 0 && selCnt === total;
            chk.indeterminate = selCnt > 0 && selCnt < total;
        }

        // ── OUTLET: helpers ───────────────────────────────────────────────────────
        function updateOutletBulkBar() {
            var n = outletSelected.size;
            $('#outlet-bulk-bar').toggle(n > 0);
            $('#outlet-selected-count').text(n);
        }

        function syncChkAllOutlet() {
            if (!dtOutlet) return;
            var visible = dtOutlet.rows({ search: 'applied' });
            var total   = visible.count();
            var selCnt  = 0;
            visible.nodes().each(function(node) {
                if (outletSelected.has(String($(node).find('.row-check-outlet').data('stock-id')))) selCnt++;
            });
            var chk = document.getElementById('chk-all-outlet');
            if (!chk) return;
            chk.checked       = total > 0 && selCnt === total;
            chk.indeterminate = selCnt > 0 && selCnt < total;
        }

        // ── SUPPLIER: select-all ──────────────────────────────────────────────────
        $(document).on('change', '#chk-all-supplier', function() {
            if (!dtSupplier) return;
            var checked = this.checked;
            dtSupplier.rows({ search: 'applied' }).nodes().each(function(node) {
                if (!node) return;
                var $chk = $(node).find('.row-check-supplier');
                var id = String($chk.data('stock-id'));
                if (id && id !== 'undefined') {
                    checked ? supplierSelected.add(id) : supplierSelected.delete(id);
                    $chk.prop('checked', checked).trigger('change');
                }
            });
            updateSupplierBulkBar();
            syncChkAllSupplier();
        });

        // ── SUPPLIER: individual checkbox ─────────────────────────────────────────
        $(document).on('change', '.row-check-supplier', function() {
            var id = String($(this).data('stock-id'));
            this.checked ? supplierSelected.add(id) : supplierSelected.delete(id);
            syncSelectionRowState($(this));
            updateSupplierBulkBar();
            syncChkAllSupplier();
        });

        // ── SUPPLIER: bulk delete ─────────────────────────────────────────────────
        $(document).on('click', '#btn-bulk-delete-supplier', function() {
            var ids = Array.from(supplierSelected);
            ids.forEach(function(id) {
                dtSupplier.rows(function(idx, data, node) {
                    return String($(node).find('.row-check-supplier').data('stock-id')) === id;
                }).remove();
            });
            supplierSelected.clear();
            dtSupplier.draw(false);
            updateSupplierBulkBar();
            syncChkAllSupplier();
            recalcTotal();
            checkSubmit();
        });

        // ── SUPPLIER: clear selection ─────────────────────────────────────────────
        $(document).on('click', '#btn-clear-supplier', function() {
            supplierSelected.clear();
            dtSupplier.draw(false);
            updateSupplierBulkBar();
            syncChkAllSupplier();
        });

        // ── OUTLET: select-all ────────────────────────────────────────────────────
        $(document).on('change', '#chk-all-outlet', function() {
            if (!dtOutlet) return;
            var checked = this.checked;
            dtOutlet.rows({ search: 'applied' }).nodes().each(function(node) {
                if (!node) return;
                var $chk = $(node).find('.row-check-outlet');
                var id = String($chk.data('stock-id'));
                if (id && id !== 'undefined') {
                    checked ? outletSelected.add(id) : outletSelected.delete(id);
                    $chk.prop('checked', checked).trigger('change');
                }
            });
            updateOutletBulkBar();
            syncChkAllOutlet();
        });

        // ── OUTLET: individual checkbox ───────────────────────────────────────────
        $(document).on('change', '.row-check-outlet', function() {
            var id = String($(this).data('stock-id'));
            this.checked ? outletSelected.add(id) : outletSelected.delete(id);
            syncSelectionRowState($(this));
            updateOutletBulkBar();
            syncChkAllOutlet();
        });

        // ── OUTLET: bulk delete ───────────────────────────────────────────────────
        $(document).on('click', '#btn-bulk-delete-outlet', function() {
            var ids = Array.from(outletSelected);
            ids.forEach(function(id) {
                dtOutlet.rows(function(idx, data, node) {
                    return String($(node).find('.row-check-outlet').data('stock-id')) === id;
                }).remove();
            });
            outletSelected.clear();
            dtOutlet.draw(false);
            updateOutletBulkBar();
            syncChkAllOutlet();
            checkSubmit();
        });

        // ── OUTLET: clear selection ───────────────────────────────────────────────
        $(document).on('click', '#btn-clear-outlet', function() {
            outletSelected.clear();
            dtOutlet.draw(false);
            updateOutletBulkBar();
            syncChkAllOutlet();
        });

        // ── Tab switching (disabled for staff-outlet) ─────────────────────────────
        @if ($isStaffOutlet)
        // Staff-outlet: lock to outlet tab, enable outlet select, auto-load
        $('#supplier_id').prop('disabled', true);
        $('#outlet_id').prop('disabled', false);
        $(function() {
            @if ($staffOutletId)
            // Outlet already pre-selected; trigger load
            if ($('#outlet_id').val()) {
                $('#outlet_id').trigger('change');
            }
            @endif
        });
        @endif

        // ── TAB 1: Supplier change → load warehouse stocks ────────────────────────
        $('#supplier_id').on('change', function() {
            var supplierId = $(this).val();
            if (!supplierId) return;

            if (supplierXhr) { supplierXhr.abort(); supplierXhr = null; }

            $('#supplier-product-area, #supplier-empty').hide();
            $('#supplier-loading').show();
            supplierSelected.clear();
            updateSupplierBulkBar();
            $('#btn-submit').prop('disabled', true);

            if (dtSupplier) { dtSupplier.destroy(); dtSupplier = null; }
            $('#supplier-repeater').empty();

            supplierXhr = $.get('/retur/supplier/' + supplierId + '/products', function(data) {
                supplierXhr = null;
                $('#supplier-loading').hide();

                if (!data.length) {
                    $('#supplier-empty').show();
                    return;
                }

                $.each(data, function(i, item) {
                    var row = `
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="row-check-supplier" data-row-key="${i}" data-stock-id="${item.stock_id}" style="cursor:pointer">
                        </td>
                        <td>
                            ${item.product_name}
                            <input type="hidden" name="product[${i}][product_id]" value="${item.product_id}">
                            <input type="hidden" name="product[${i}][stock_id]" value="${item.stock_id}">
                            <input type="hidden" name="product[${i}][sku]" value="${item.sku}">
                        </td>
                        <td><span class="label label-default">${item.sku}</span></td>
                        <td><small class="text-muted">${item.pembelian_code}</small></td>
                        <td><span class="badge bg-blue">${item.qty_available}</span></td>
                        <td>
                            <input type="number" class="form-control input-qty" style="width:70px" name="product[${i}][qty]" value="1"
                                min="1" max="${item.qty_available}" required>
                        </td>
                        <td>
                            <input type="text" class="form-control numeral-mask input-harga" style="width:100px" name="product[${i}][harga]"
                                value="${item.harga_beli}" required>
                        </td>
                        <td>
                            <input type="text" class="form-control" name="product[${i}][alasan]" placeholder="Alasan retur..." required>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                    $('#supplier-repeater').append(row);
                    setRowSelectionState($('#supplier-repeater tr:last'), false);
                });

                applyMask();
                $('#supplier-product-area').show();

                dtSupplier = $('#tbl-supplier').DataTable({
                    pageLength: 10,
                    language: {
                        search: 'Cari:',
                        lengthMenu: 'Tampilkan _MENU_ baris',
                        info: 'Menampilkan _START_–_END_ dari _TOTAL_ produk',
                        paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
                        emptyTable: 'Tidak ada produk'
                    },
                    columnDefs: [{ orderable: false, targets: [0, 5, 6, 7, 8] }],
                    drawCallback: function() {
                        $('#supplier-repeater .row-check-supplier').each(function() {
                            $(this).prop('checked', supplierSelected.has(String($(this).data('stock-id'))));
                            syncSelectionRowState($(this));
                        });
                        syncChkAllSupplier();
                    }
                });

                recalcTotal();
                checkSubmit();

                $('#supplier-repeater').off('input', '.input-qty, .input-harga')
                    .on('input', '.input-qty, .input-harga', function() { recalcTotal(); });
            }).fail(function(xhr) {
                if (xhr.statusText === 'abort') return;
                $('#supplier-loading').hide();
                alert('Gagal memuat produk supplier.');
            });
        });

        // ── TAB 2: Outlet change → load all outlet stocks ─────────────────────────
        $('#outlet_id').on('change', function() {
            var outletId = $(this).val();
            if (!outletId) return;

            if (outletXhr) { outletXhr.abort(); outletXhr = null; }

            $('#outlet-product-area, #outlet-empty').hide();
            $('#outlet-loading').show();
            outletSelected.clear();
            updateOutletBulkBar();
            $('#btn-submit').prop('disabled', true);

            if (dtOutlet) { dtOutlet.destroy(); dtOutlet = null; }
            $('#outlet-repeater').empty();

            outletXhr = $.get('/retur/outlet/' + outletId + '/products', function(data) {
                outletXhr = null;
                $('#outlet-loading').hide();

                if (!data.length) {
                    $('#outlet-empty').show();
                    return;
                }

                $.each(data, function(i, item) {
                    var row = `
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="row-check-outlet" data-row-key="${i}" data-stock-id="${item.stock_id}" style="cursor:pointer">
                        </td>
                        <td>
                            ${item.product_name}
                            <input type="hidden" name="product[${i}][product_id]" value="${item.product_id}">
                            <input type="hidden" name="product[${i}][stock_id]" value="${item.stock_id}">
                        </td>
                        <td><span class="label label-default">${item.sku}</span></td>
                        <td><small class="text-muted">${item.do_code}</small></td>
                        <td><span class="badge bg-green">${item.qty_available}</span></td>
                        <td>
                            <input type="number" class="form-control" style="width:70px"
                                name="product[${i}][qty]" value="1"
                                min="1" max="${item.qty_available}" required>
                        </td>
                        <td>
                            <input type="text" class="form-control" name="product[${i}][alasan]"
                                placeholder="Alasan retur..." required>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">
                                <i class="fa fa-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                    $('#outlet-repeater').append(row);
                    setRowSelectionState($('#outlet-repeater tr:last'), false);
                });

                $('#outlet-product-area').show();

                dtOutlet = $('#tbl-outlet').DataTable({
                    pageLength: 10,
                    language: {
                        search: 'Cari:',
                        lengthMenu: 'Tampilkan _MENU_ baris',
                        info: 'Menampilkan _START_–_END_ dari _TOTAL_ produk',
                        paginate: { previous: 'Sebelumnya', next: 'Berikutnya' },
                        emptyTable: 'Tidak ada produk'
                    },
                    columnDefs: [{ orderable: false, targets: [0, 4, 5, 6, 7] }],
                    drawCallback: function() {
                        $('#outlet-repeater .row-check-outlet').each(function() {
                            $(this).prop('checked', outletSelected.has(String($(this).data('stock-id'))));
                            syncSelectionRowState($(this));
                        });
                        syncChkAllOutlet();
                    }
                });

                checkSubmit();
            }).fail(function(xhr) {
                if (xhr.statusText === 'abort') return;
                $('#outlet-loading').hide();
                alert('Gagal memuat stok outlet.');
            });
        });

        // ── Auto-load on page ready (browser restore / old() after validation) ──────
        $(function() {
            var sid = $('#supplier_id').val();
            var oid = $('#outlet_id').val();
            if (sid) {
                $('#supplier_id').trigger('change');
            } else if (oid) {
                $('#outlet_id').trigger('change');
            }
        });

        $('#refund-form').on('submit', function(e) {
            var $form = $(this);
            var type = $('#type').val();
            var selected = [];

            $form.find('input[name="selected_rows[]"]').remove();

            if (type === 'gudang_ke_supplier') {
                $('#supplier-repeater .row-check-supplier:checked').each(function() {
                    selected.push($(this).data('row-key'));
                });
            } else {
                $('#outlet-repeater .row-check-outlet:checked').each(function() {
                    selected.push($(this).data('row-key'));
                });
            }

            if (selected.length === 0) {
                e.preventDefault();
                alert('Pilih minimal satu baris retur yang dicentang.');
                return false;
            }

            selected.forEach(function(key) {
                $form.append('<input type="hidden" name="selected_rows[]" value="' + key + '">');
            });

            prepareRowsForSubmit();
        });
    </script>
@endsection
