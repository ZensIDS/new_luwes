@extends('layouts.master')

@section('title', 'Edit PO')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            Edit PO @if ($pembelian->code) — {{ $pembelian->code }} @endif
                        </h3>
                        <div class="box-tools">
                            <span id="autosave-indicator" class="text-muted small"></span>
                        </div>
                    </div><!-- /.box-header -->
                    <!-- form start: dipakai HANYA untuk tombol "Selesai" di akhir -->
                    <form action="{{ route('pembelian.finish', $pembelian) }}" method="POST" id="finish-form">
                        @csrf
                        <div class="box-body">
                            <div class="form-group">
                                <label>Supplier</label>
                                <select class="form-control select2" name="supplier_id" id="supplier_id"
                                    data-placeholder="Pilih Supplier" style="width: 100%;">
                                    <option value="" {{ $pembelian->supplier_id ? '' : 'selected' }} disabled>Pilih Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            {{ $pembelian->supplier_id == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="alert alert-info">
                                <strong>Status ACC Owner:</strong>
                                <span class="label label-{{ $pembelian->owner_approval_status === 'approved' ? 'success' : ($pembelian->owner_approval_status === 'rejected' ? 'danger' : 'warning') }}">
                                    {{ strtoupper($pembelian->owner_approval_status ?? 'pending') }}
                                </span>
                                @if ($pembelian->ownerApprovedBy)
                                    <br><small>Diproses oleh {{ $pembelian->ownerApprovedBy->name }} pada {{ $pembelian->owner_approved_at?->format('d-m-Y H:i') }}</small>
                                @endif
                                @if ($pembelian->owner_approval_note)
                                    <br><small>Catatan owner: {{ $pembelian->owner_approval_note }}</small>
                                @endif
                            </div>
                            @if (in_array(auth()->user()->role, ['owner', 'superadmin']) && $pembelian->owner_approval_status === 'pending')
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Catatan Owner (opsional)</label>
                                            <input type="text" class="form-control" id="owner-approval-note"
                                                placeholder="Catatan ACC owner">
                                        </div>
                                        <button type="button" class="btn btn-success btn-block" id="btn-owner-approve">ACC Owner</button>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Catatan Revisi Owner (opsional)</label>
                                            <input type="text" class="form-control" id="owner-reject-note"
                                                placeholder="Alasan ditolak / revisi">
                                        </div>
                                        <button type="button" class="btn btn-danger btn-block" id="btn-owner-reject">Tolak Owner</button>
                                    </div>
                                </div>
                            @endif
                            <hr>
                            <table class="table table-bordered table-striped" id="example">
                                <thead>
                                    <tr>
                                        <td>Nama Product</td>
                                        <td>Qty</td>
                                        <td>Konversi</td>
                                        <td>Harga Beli</td>
                                        <td>Sub Total</td>
                                        <td width="90">Status</td>
                                        <td>Aksi</td>
                                    </tr>
                                </thead>
                                <tbody id="product-repeater">
                                    @forelse ($pembelian->pembelianProducts as $stock)
                                        <tr data-item-id="{{ $stock->id }}">
                                            <td>
                                                <select class="form-control select2 product" data-placeholder="Pilih Product"
                                                    required style="width:100%" data-current-product="{{ $stock->product_id }}">
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control qty" required
                                                    value="{{ $stock->product->is_serialized ? ($stock->serial_numbers ? count($stock->serial_numbers) : 1) : $stock->qty }}"
                                                    min="1" {{ $stock->product->is_serialized ? 'readonly' : '' }}>
                                            </td>
                                            <td class="konversi-ratio text-center text-muted">-</td>
                                            <td>
                                                <input type="text" class="form-control harga_beli numeral-mask"
                                                    required value="{{ $stock->harga_beli }}">
                                            </td>
                                            <td>
                                                <input class="form-control subtotal" required readonly>
                                            </td>
                                            <td class="text-center row-status"><span class="label label-success">Tersimpan</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-danger remove-row" type="button">Remove</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td>
                                                <select class="form-control select2 product" data-placeholder="Pilih Product"
                                                    required style="width:100%">
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control qty" required value="1" min="1">
                                            </td>
                                            <td class="konversi-ratio text-center text-muted">-</td>
                                            <td>
                                                <input type="text" class="form-control harga_beli numeral-mask" required value="0">
                                            </td>
                                            <td>
                                                <input class="form-control subtotal" required readonly>
                                            </td>
                                            <td class="text-center row-status"><span class="label label-default">Belum tersimpan</span></td>
                                            <td>
                                                <button class="btn btn-sm btn-danger remove-row" type="button">Remove</button>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>

                            <div class="d-flex gap-2 mb-2">
                                <button class="btn btn-sm btn-warning" type="button" data-toggle="modal" data-target="#modalCekBarang">
                                    <i class="fa fa-search"></i> Cek Barang
                                </button>
                                <button class="btn btn-sm btn-primary" id="add-row" type="button">
                                    <i class="fa fa-plus"></i> Add Row
                                </button>
                            </div>
                            <hr>
                            <div class="form-group">
                                <label>Total</label>
                                <input type="text" class="form-control" id="total" readonly
                                    value="{{ number_format($pembelian->total ?? 0, 0, ',', '.') }}">
                            </div>
                        </div><!-- /.box-body -->

                        <div class="box-footer">
                            <a href="{{ route('pembelian.index') }}" class="btn btn-default">Kembali</a>
                            @if ($pembelian->canBeEditedBy(auth()->user()))
                                <button type="submit" class="btn btn-primary">Selesai</button>
                            @endif
                        </div>

                        <!-- Modal Cek Barang -->
                        <div class="modal fade" id="modalCekBarang" tabindex="-1" role="dialog" aria-labelledby="modalCekBarangLabel">
                            <div class="modal-dialog modal-lg" role="document">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                        <h4 class="modal-title" id="modalCekBarangLabel">
                                            <i class="fa fa-search"></i> Pilih Produk untuk PO
                                            <small class="text-warning">— diurutkan dari stok paling kritis</small>
                                        </h4>
                                    </div>
                                    <div class="modal-body">
                                        <table id="tableCekBarang" class="table table-bordered table-striped table-hover" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th width="30"><input type="checkbox" id="checkAll"></th>
                                                    <th>Kode</th>
                                                    <th>Nama Produk</th>
                                                    <th>Stok Saat Ini</th>
                                                    <th>Min Stok</th>
                                                    <th>Konversi</th>
                                                    <th>Status</th>
                                                    <th width="90">Qty Order</th>
                                                </tr>
                                            </thead>
                                            <tbody id="cekBarangBody"></tbody>
                                        </table>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                                        <button type="button" class="btn btn-primary" id="btnTambahkanPO">
                                            <i class="fa fa-check"></i> Tambahkan ke PO
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                    <form id="owner-approve-form" action="{{ route('pembelian.owner-approve', $pembelian->id) }}" method="POST" style="display:none;">
                        @csrf
                        <input type="hidden" name="owner_approval_note" id="owner-approve-note-hidden">
                    </form>
                    <form id="owner-reject-form" action="{{ route('pembelian.owner-reject', $pembelian->id) }}" method="POST" style="display:none;">
                        @csrf
                        <input type="hidden" name="owner_approval_note" id="owner-reject-note-hidden">
                    </form>
                </div><!-- /.box -->
            </div>
        </div>
    </section>
@endsection
@section('page-script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        const pembelianId = {{ $pembelian->id }};
        const csrfToken = '{{ csrf_token() }}';

        const routes = {
            autosaveHeader: `/pembelian/${pembelianId}/autosave-header`,
            autosaveItem:   `/pembelian/${pembelianId}/items`,
            destroyItem:    (itemId) => `/pembelian/${pembelianId}/items/${itemId}`,
        };

        function showIndicator(message, isError = false) {
            const $ind = $('#autosave-indicator');
            $ind.removeClass('text-danger text-success').addClass(isError ? 'text-danger' : 'text-success');
            $ind.text(message);
            clearTimeout(showIndicator._t);
            showIndicator._t = setTimeout(() => $ind.text(''), 2000);
        }

        function setTotal(total) {
            $('#total').val(formatRupiah(total || 0));
        }

        // ---- header autosave (supplier) ----
        // headerPending: true selama menunggu debounce ATAU selama request-nya berjalan.
        // Dipakai supaya tombol "Selesai" tahu harus menunggu sebelum submit (lihat flushPendingAutosaves).
        let headerTimeout;
        let headerPending = false;
        function autosaveHeader() {
            clearTimeout(headerTimeout);
            headerPending = true;
            headerTimeout = setTimeout(function() {
                $.ajax({
                    url: routes.autosaveHeader,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: { supplier_id: $('#supplier_id').val() },
                    success: function(res) {
                        if (res.code) {
                            $('.box-title').first().text('Edit PO — ' + res.code);
                        }
                        showIndicator('Tersimpan otomatis ✓');
                    },
                    error: function() { showIndicator('Gagal menyimpan', true); },
                    complete: function() { headerPending = false; },
                });
            }, 400);
        }

        let currentProducts = null;
        let productMap = {};
        let supplierRequest = null;
        let selectedSupplierId = $('#supplier_id').val() || null;

        // ---- anti-duplikat produk dalam 1 PO (berdasarkan id produk & barcode/kode) ----
        function rowProductId($row) {
            const $sel = $row.find('.product');
            return String($sel.val() || $sel.data('current-product') || '');
        }

        function productCode(id) {
            const p = productMap[id];
            return p && p.code ? String(p.code).trim() : '';
        }

        // Cari baris LAIN yang sudah memakai produk yang sama (id sama atau barcode sama)
        function findDuplicateRow(productId, $exceptRow) {
            productId = String(productId || '');
            if (!productId) return null;
            const code = productCode(productId);
            let $dup = null;

            $('#product-repeater tr').each(function() {
                if ($exceptRow && this === $exceptRow[0]) return;
                const otherId = rowProductId($(this));
                if (!otherId) return;
                if (otherId === productId || (code && productCode(otherId) === code)) {
                    $dup = $(this);
                    return false;
                }
            });
            return $dup;
        }

        // Produk yang sudah dipakai di PO (untuk modal Cek Barang)
        function getUsedProducts() {
            const ids = new Set();
            const codes = new Set();
            $('#product-repeater tr').each(function() {
                const id = rowProductId($(this));
                if (!id) return;
                ids.add(id);
                const code = productCode(id);
                if (code) codes.add(code);
            });
            return { ids, codes };
        }

        // Nonaktifkan option produk yang sudah dipilih di baris lain
        function refreshProductOptions() {
            const idOwner = {};
            const codeOwner = {};

            $('#product-repeater .product').each(function() {
                const id = String($(this).val() || $(this).data('current-product') || '');
                if (!id) return;
                if (!idOwner[id]) idOwner[id] = this;
                const code = productCode(id);
                if (code && !codeOwner[code]) codeOwner[code] = this;
            });

            $('#product-repeater .product').each(function() {
                const sel = this;
                $(sel).find('option').each(function() {
                    if (!this.value) return;
                    const code = productCode(this.value);
                    const byId = idOwner[this.value];
                    const byCode = code ? codeOwner[code] : null;
                    this.disabled = !!((byId && byId !== sel) || (byCode && byCode !== sel));
                });
            });
        }

        function markDuplicate($row) {
            $row.find('.row-status').html('<span class="label label-danger">Duplikat</span>');
        }

        // Tandai baris yang produknya dobel (mis. data lama yang sudah terlanjur dobel)
        function flagDuplicateRows() {
            $('#product-repeater tr').each(function() {
                const $row = $(this);
                const id = rowProductId($row);
                if (id && findDuplicateRow(id, $row)) {
                    markDuplicate($row);
                }
            });
        }

        //TODO use product's konversiDisplay instead
        function konversiDisplay(qty, konversiQty, satuanBesar, satuan) {
            satuan = satuan || 'PCS';
            qty = parseInt(qty) || 0;
            if (!konversiQty || !satuanBesar) return null;
            var boxes = Math.floor(qty / konversiQty);
            var rem = qty % konversiQty;
            if (rem === 0) return boxes + ' ' + satuanBesar;
            if (boxes > 0) return boxes + ' ' + satuanBesar + ' ' + rem + ' ' + satuan;
            return qty + ' ' + satuan;
        }
        function fmtQtyK(qty, p) {
            if (!p) return qty;
            var k = konversiDisplay(qty, p.konversi_qty, p.satuan_besar, p.satuan);
            return qty + (k ? ' <span class="label label-info">' + k + '</span>' : '');
        }

        function fmtKonversiRatio(p) {
            if (!p || !p.konversi_qty || !p.satuan_besar) {
                return '<span class="text-muted">-</span>';
            }
            var satuanKecil = p.satuan || 'PCS';
            return '1 ' + p.satuan_besar + ' = ' + p.konversi_qty + ' ' + satuanKecil;
        }

        function updateKonversiDisplay($row) {
            if (!currentProducts) return;
            let productId = $row.find('.product').val();
            let qty = parseInt($row.find('.qty').val()) || 0;
            let prod = currentProducts.find(function(p) { return p.id == productId; });
            let k = prod ? konversiDisplay(qty, prod.konversi_qty, prod.satuan_besar, prod.satuan) : null;
            $row.find('.konversi-display').html(k ? '<span class="label label-info">' + k + '</span>' : '');

            let $ratio = $row.find('.konversi-ratio');
            if (prod && prod.konversi_qty && prod.satuan_besar) {
                $ratio.removeClass('text-muted').html(fmtKonversiRatio(prod));
            } else {
                $ratio.addClass('text-muted').text('-');
            }
        }

        function buildProductRow() {
            return `
                <tr>
                    <td>
                        <select required class="form-control select2 product" data-placeholder="Pilih Product" style="width:100%;">
                            <option value="" disabled selected>Pilih Produk</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" required value="1" min="1" class="form-control qty">
                        <span class="konversi-display"></span>
                    </td>
                    <td class="konversi-ratio text-center text-muted">-</td>
                    <td><input type="text" required value="0" class="form-control harga_beli numeral-mask"></td>
                    <td><input type="text" required class="form-control subtotal" readonly></td>
                    <td class="text-center row-status"><span class="label label-default">Belum tersimpan</span></td>
                    <td><button class="btn btn-sm btn-danger remove-row" type="button">Remove</button></td>
                </tr>`;
        }

        function initializeProductRow($row) {
            $row.find('.numeral-mask').mask("#,##0", { reverse: true });
            $row.find('.select2').select2();
            $row.data('prev-product', String($row.find('.product').data('current-product') || ''));

            if (currentProducts) {
                populateProductSelects(currentProducts, $row.find('.product'));
            }

            updateKonversiDisplay($row);
            updateRowSubtotal($row);
        }

        function resetCekBarangModal() {
            $('#checkAll').prop('checked', false);
            if (cekBarangTable) {
                cekBarangTable.destroy();
                cekBarangTable = null;
            }
            $('#cekBarangBody').empty();
        }

        function resetProductRowsForSupplierChange() {
            // Batalkan autosave yang masih menunggu / berjalan untuk baris-baris lama
            $('#product-repeater tr').each(function() {
                clearTimeout($(this).data('debounce'));
                $(this).data('removed', true);
            });
            $('#product-repeater').empty();
            productMap = {};
            setTotal(0);
        }

        // Function to populate product selects with given products
        function populateProductSelects(products, target = '.product') {
            $(target).each(function() {
                let $select = $(this);
                // Prioritaskan data-current-product (dari Blade) lalu current value
                let currentProductId = $select.data('current-product') || $select.val();

                $select.empty().append('<option value="" disabled selected>Pilih Produk</option>');
                $.each(products, function(i, product) {
                    let stockText = product.stock_count ? ' [' + product.stock_count + ']' : '';
                    $select.append($('<option>', {
                        value: product.id,
                        text: product.code + ' ' + product.name + stockText,
                        'data-serialized': product.is_serialized ? 1 : 0,
                        'data-harga': product.harga_beli || 0,
                    }));
                });

                // Set nilai yang sesuai
                if (currentProductId && products.some(p => p.id == currentProductId)) {
                    $select.val(currentProductId);
                }

                $select.trigger('change.select2');
            });
        }

        function loadProductsForSupplier(supplierId) {
            currentProducts = [];
            productMap = {};
            resetCekBarangModal();
            populateProductSelects([]);

            if (!supplierId) {
                return;
            }

            if (supplierRequest) {
                supplierRequest.abort();
                supplierRequest = null;
            }

            supplierRequest = $.get('{{ route("pembelian.all-products") }}', { supplier_id: supplierId })
                .done(function(products) {
                    if (String($('#supplier_id').val() || '') !== String(supplierId)) {
                        return;
                    }

                    currentProducts = products;
                    productMap = {};
                    products.forEach(function(p) { productMap[p.id] = p; });
                    populateProductSelects(products);

                    $('#product-repeater tr').each(function() {
                        updateKonversiDisplay($(this));
                    });

                    refreshProductOptions();
                    flagDuplicateRows();
                })
                .fail(function() {
                    alert('Gagal memuat daftar produk supplier. Silakan refresh halaman.');
                })
                .always(function() {
                    supplierRequest = null;
                });
        }

        // Muat produk berdasarkan supplier saat halaman selesai dimuat
        $(document).ready(function() {
            loadProductsForSupplier($('#supplier_id').val());

            $('#product-repeater tr').each(function() {
                initializeProductRow($(this));
            });

            flagDuplicateRows();
            $('#supplier_id').select2();
        });

        $('#supplier_id').on('change', function() {
            var nextSupplierId = $(this).val() || null;

            if (String(selectedSupplierId || '') !== String(nextSupplierId || '')) {
                currentProducts = [];
                resetProductRowsForSupplierChange();
            }

            selectedSupplierId = nextSupplierId;
            loadProductsForSupplier(selectedSupplierId);
            autosaveHeader();
        });

        // Helper: format number with thousand separators (Indonesian style)
        function formatRupiah(angka) {
            if (!angka) return '0';
            return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        $('#add-row').on('click', function() {
            $('#product-repeater').append(buildProductRow());
            initializeProductRow($('#product-repeater tr:last'));
            refreshProductOptions();
        });

        function updateRowSubtotal($row) {
            let qty = parseFloat($row.find('.qty').val()) || 0;
            let $hargaInput = $row.find('.harga_beli');
            let harga_beli = ($hargaInput.data('mask') !== undefined)
                ? ($hargaInput.cleanVal() || 0)
                : (parseFloat($hargaInput.val()) || 0);
            let subtotal = qty * harga_beli;
            $row.find('.subtotal').val(formatRupiah(subtotal));
            return subtotal;
        }

        // ---- item row autosave ----
        // Aturan penting: 1 baris = maksimal 1 request simpan yang sedang berjalan.
        // Kalau ada perubahan saat request masih jalan, disimpan lagi SETELAH request selesai
        // (dengan item_id yang sudah ada). Ini mencegah 1 baris tersimpan 2x (produk dobel).
        function deleteItemRequest(itemId, onDone) {
            $.ajax({
                url: routes.destroyItem(itemId),
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: { _method: 'DELETE' },
                success: function(res) {
                    setTotal(res.total);
                    if (onDone) onDone(res);
                },
                error: function() { showIndicator('Gagal menghapus item', true); },
            });
        }

        function autosaveRow($row) {
            clearTimeout($row.data('debounce'));
            $row.data('debouncePending', false);
            if ($row.data('removed') || !document.contains($row[0])) return;

            const productId = rowProductId($row);
            const qty       = parseFloat($row.find('.qty').val()) || 0;
            const $hargaInput = $row.find('.harga_beli');
            const hargaBeli = ($hargaInput.data('mask') !== undefined)
                ? ($hargaInput.cleanVal() || 0)
                : (parseFloat($hargaInput.val()) || 0);

            updateRowSubtotal($row);

            if (!productId || qty <= 0) return;

            // Produk yang sama tidak boleh muncul 2x di 1 PO
            if (findDuplicateRow(productId, $row)) {
                markDuplicate($row);
                showIndicator('Produk sudah ada di PO ini', true);
                return;
            }

            if ($row.data('saving')) {
                $row.data('dirty', true);
                return;
            }

            $row.data('saving', true);
            $row.data('dirty', false);
            $row.find('.row-status').html('<span class="label label-warning">Menyimpan...</span>');

            $.ajax({
                url: routes.autosaveItem,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: { id: $row.data('item-id') || null, product_id: productId, qty: qty, harga_beli: hargaBeli },
                success: function(res) {
                    // Baris sudah di-Remove user selagi request jalan -> hapus item yang baru terbentuk
                    if ($row.data('removed')) {
                        deleteItemRequest(res.item_id);
                        return;
                    }

                    $row.data('item-id', res.item_id);
                    $row.attr('data-item-id', res.item_id);
                    $row.find('.row-status').html('<span class="label label-success">Tersimpan</span>');
                    setTotal(res.total);
                    showIndicator('Item tersimpan ✓');
                },
                error: function(xhr) {
                    if ($row.data('removed')) return;

                    const msg = xhr.responseJSON?.message || 'Gagal menyimpan item';
                    if (xhr.status === 422 && xhr.responseJSON?.code === 'duplicate') {
                        markDuplicate($row);
                    } else {
                        $row.find('.row-status').html('<span class="label label-danger">Gagal</span>');
                    }
                    showIndicator(msg, true);
                },
                complete: function() {
                    $row.data('saving', false);
                    if ($row.data('dirty') && !$row.data('removed')) {
                        $row.data('dirty', false);
                        autosaveRow($row);
                    }
                },
            });
        }

        $(document).on('change', '.product', function() {
            const $select = $(this);
            const $row = $select.closest('tr');
            const product_id = $select.val();

            // Tolak kalau produk (id / barcode) sudah dipilih di baris lain
            if (product_id) {
                const $dup = findDuplicateRow(product_id, $row);
                if ($dup) {
                    const prev = $row.data('prev-product') || '';
                    const name = (productMap[product_id] && productMap[product_id].name) || 'Produk ini';
                    alert(name + ' sudah ada di PO ini. Satu produk hanya boleh dipilih satu kali.');
                    $select.val(prev).trigger('change.select2');
                    refreshProductOptions();
                    return;
                }
            }

            $row.data('prev-product', product_id || '');
            $select.data('current-product', product_id || '');

            const $qtyInput = $row.find('.qty');
            const isProductSerialized = $select.find('option:selected').data('serialized');
            const hargaFromOption = $select.find('option:selected').data('harga');

            if (isProductSerialized) {
                $qtyInput.prop('readonly', true);
                if (!$qtyInput.val() || $qtyInput.val() == 0) $qtyInput.val(1);
            } else {
                $qtyInput.prop('readonly', false);
                if (!$qtyInput.val() || $qtyInput.val() == 0) $qtyInput.val(1);
            }

            if (product_id) {
                $row.find('.harga_beli').val(hargaFromOption || 0).trigger('input');
            }

            updateKonversiDisplay($row);
            refreshProductOptions();
            autosaveRow($row);   // autosaveRow membatalkan debounce dari trigger('input') di atas
        });

        // Debounce PER BARIS (sebelumnya 1 timer global dipakai bersama semua baris)
        $(document).on('input', '.qty, .harga_beli', function() {
            const $row = $(this).closest('tr');
            updateKonversiDisplay($row);
            updateRowSubtotal($row);
            clearTimeout($row.data('debounce'));
            $row.data('debouncePending', true);
            $row.data('debounce', setTimeout(function() {
                autosaveRow($row);
            }, 600));
        });

        $(document).on('click', '.remove-row', function() {
            const $row   = $(this).closest('tr');
            const itemId = $row.data('item-id');

            const finish = function() {
                $row.remove();
                if ($('#product-repeater tr').length === 0) {
                    $('#product-repeater').append(buildProductRow());
                    initializeProductRow($('#product-repeater tr:last'));
                }
                refreshProductOptions();
            };

            clearTimeout($row.data('debounce'));

            if (itemId) {
                deleteItemRequest(itemId, function() {
                    finish();
                    showIndicator('Item dihapus ✓');
                });
            } else {
                // Belum punya item_id, tapi mungkin request simpan pertamanya masih jalan
                if ($row.data('saving')) $row.data('removed', true);
                finish();
            }
        });

        $('.numeral-mask').mask("#,##0", { reverse: true });

        // ---- Cek Barang Modal ----
        let cekBarangTable = null;

        $('#modalCekBarang').on('show.bs.modal', function (e) {
            if (!$('#supplier_id').val()) {
                e.preventDefault();
                alert('Pilih supplier terlebih dahulu.');
                return;
            }

            if (!currentProducts || currentProducts.length === 0) {
                e.preventDefault();
                alert('Produk supplier belum tersedia. Coba pilih supplier atau muat ulang halaman.');
                return;
            }

            const sorted = [...currentProducts].sort((a, b) => {
                const aUnder = a.is_under_minimum ? 0 : 1;
                const bUnder = b.is_under_minimum ? 0 : 1;
                if (aUnder !== bUnder) return aUnder - bUnder;
                return a.stock_count - b.stock_count;
            });

            const tbody = $('#cekBarangBody');
            tbody.empty();

            const used = getUsedProducts();

            sorted.forEach(function (p) {
                const isUnder = p.is_under_minimum;
                const code = p.code ? String(p.code).trim() : '';
                const alreadyInPo = used.ids.has(String(p.id)) || (code && used.codes.has(code));
                const suggestedQty = Math.max(1, (p.effective_min || p.min_stock || 0) - (p.stock_count || 0));

                const $tr = $('<tr>').addClass(alreadyInPo ? 'text-muted' : (isUnder ? 'danger' : ''));

                const $checkTd = $('<td>').addClass('text-center').append(
                    $('<input>').attr({ type: 'checkbox', class: 'cek-product-check', value: p.id })
                        .prop('disabled', !!alreadyInPo)
                        .prop('checked', !alreadyInPo && isUnder)
                        .data('name', p.name).data('harga', p.harga_beli || 0)
                );
                const $statusBadge = $('<span>').addClass('label')
                    .addClass(alreadyInPo ? 'label-default' : (isUnder ? 'label-danger' : 'label-success'))
                    .text(alreadyInPo ? 'Sudah di PO' : (isUnder ? 'OUT OF STOCK' : 'Normal'));
                const $qtyInput = $('<input>')
                    .attr({
                        type: 'text',
                        class: 'form-control input-sm cek-qty'
                    })
                    .css('width', '70px')
                    .prop('disabled', !!alreadyInPo)
                    .val(!alreadyInPo && isUnder ? suggestedQty : 0)
                    .on('input', function() {
                        // 1. Hapus semua karakter yang bukan angka (termasuk tanda minus '-')
                        let value = $(this).val().replace(/[^0-9]/g, '');

                        // 2. Jika ada angka 0 di depan diikuti angka lain (misal: '02'), ubah jadi '2'
                        // Tapi jika hanya '0' saja, biarkan tetap '0'
                        if (value.length > 1 && value.startsWith('0')) {
                            value = parseInt(value, 10).toString();
                        }

                        $(this).val(value);
                    })
                    .on('blur', function() {
                        // 3. Saat pengguna meninggalkan input, jika kolom kosong, paksa jadi 0
                        let value = $(this).val();
                        if (value === '') {
                            $(this).val(0);
                        }
                    });

                $tr.append(
                    $checkTd,
                    $('<td>').text(p.code),
                    $('<td>').text(p.name),
                    $('<td>').addClass('text-center').html(fmtQtyK(p.stock_count || 0, p)),
                    $('<td>').addClass('text-center').html(fmtQtyK(p.effective_min || p.min_stock || 0, p)),
                    $('<td>').addClass('text-center').html(fmtKonversiRatio(p)),
                    $('<td>').addClass('text-center').append($statusBadge),
                    $('<td>').append($qtyInput)
                );

                tbody.append($tr);
            });

            if (cekBarangTable) {
                cekBarangTable.destroy();
            }
            cekBarangTable = $('#tableCekBarang').DataTable({
                retrieve: false,
                destroy: true,
                pageLength: 10,
                order: [],
                columnDefs: [
                    { orderable: false, targets: [0, 7] }
                ],
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ baris",
                    info: "Menampilkan _START_-_END_ dari _TOTAL_ produk",
                    paginate: { previous: "Prev", next: "Next" },
                    zeroRecords: "Tidak ada produk ditemukan"
                }
            });

            $(document).off('input', '.cek-qty').on('input', '.cek-qty', function() {
                var qty = parseInt($(this).val()) || 0;
                var $check = $(this).closest('tr').find('.cek-product-check');
                if ($check.prop('disabled')) return;
                if (qty > 0) {
                    $check.prop('checked', true);
                } else {
                    $check.prop('checked', false);
                }
            });
        });

        $(document).on('change', '#checkAll', function () {
            const checked = $(this).prop('checked');
            if (cekBarangTable) {
                cekBarangTable.rows().nodes().each(function (node) {
                    $(node).find('.cek-product-check:not(:disabled)').prop('checked', checked);
                });
            }
        });

        $('#btnTambahkanPO').on('click', function () {
            const selected = [];

            if (!cekBarangTable) {
                alert('Tabel produk belum siap.');
                return;
            }

            cekBarangTable.rows().nodes().each(function (node) {
                const $check = $(node).find('.cek-product-check:checked:not(:disabled)');
                const qty = parseInt($(node).find('.cek-qty').val()) || 0;
                if ($check.length && qty > 0) { // tambah pengecekan qty > 0
                    selected.push({
                        product_id: $check.val(),
                        name: $check.data('name'),
                        harga: $check.data('harga'),
                        qty: qty
                    });
                }
            });

            if (selected.length === 0) {
                alert('Pilih minimal satu produk.');
                return;
            }

            const $firstRow = $('#product-repeater tr:first');
            if ($firstRow.length && !rowProductId($firstRow) && !$firstRow.data('item-id')) {
                $firstRow.remove();
            }

            // Pastikan tidak ada produk dobel: terhadap isi PO saat ini DAN di antara pilihan itu sendiri
            const used = getUsedProducts();
            const skipped = [];
            const toAdd = [];

            selected.forEach(function (item) {
                const code = productCode(item.product_id);
                if (used.ids.has(String(item.product_id)) || (code && used.codes.has(code))) {
                    skipped.push(item.name);
                    return;
                }
                used.ids.add(String(item.product_id));
                if (code) used.codes.add(code);
                toAdd.push(item);
            });

            if (skipped.length) {
                alert('Produk berikut dilewati karena sudah ada di PO:\n- ' + skipped.join('\n- '));
            }

            toAdd.forEach(function (item) {
                $('#product-repeater').append(buildProductRow());
                const $newRow = $('#product-repeater tr:last');
                initializeProductRow($newRow);

                const $productSelect = $newRow.find('.product');
                const $hargaInput = $newRow.find('.harga_beli');
                const $qtyInput = $newRow.find('.qty');

                $productSelect.val(item.product_id).trigger('change.select2');
                $productSelect.data('current-product', String(item.product_id));
                $newRow.data('prev-product', String(item.product_id));
                $hargaInput.val(item.harga).trigger('input');
                $qtyInput.val(item.qty);

                updateKonversiDisplay($newRow);
                autosaveRow($newRow);   // membatalkan debounce dari trigger('input') di atas
            });

            refreshProductOptions();

            $('#modalCekBarang').modal('hide');

            // Reset semua checkbox setelah tambahkan
            cekBarangTable.rows().nodes().each(function (node) {
                $(node).find('.cek-product-check').prop('checked', false);
            });
            $('#checkAll').prop('checked', false);
        });

        $('#btn-owner-approve').on('click', function() {
            $('#owner-approve-note-hidden').val($('#owner-approval-note').val().trim());
            $('#owner-approve-form').trigger('submit');
        });

        $('#btn-owner-reject').on('click', function() {
            $('#owner-reject-note-hidden').val($('#owner-reject-note').val().trim());
            $('#owner-reject-form').trigger('submit');
        });

        // ---- Pastikan semua autosave (header + tiap baris) benar-benar selesai ----
        // sebelum tombol "Selesai" boleh submit & pindah halaman.
        // BUG LAMA: "Selesai" adalah submit form biasa (navigasi penuh). Kalau ada baris yang
        // masih menunggu debounce (belum 600ms) atau requestnya masih jalan/di-antrekan (lock per PO
        // di server memproses satu-persatu), maka begitu halaman pindah, browser MEMBATALKAN semua
        // request yang belum selesai itu — produk yang baru saja "kelihatan" tersimpan jadi
        // hilang di database, terutama saat user menambah banyak produk sekaligus lalu langsung klik Selesai.
        function isRowBusy($row) {
            return !!($row.data('debouncePending') || $row.data('saving') || $row.data('dirty'));
        }

        function anyPendingAutosave() {
            if (headerPending) return true;
            let busy = false;
            $('#product-repeater tr').each(function() {
                if (isRowBusy($(this))) { busy = true; return false; }
            });
            return busy;
        }

        function anyRowFailed() {
            return $('#product-repeater .row-status .label-danger').length > 0;
        }

        function flushPendingAutosaves(onSettled) {
            // Paksa jalankan lebih dulu semua debounce yang masih menunggu, supaya tidak perlu
            // menunggu sisa waktu debounce-nya (600ms/400ms) satu-satu.
            clearTimeout(headerTimeout);
            if (headerPending) {
                // headerTimeout sudah di-clear di atas, jadi panggil ulang requestnya sekarang juga.
                headerPending = false;
                $.ajax({
                    url: routes.autosaveHeader,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: { supplier_id: $('#supplier_id').val() },
                    complete: function() {},
                });
            }
            $('#product-repeater tr').each(function() {
                const $row = $(this);
                if ($row.data('debouncePending')) {
                    autosaveRow($row); // langsung simpan, tidak usah tunggu debounce
                }
            });

            const start = Date.now();
            const maxWaitMs = 15000;
            (function poll() {
                if (!anyPendingAutosave()) {
                    onSettled();
                    return;
                }
                if (Date.now() - start > maxWaitMs) {
                    // Jangan biarkan user menunggu tanpa batas — tetap hentikan submit dan beri tahu.
                    onSettled(true);
                    return;
                }
                setTimeout(poll, 150);
            })();
        }

        $('#finish-form').on('submit', function(e) {
            if (!anyPendingAutosave() && !anyRowFailed()) {
                return; // tidak ada yang tertunda, biarkan submit seperti biasa
            }

            e.preventDefault();
            const $btn = $(this).find('button[type="submit"]');
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Menyimpan sisa perubahan...');

            flushPendingAutosaves((timedOut) => {
                $btn.prop('disabled', false).text(originalText);

                if (anyRowFailed()) {
                    alert('Ada produk yang gagal tersimpan (lihat status "Gagal" pada tabel). Perbaiki dulu baris tersebut sebelum klik Selesai, supaya produk tidak hilang.');
                    return;
                }
                if (timedOut) {
                    alert('Masih ada perubahan yang belum selesai tersimpan. Coba klik Selesai sekali lagi dalam beberapa detik.');
                    return;
                }

                showIndicator('Semua tersimpan ✓');
                $('#finish-form').off('submit').trigger('submit');
            });
        });
    </script>
@endsection
