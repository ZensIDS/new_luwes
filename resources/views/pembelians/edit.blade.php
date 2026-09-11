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
        let headerTimeout;
        function autosaveHeader() {
            clearTimeout(headerTimeout);
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
                });
            }, 400);
        }

        let currentProducts = null;
        let supplierRequest = null;
        let selectedSupplierId = $('#supplier_id').val() || null;

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
                    <td><input type="text" required value="0" class="form-control harga_beli numeral-mask"></td>
                    <td><input type="text" required class="form-control subtotal" readonly></td>
                    <td class="text-center row-status"><span class="label label-default">Belum tersimpan</span></td>
                    <td><button class="btn btn-sm btn-danger remove-row" type="button">Remove</button></td>
                </tr>`;
        }

        function initializeProductRow($row) {
            $row.find('.numeral-mask').mask("#,##0", { reverse: true });
            $row.find('.select2').select2();

            if (currentProducts) {
                populateProductSelects(currentProducts, $row.find('.product'));
            }

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
            $('#product-repeater').empty();
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
                    populateProductSelects(products);
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
        function autosaveRow($row) {
            const productId = $row.find('.product').val();
            const qty       = parseFloat($row.find('.qty').val()) || 0;
            const $hargaInput = $row.find('.harga_beli');
            const hargaBeli = ($hargaInput.data('mask') !== undefined)
                ? ($hargaInput.cleanVal() || 0)
                : (parseFloat($hargaInput.val()) || 0);
            const itemId    = $row.data('item-id') || null;

            updateRowSubtotal($row);

            if (!productId || qty <= 0) return;

            $.ajax({
                url: routes.autosaveItem,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: { id: itemId, product_id: productId, qty: qty, harga_beli: hargaBeli },
                success: function(res) {
                    $row.data('item-id', res.item_id);
                    $row.attr('data-item-id', res.item_id);
                    $row.find('.row-status').html('<span class="label label-success">Tersimpan</span>');
                    setTotal(res.total);
                    showIndicator('Item tersimpan ✓');
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Gagal menyimpan item';
                    $row.find('.row-status').html('<span class="label label-danger">Gagal</span>');
                    showIndicator(msg, true);
                },
            });
        }

        let rowDebounce;
        $(document).on('change', '.product', function() {
            let $row = $(this).closest('tr');
            let $qtyInput = $row.find('.qty');
            let product_id = $(this).val();
            let isProductSerialized = $(this).find('option:selected').data('serialized');
            let hargaFromOption = $(this).find('option:selected').data('harga');

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

            autosaveRow($row);
        });

        $(document).on('input', '.qty, .harga_beli', function() {
            let $row = $(this).closest('tr');
            updateRowSubtotal($row);
            clearTimeout(rowDebounce);
            rowDebounce = setTimeout(function() {
                autosaveRow($row);
            }, 600);
        });

        $(document).on('click', '.remove-row', function() {
            if ($('#product-repeater tr').length <= 1) {
                $(this).closest('tr').remove();
                $('#product-repeater').append(buildProductRow());
                initializeProductRow($('#product-repeater tr:last'));
                return;
            }

            const $row   = $(this).closest('tr');
            const itemId = $row.data('item-id');

            if (itemId) {
                $.ajax({
                    url: routes.destroyItem(itemId),
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    data: { _method: 'DELETE' },
                    success: function(res) {
                        $row.remove();
                        setTotal(res.total);
                        showIndicator('Item dihapus ✓');
                    },
                    error: function() { showIndicator('Gagal menghapus item', true); },
                });
            } else {
                $row.remove();
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

            sorted.forEach(function (p) {
                const isUnder = p.is_under_minimum;

                const $tr = $('<tr>').addClass(isUnder ? 'danger' : '');

                const $checkTd = $('<td>').addClass('text-center').append(
                    $('<input>').attr({ type: 'checkbox', class: 'cek-product-check', value: p.id })
                        .data('name', p.name).data('harga', p.harga_beli || 0)
                );
                const $statusBadge = $('<span>').addClass('label')
                    .addClass(isUnder ? 'label-danger' : 'label-success')
                    .text(isUnder ? 'OUT OF STOCK' : 'Normal');
                const $qtyInput = $('<input>')
                    .attr({
                        type: 'text',
                        class: 'form-control input-sm cek-qty'
                    })
                    .css('width', '70px')
                    .val(0) // Nilai awal kembali ke 0
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
                    $('<td>').addClass('text-center').text(p.stock_count || 0),
                    $('<td>').addClass('text-center').text(p.effective_min || p.min_stock || 0),
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
                    { orderable: false, targets: [0, 6] }
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
                    $(node).find('.cek-product-check').prop('checked', checked);
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
                const $check = $(node).find('.cek-product-check:checked');
                const qty = parseInt($(node).find('.cek-qty').val()) || 0;
                if ($check.length && qty > 0) { // tambah pengecekan qty > 0
                    const $row = $(node);
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
            if ($firstRow.length && ($firstRow.find('.product').val() === null || $firstRow.find('.product').val() === '')) {
                $firstRow.remove();
            }

            selected.forEach(function (item) {
                $('#product-repeater').append(buildProductRow());
                const $newRow = $('#product-repeater tr:last');
                initializeProductRow($newRow);

                const $productSelect = $newRow.find('.product');
                const $hargaInput = $newRow.find('.harga_beli');
                const $qtyInput = $newRow.find('.qty');

                $productSelect.val(item.product_id).trigger('change.select2');
                $hargaInput.val(item.harga).trigger('input');
                $qtyInput.val(item.qty);

                autosaveRow($newRow);
            });

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
    </script>
@endsection
