@extends('layouts.master')

@section('title', 'Belanja Langsung')

@section('container')
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            Belanja Langsung — {{ $purchase->code }}
                            @if ($purchase->isDraft())
                                <span class="label label-default">Draft</span>
                            @else
                                <span class="label label-warning">Menunggu Penerimaan</span>
                            @endif
                        </h3>
                        <div class="box-tools">
                            <span id="autosave-indicator" class="text-muted small"></span>
                            <a href="{{ route('outlet-purchases.print', $purchase) }}" class="btn btn-xs btn-success">
                                <i class="fa fa-print"></i> Print
                            </a>
                            <button type="button" class="btn btn-xs btn-warning btn-sync-failed">
                                <i class="fa fa-refresh"></i> Sync Ulang Item Gagal
                            </button>
                        </div>
                    </div>

                    {{-- Form ini dipakai HANYA untuk tombol "Selesai"; semua isian disimpan otomatis (autosave). --}}
                    <form action="{{ route('outlet-purchases.finish', $purchase) }}" method="POST" id="finish-form" novalidate>
                        @csrf
                        <div class="box-body">
                            <div class="alert alert-info">
                                Gunakan halaman ini hanya jika outlet membeli langsung dari supplier. Semua isian <strong>tersimpan otomatis</strong>.
                                Klik <strong>Selesai</strong> bila pengisian sudah lengkap: transaksi menjadi <strong>Menunggu Penerimaan</strong>.
                                <strong>Stock toko belum bertambah</strong> sampai barang diterima (klik <em>Lihat</em> di daftar, isi qty yang datang).
                                <strong>Tanggal kadaluarsa</strong> diisi saat penerimaan barang, bukan di halaman ini.
                                Outlet tidak bisa diganti; jika salah outlet, hapus draft lalu buat lagi.
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Outlet</label>
                                        <input type="text" class="form-control" value="{{ $purchase->outlet?->name }}" readonly>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Supplier</label>
                                        <select class="form-control select2" id="supplier_id" data-placeholder="Pilih Supplier" style="width:100%;">
                                            <option value="" {{ $purchase->supplier_id ? '' : 'selected' }} disabled>Pilih Supplier</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}" {{ $purchase->supplier_id == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Tanggal</label>
                                        <input type="date" class="form-control" id="purchase_date" value="{{ $purchase->purchase_date?->format('Y-m-d') ?? date('Y-m-d') }}">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>No. nota supplier <small>(opsional)</small></label>
                                        <input type="text" class="form-control" id="invoice_number" maxlength="255" value="{{ $purchase->invoice_number }}">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Catatan <small>(opsional)</small></label>
                                <input type="text" class="form-control" id="notes" maxlength="2000" value="{{ $purchase->notes }}">
                            </div>
                            <hr>

                            <div class="table-responsive">
                                <table class="table table-bordered table-striped" id="purchase-items">
                                    <thead>
                                        <tr>
                                            <th>Nama Product</th>
                                            <th width="110">Qty</th>
                                            <th>Konversi</th>
                                            <th width="170">Harga Beli</th>
                                            <th width="150">Sub Total</th>
                                            <th width="110">Status</th>
                                            <th width="90">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="product-repeater">
                                        @foreach ($purchase->items as $item)
                                            <tr data-item-id="{{ $item->id }}">
                                                <td>
                                                    <select class="form-control select2 product" style="width:100%"
                                                        data-placeholder="Pilih Product"
                                                        data-current-product="{{ $item->product_id }}"
                                                        data-current-label="{{ $item->product ? $item->product->code . ' ' . $item->product->name : 'Produk #' . $item->product_id }}">
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control qty" min="1" step="1" value="{{ $item->qty }}">
                                                    <span class="konversi-display"></span>
                                                </td>
                                                <td class="konversi-ratio text-center text-muted">-</td>
                                                <td>
                                                    <input type="text" class="form-control harga_beli numeral-mask" value="{{ (int) round($item->harga_beli) }}">
                                                    <small class="harga-diff"></small>
                                                </td>
                                                <td><input class="form-control subtotal" readonly></td>
                                                <td class="text-center row-status"><span class="label label-success">Tersimpan</span></td>
                                                <td><button class="btn btn-sm btn-danger remove-row" type="button">Remove</button></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <button class="btn btn-sm btn-warning" type="button" data-toggle="modal" data-target="#modalCekBarang">
                                <i class="fa fa-search"></i> Cek Barang
                            </button>
                            <button class="btn btn-sm btn-primary" id="add-row" type="button">
                                <i class="fa fa-plus"></i> Add Row
                            </button>
                            <hr>
                            <div class="form-group">
                                <label>Total</label>
                                <input type="text" class="form-control" id="total" readonly value="{{ number_format($purchase->subtotal ?? 0, 0, ',', '.') }}">
                            </div>
                        </div>

                        <div class="box-footer">
                            <a href="{{ route('outlet-purchases.index') }}" class="btn btn-default">Kembali</a>
                            <a href="{{ route('outlet-purchases.print', $purchase) }}" class="btn btn-success">
                                <i class="fa fa-print"></i> Print
                            </a>
                            <button type="button" class="btn btn-warning btn-sync-failed">
                                <i class="fa fa-refresh"></i> Sync Ulang Item Gagal
                            </button>
                            <button type="submit" class="btn btn-primary">Selesai</button>
                        </div>
                    </form>

                    <!-- Modal Cek Barang (stok, min stok, dan Dalam Pesanan khusus toko ini) -->
                    <div class="modal fade" id="modalCekBarang" tabindex="-1" role="dialog" aria-labelledby="modalCekBarangLabel">
                        <div class="modal-dialog modal-lg" role="document" style="width:95%;max-width:1200px;">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                    <h4 class="modal-title" id="modalCekBarangLabel">
                                        <i class="fa fa-search"></i> Pilih Produk untuk Belanja Langsung
                                        <small class="text-warning">— {{ $purchase->outlet?->name }}, diurutkan dari stok paling kritis</small>
                                    </h4>
                                </div>
                                <div class="modal-body">
                                    <table id="tableCekBarang" class="table table-bordered table-striped table-hover" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th width="30"><input type="checkbox" id="checkAll"></th>
                                                <th>Kode</th>
                                                <th>Nama Produk</th>
                                                <th>Stok Toko</th>
                                                <th>Dalam Pesanan</th>
                                                <th>Min Stok</th>
                                                <th>Rata-rata Terjual/bln</th>
                                                <th>Konversi</th>
                                                <th>Status</th>
                                                <th width="110">Qty Order</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                                    <button type="button" class="btn btn-primary" id="btnTambahkanPOUT">
                                        <i class="fa fa-check"></i> Tambahkan ke POUT
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('page-script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const csrfToken = '{{ csrf_token() }}';
        const routes = {
            autosaveHeader: '{{ route('outlet-purchases.autosave-header', $purchase) }}',
            autosaveItem: '{{ route('outlet-purchases.autosave-item', $purchase) }}',
            destroyItem: function (itemId) {
                return '{{ route('outlet-purchases.destroy-item', [$purchase, '__ID__']) }}'.replace('__ID__', itemId);
            },
            show: '{{ route('outlet-purchases.show', $purchase) }}',
            products: '{{ route('outlet-purchases.cek-barang', $purchase) }}',
        };

        // ---- Antrean simpan global ----
        // Server mengunci header (lockForUpdate) sehingga request autosave diproses satu per satu.
        // Browser juga mengantre: hanya 1 request simpan yang jalan dalam satu waktu (sama seperti PO),
        // supaya tidak numpuk lalu timeout menjadi "Gagal".
        let saveQueueTail = Promise.resolve();

        function queuedAjax(options) {
            const run = saveQueueTail.then(function () {
                return new Promise(function (resolve) {
                    $.ajax(options).always(function () { resolve(); });
                });
            });
            saveQueueTail = run.catch(function () {});
            return run;
        }

        function showIndicator(message, isError) {
            const $ind = $('#autosave-indicator');
            $ind.removeClass('text-danger text-success').addClass(isError ? 'text-danger' : 'text-success');
            $ind.text(message);
            clearTimeout(showIndicator._t);
            showIndicator._t = setTimeout(function () { $ind.text(''); }, 2500);
        }

        function formatRupiah(angka) {
            if (!angka) return '0';
            return Math.round(angka).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function setTotal(total) {
            $('#total').val(formatRupiah(total || 0));
        }

        // Dokumen sudah tidak bisa diedit (mis. sudah diterima dari tab lain): hentikan dan arahkan ke detail.
        let notEditableShown = false;
        function handleNotEditable(xhr) {
            if (xhr && xhr.responseJSON && xhr.responseJSON.code === 'not_editable') {
                if (!notEditableShown) {
                    notEditableShown = true;
                    alert(xhr.responseJSON.message);
                    window.location.href = routes.show;
                }
                return true;
            }
            return false;
        }

        function isTransientError(xhr) {
            return xhr.status === 0 || xhr.status === 408 || xhr.status === 429 || xhr.status >= 500;
        }

        // ---- Header autosave (supplier, tanggal, no. nota, catatan) ----
        // headerPending: true selama menunggu debounce ATAU selama request-nya berjalan.
        let headerTimeout;
        let headerPending = false;
        let headerRetryCount = 0;
        let headerRetryScheduled = false;

        function headerPayload() {
            return {
                supplier_id: $('#supplier_id').val() || '',
                purchase_date: $('#purchase_date').val() || '',
                invoice_number: $('#invoice_number').val() || '',
                notes: $('#notes').val() || '',
            };
        }

        function sendHeader() {
            queuedAjax({
                url: routes.autosaveHeader,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: headerPayload(),
                success: function () {
                    headerRetryCount = 0;
                    showIndicator('Tersimpan otomatis ✓');
                },
                error: function (xhr) {
                    if (handleNotEditable(xhr)) return;
                    if (isTransientError(xhr) && headerRetryCount < 3) {
                        headerRetryCount++;
                        headerRetryScheduled = true;
                        setTimeout(function () {
                            headerRetryScheduled = false;
                            autosaveHeader(0);
                        }, 800 * headerRetryCount);
                        return;
                    }
                    headerRetryCount = 0;
                    showIndicator('Gagal menyimpan', true);
                },
                complete: function () { headerPending = false; },
            });
        }

        function autosaveHeader(delay) {
            clearTimeout(headerTimeout);
            headerPending = true;
            headerTimeout = setTimeout(sendHeader, delay === undefined ? 500 : delay);
        }

        // ---- Produk per supplier ----
        // Dari endpoint Cek Barang toko: stok toko, Dalam Pesanan, min stok toko, dan harga awal (HPP terbaru outlet).
        let currentProducts = null;
        let productMap = {};
        let supplierRequest = null;
        let selectedSupplierId = $('#supplier_id').val() || null;

        function rowProductId($row) {
            const $sel = $row.find('.product');
            return String($sel.val() || $sel.data('current-product') || '');
        }

        function productCode(id) {
            const p = productMap[id];
            return p && p.code ? String(p.code).trim() : '';
        }

        // Cari baris LAIN yang memakai produk yang sama (id sama atau kode/barcode sama)
        function findDuplicateRow(productId, $exceptRow) {
            productId = String(productId || '');
            if (!productId) return null;
            const code = productCode(productId);
            let $dup = null;

            $('#product-repeater tr').each(function () {
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

        // Nonaktifkan option produk yang sudah dipilih di baris lain
        function refreshProductOptions() {
            const idOwner = {};
            const codeOwner = {};

            $('#product-repeater .product').each(function () {
                const id = String($(this).val() || $(this).data('current-product') || '');
                if (!id) return;
                if (!idOwner[id]) idOwner[id] = this;
                const code = productCode(id);
                if (code && !codeOwner[code]) codeOwner[code] = this;
            });

            $('#product-repeater .product').each(function () {
                const sel = this;
                $(sel).find('option').each(function () {
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

        function flagDuplicateRows() {
            $('#product-repeater tr').each(function () {
                const $row = $(this);
                const id = rowProductId($row);
                if (id && findDuplicateRow(id, $row)) markDuplicate($row);
            });
        }

        function konversiDisplay(qty, konversiQty, satuanBesar, satuan) {
            satuan = satuan || 'PCS';
            qty = parseInt(qty) || 0;
            if (!konversiQty || !satuanBesar) return null;
            const boxes = Math.floor(qty / konversiQty);
            const rem = qty % konversiQty;
            if (rem === 0) return boxes + ' ' + satuanBesar;
            if (boxes > 0) return boxes + ' ' + satuanBesar + ' ' + rem + ' ' + satuan;
            return qty + ' ' + satuan;
        }

        function fmtKonversiRatio(p) {
            if (!p || !p.konversi_qty || !p.satuan_besar) return '<span class="text-muted">-</span>';
            return '1 ' + p.satuan_besar + ' = ' + p.konversi_qty + ' ' + (p.satuan || 'PCS');
        }

        function esc(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        function fmtQtyK(qty, p) {
            qty = Number(qty) || 0;
            if (!p) return qty;
            const k = konversiDisplay(qty, p.konversi_qty, p.satuan_besar, p.satuan);
            return qty + (k ? ' <span class="label label-info">' + esc(k) + '</span>' : '');
        }

        // Badge sumber min stok toko: auto (3 bulan penjualan di toko ini) | belum cukup data (min 0) | +adj
        function minBadge(p) {
            if (!p) return '';
            let h = p.min_source === 'auto'
                ? ' <span class="label label-primary" title="Hasil hitung penjualan 3 bulan di toko ini">auto</span>'
                : ' <span class="label label-default" title="Penjualan di toko ini belum cukup 3 bulan: min stok 0, tidak ditandai kurang">belum cukup data</span>';
            if (Number(p.min_adjustment_pct) > 0) {
                h += ' <span class="label label-warning" title="Adjustment aktif">+adj ' + Number(p.min_adjustment_pct) + '%</span>';
            }
            return h;
        }

        // Penanda selisih harga yang diketik vs harga awal (HPP terbaru outlet), supaya tidak perlu cek manual.
        function updateHargaDiff($row) {
            const $d = $row.find('.harga-diff');
            const id = rowProductId($row);
            const p = id ? productMap[id] : null;
            const base = p ? (Number(p.harga_beli) || 0) : 0;
            const cur = parseFloat(hargaOf($row)) || 0;

            if (!p || base <= 0 || cur === base) {
                $d.attr('class', 'harga-diff').text('');
                return;
            }
            const pct = (cur - base) / base * 100;
            const label = (pct > 0 ? 'naik ' : 'turun ') + Math.abs(pct).toFixed(1).replace(/\.0$/, '') + '% dari harga terakhir (' + formatRupiah(base) + ')';
            $d.attr('class', 'harga-diff ' + (pct > 0 ? 'text-danger' : 'text-success')).text(label);
        }

        // Produk yang sudah ada di POUT ini (id dan kode), untuk modal Cek Barang
        function getUsedProducts() {
            const ids = new Set();
            const codes = new Set();
            $('#product-repeater tr').each(function () {
                const id = rowProductId($(this));
                if (!id) return;
                ids.add(id);
                const code = productCode(id);
                if (code) codes.add(code);
            });
            return { ids: ids, codes: codes };
        }

        function updateKonversiDisplay($row) {
            updateHargaDiff($row);
            if (!currentProducts) return;
            const productId = $row.find('.product').val();
            const qty = parseInt($row.find('.qty').val()) || 0;
            const prod = currentProducts.find(function (p) { return p.id == productId; });
            const k = prod ? konversiDisplay(qty, prod.konversi_qty, prod.satuan_besar, prod.satuan) : null;
            $row.find('.konversi-display').html(k ? '<span class="label label-info">' + k + '</span>' : '');

            const $ratio = $row.find('.konversi-ratio');
            if (prod && prod.konversi_qty && prod.satuan_besar) {
                $ratio.removeClass('text-muted').html(fmtKonversiRatio(prod));
            } else {
                $ratio.addClass('text-muted').text('-');
            }
        }

        function hargaOf($row) {
            const $in = $row.find('.harga_beli');
            return ($in.data('mask') !== undefined) ? ($in.cleanVal() || 0) : (parseFloat($in.val()) || 0);
        }

        function updateRowSubtotal($row) {
            const qty = parseFloat($row.find('.qty').val()) || 0;
            const subtotal = qty * (parseFloat(hargaOf($row)) || 0);
            $row.find('.subtotal').val(formatRupiah(subtotal));
            return subtotal;
        }

        function buildProductRow() {
            return `
                <tr>
                    <td>
                        <select class="form-control select2 product" data-placeholder="Pilih Product" style="width:100%;">
                            <option value="" disabled selected>Pilih Produk</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" value="1" min="1" step="1" class="form-control qty">
                        <span class="konversi-display"></span>
                    </td>
                    <td class="konversi-ratio text-center text-muted">-</td>
                    <td>
                        <input type="text" value="0" class="form-control harga_beli numeral-mask">
                        <small class="harga-diff"></small>
                    </td>
                    <td><input type="text" class="form-control subtotal" readonly></td>
                    <td class="text-center row-status"><span class="label label-default">Belum tersimpan</span></td>
                    <td><button class="btn btn-sm btn-danger remove-row" type="button">Remove</button></td>
                </tr>`;
        }

        // Isi select produk. Produk baris yang sudah tersimpan tetap muncul walau tidak ada di daftar supplier saat ini.
        function populateProductSelects(products, target) {
            $(target || '.product').each(function () {
                const $select = $(this);
                const currentProductId = $select.data('current-product') || $select.val();
                const currentLabel = $select.data('current-label');

                $select.empty().append('<option value="" disabled selected>Pilih Produk</option>');
                $.each(products, function (i, product) {
                    const stockText = product.stock_count ? ' [' + product.stock_count + ']' : '';
                    $select.append($('<option>', {
                        value: product.id,
                        text: product.code + ' ' + product.name + stockText,
                        'data-harga': product.harga_beli || 0,
                    }));
                });

                if (currentProductId && !products.some(function (p) { return p.id == currentProductId; })) {
                    $select.append($('<option>', { value: currentProductId, text: currentLabel || ('Produk #' + currentProductId) }));
                }
                if (currentProductId) $select.val(currentProductId);

                $select.trigger('change.select2');
            });
        }

        function initializeProductRow($row) {
            $row.find('.numeral-mask').mask('#,##0', { reverse: true });
            $row.find('.select2').select2();
            $row.data('prev-product', String($row.find('.product').data('current-product') || ''));

            if (currentProducts) {
                populateProductSelects(currentProducts, $row.find('.product'));
            } else if ($row.find('.product').data('current-product')) {
                populateProductSelects([], $row.find('.product'));
            }

            updateKonversiDisplay($row);
            updateRowSubtotal($row);
        }

        function addBlankRow() {
            $('#product-repeater').append(buildProductRow());
            initializeProductRow($('#product-repeater tr:last'));
            refreshProductOptions();
        }

        function resetProductRowsForSupplierChange() {
            // Batalkan autosave yang masih menunggu / berjalan untuk baris-baris lama
            $('#product-repeater tr').each(function () {
                clearTimeout($(this).data('debounce'));
                $(this).data('removed', true);
            });
            $('#product-repeater').empty();
            productMap = {};
            setTotal(0);
        }

        function loadProductsForSupplier(supplierId) {
            currentProducts = [];
            productMap = {};
            populateProductSelects([]);

            if (!supplierId) return;

            if (supplierRequest) {
                supplierRequest.abort();
                supplierRequest = null;
            }

            supplierRequest = $.get(routes.products, { supplier_id: supplierId })
                .done(function (products) {
                    if (String($('#supplier_id').val() || '') !== String(supplierId)) return;

                    currentProducts = products;
                    productMap = {};
                    products.forEach(function (p) { productMap[p.id] = p; });
                    populateProductSelects(products);

                    $('#product-repeater tr').each(function () { updateKonversiDisplay($(this)); });

                    refreshProductOptions();
                    flagDuplicateRows();
                })
                .fail(function (xhr) {
                    if (xhr.statusText !== 'abort') alert('Gagal memuat daftar produk supplier. Silakan refresh halaman.');
                })
                .always(function () { supplierRequest = null; });
        }

        $(document).ready(function () {
            $('#supplier_id').select2();
            loadProductsForSupplier($('#supplier_id').val());

            $('#product-repeater tr').each(function () { initializeProductRow($(this)); });
            if ($('#product-repeater tr').length === 0) addBlankRow();

            flagDuplicateRows();
        });

        // Ganti supplier: item dikosongkan di layar dan di server (via autosave header).
        // Bila sudah ada produk yang diinput, minta konfirmasi (SweetAlert) dulu.
        function applySupplierChange(nextSupplierId) {
            if (String(selectedSupplierId || '') !== String(nextSupplierId || '')) {
                currentProducts = [];
                resetProductRowsForSupplierChange();
                addBlankRow();
            }

            selectedSupplierId = nextSupplierId;
            loadProductsForSupplier(selectedSupplierId);
            autosaveHeader(0);
        }

        $('#supplier_id').on('change', function () {
            const $select = $(this);
            const nextSupplierId = $select.val() || null;

            if (String(selectedSupplierId || '') === String(nextSupplierId || '')) {
                applySupplierChange(nextSupplierId);
                return;
            }

            const filledRows = $('#product-repeater tr').filter(function () {
                return !!(rowProductId($(this)) || $(this).data('item-id'));
            }).length;

            if (filledRows === 0) {
                applySupplierChange(nextSupplierId);
                return;
            }

            // Batal: kembalikan pilihan supplier tanpa memicu handler ini lagi (change.select2 bernamespace).
            const revert = function () {
                $select.val(selectedSupplierId || '').trigger('change.select2');
            };
            const message = 'Mengganti supplier akan MENGHAPUS ' + filledRows + ' produk yang sudah diinput dari daftar belanja langsung ini '
                + '(produk supplier lama tidak berlaku untuk supplier baru).';

            if (typeof Swal === 'undefined') {
                if (confirm(message + '\n\nLanjutkan ganti supplier?')) { applySupplierChange(nextSupplierId); } else { revert(); }
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: 'Ganti supplier?',
                text: message,
                showCancelButton: true,
                confirmButtonText: 'Ya, ganti dan hapus produk',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#d9534f',
                focusCancel: true,
            }).then(function (result) {
                if (result.isConfirmed) { applySupplierChange(nextSupplierId); } else { revert(); }
            });
        });

        $('#purchase_date').on('change', function () { autosaveHeader(); });
        $('#invoice_number, #notes').on('input', function () { autosaveHeader(900); });

        $('#add-row').on('click', addBlankRow);

        // ---- Item row autosave ----
        // Aturan: 1 baris = maksimal 1 request simpan yang berjalan. Perubahan saat request masih jalan
        // disimpan lagi SETELAH selesai (dengan item_id yang sudah ada), supaya 1 baris tidak tersimpan 2x.
        function deleteItemRequest(itemId, onDone) {
            queuedAjax({
                url: routes.destroyItem(itemId),
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: { _method: 'DELETE' },
                success: function (res) {
                    setTotal(res.total);
                    if (onDone) onDone(res);
                },
                error: function (xhr) {
                    if (handleNotEditable(xhr)) return;
                    // 404 = item sudah tidak ada (mis. ikut terhapus saat supplier diganti): anggap selesai.
                    if (xhr.status === 404) {
                        if (onDone) onDone({});
                        return;
                    }
                    showIndicator('Gagal menghapus item', true);
                },
            });
        }

        function autosaveRow($row) {
            clearTimeout($row.data('debounce'));
            $row.data('debouncePending', false);
            if ($row.data('removed') || !document.contains($row[0])) return;

            const productId = rowProductId($row);
            const qty = parseInt($row.find('.qty').val()) || 0;
            const hargaBeli = hargaOf($row);

            updateRowSubtotal($row);

            if (!productId || qty <= 0) return;

            if (findDuplicateRow(productId, $row)) {
                markDuplicate($row);
                showIndicator('Produk sudah ada di belanja langsung ini', true);
                return;
            }

            if ($row.data('saving')) {
                $row.data('dirty', true);
                return;
            }

            $row.data('saving', true);
            $row.data('dirty', false);
            $row.find('.row-status').html('<span class="label label-warning">Menyimpan...</span>');

            queuedAjax({
                url: routes.autosaveItem,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: {
                    id: $row.data('item-id') || null,
                    product_id: productId,
                    qty: qty,
                    harga_beli: hargaBeli,
                },
                success: function (res) {
                    // Baris sudah di-Remove user selagi request jalan -> hapus item yang baru terbentuk
                    if ($row.data('removed')) {
                        deleteItemRequest(res.item_id);
                        return;
                    }

                    $row.data('retryCount', 0);
                    $row.data('item-id', res.item_id);
                    $row.attr('data-item-id', res.item_id);
                    $row.find('.row-status').html('<span class="label label-success">Tersimpan</span>');
                    setTotal(res.total);
                    showIndicator('Item tersimpan ✓');
                },
                error: function (xhr) {
                    if ($row.data('removed')) return;
                    if (handleNotEditable(xhr)) return;

                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.code === 'duplicate') {
                        $row.data('retryCount', 0);
                        markDuplicate($row);
                        showIndicator(xhr.responseJSON.message || 'Produk sudah ada di belanja langsung ini', true);
                        return;
                    }

                    // Gagal sementara (koneksi putus, server sibuk/timeout): coba lagi otomatis beberapa kali
                    // sebelum ditandai "Gagal", supaya user tidak perlu klik Sync Ulang tiap saat.
                    const retryCount = $row.data('retryCount') || 0;
                    if (isTransientError(xhr) && retryCount < 3 && !$row.data('dirty')) {
                        $row.data('retryCount', retryCount + 1);
                        $row.data('retryScheduled', true);
                        $row.find('.row-status').html('<span class="label label-warning">Mencoba lagi...</span>');
                        setTimeout(function () {
                            $row.data('retryScheduled', false);
                            if (!$row.data('removed') && document.contains($row[0])) autosaveRow($row);
                        }, 800 * (retryCount + 1));
                        return;
                    }

                    $row.data('retryCount', 0);
                    const msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menyimpan item';
                    $row.find('.row-status').html('<span class="label label-danger">Gagal</span>');
                    showIndicator(msg, true);
                },
                complete: function () {
                    $row.data('saving', false);
                    if ($row.data('dirty') && !$row.data('removed')) {
                        $row.data('dirty', false);
                        autosaveRow($row);
                    }
                },
            });
        }

        $(document).on('change', '.product', function () {
            const $select = $(this);
            const $row = $select.closest('tr');
            const productId = $select.val();

            // Tolak kalau produk (id / kode) sudah dipilih di baris lain
            if (productId) {
                const $dup = findDuplicateRow(productId, $row);
                if ($dup) {
                    const prev = $row.data('prev-product') || '';
                    const name = (productMap[productId] && productMap[productId].name) || 'Produk ini';
                    alert(name + ' sudah ada di belanja langsung ini. Satu produk hanya boleh dipilih satu kali.');
                    $select.val(prev).trigger('change.select2');
                    refreshProductOptions();
                    return;
                }
            }

            $row.data('prev-product', productId || '');
            $select.data('current-product', productId || '');

            if (!$row.find('.qty').val() || $row.find('.qty').val() == 0) $row.find('.qty').val(1);

            if (productId) {
                const hargaFromOption = $select.find('option:selected').data('harga');
                $row.find('.harga_beli').val(hargaFromOption || 0).trigger('input');
            }

            updateKonversiDisplay($row);
            refreshProductOptions();
            autosaveRow($row);   // membatalkan debounce dari trigger('input') di atas
        });

        // Debounce PER BARIS
        $(document).on('input', '.qty, .harga_beli', function () {
            const $row = $(this).closest('tr');
            updateKonversiDisplay($row);
            updateRowSubtotal($row);
            clearTimeout($row.data('debounce'));
            $row.data('debouncePending', true);
            $row.data('debounce', setTimeout(function () { autosaveRow($row); }, 600));
        });

        $(document).on('click', '.remove-row', function () {
            const $row = $(this).closest('tr');
            const itemId = $row.data('item-id');

            const finish = function () {
                $row.remove();
                if ($('#product-repeater tr').length === 0) addBlankRow();
                refreshProductOptions();
            };

            clearTimeout($row.data('debounce'));
            $row.data('debouncePending', false);

            if (itemId) {
                deleteItemRequest(itemId, function () {
                    finish();
                    showIndicator('Item dihapus ✓');
                });
            } else {
                // Belum punya item_id, tapi mungkin request simpan pertamanya masih jalan
                if ($row.data('saving')) $row.data('removed', true);
                finish();
            }
        });

        $('.numeral-mask').mask('#,##0', { reverse: true });

        // ---- Cek Barang (modal) ----
        // Data dari endpoint Cek Barang toko. Baris dirender malas (deferRender) dan pilihan/qty disimpan di cekState,
        // jadi ribuan produk tetap ringan dan pilihan tidak hilang saat pindah halaman / mencari.
        let cekBarangTable = null;
        let cekData = [];
        let cekState = {};

        function sanitizeQty(value) {
            let v = String(value).replace(/[^0-9]/g, '');
            if (v.length > 1 && v.startsWith('0')) v = parseInt(v, 10).toString();
            return v;
        }

        function cekQtyKonversi(qty, p) {
            const k = konversiDisplay(parseInt(qty) || 0, p.konversi_qty, p.satuan_besar, p.satuan);
            return k ? '<span class="label label-info">' + esc(k) + '</span>' : '';
        }

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

            const used = getUsedProducts();
            cekData = currentProducts.map(function (p) {
                const code = p.code ? String(p.code).trim() : '';
                const inPurchase = used.ids.has(String(p.id)) || !!(code && used.codes.has(code));
                return Object.assign({}, p, { _used: inPurchase });
            }).sort(function (a, b) {
                const aUnder = a.is_under_minimum ? 0 : 1;
                const bUnder = b.is_under_minimum ? 0 : 1;
                if (aUnder !== bUnder) return aUnder - bUnder;
                return a.stock_count - b.stock_count;
            });

            cekState = {};
            cekData.forEach(function (p) {
                // Qty otomatis hanya untuk baris OUT OF STOCK (sudah memperhitungkan Dalam Pesanan di server).
                cekState[p.id] = { checked: false, qty: (p._used || !p.is_under_minimum) ? 0 : (p.suggested_qty || 0) };
            });
            $('#checkAll').prop('checked', false);

            if (cekBarangTable) {
                cekBarangTable.destroy();
                $('#tableCekBarang tbody').empty();
            }

            cekBarangTable = $('#tableCekBarang').DataTable({
                destroy: true,
                data: cekData,
                deferRender: true,
                pageLength: 10,
                order: [],
                columns: [
                    { data: null, orderable: false, className: 'text-center', render: function (d, type, p) {
                        if (type !== 'display') return '';
                        return '<input type="checkbox" class="cek-product-check" value="' + p.id + '"'
                            + (cekState[p.id].checked ? ' checked' : '') + (p._used ? ' disabled' : '') + '>';
                    } },
                    { data: 'code', render: function (d, type) { return type === 'display' ? esc(d) : (d || ''); } },
                    { data: 'name', render: function (d, type) { return type === 'display' ? esc(d) : (d || ''); } },
                    { data: 'stock_count', className: 'text-center', render: function (d, type, p) { return type === 'display' ? fmtQtyK(d, p) : d; } },
                    { data: 'on_order', className: 'text-center', render: function (d, type, p) { return type === 'display' ? fmtQtyK(d, p) : d; } },
                    { data: 'effective_min', className: 'text-center', render: function (d, type, p) { return type === 'display' ? fmtQtyK(d, p) + minBadge(p) : d; } },
                    { data: 'average_sold', className: 'text-center', render: function (d, type) {
                        return type === 'display' ? Number(d).toLocaleString('id-ID', { maximumFractionDigits: 1 }) : d;
                    } },
                    { data: null, orderable: false, className: 'text-center', render: function (d, type, p) { return type === 'display' ? fmtKonversiRatio(p) : ''; } },
                    { data: null, className: 'text-center', render: function (d, type, p) {
                        const label = p._used ? 'Sudah di POUT' : (p.is_under_minimum ? 'OUT OF STOCK' : 'Normal');
                        if (type !== 'display') return label;
                        const cls = p._used ? 'label-default' : (p.is_under_minimum ? 'label-danger' : 'label-success');
                        return '<span class="label ' + cls + '">' + label + '</span>'
                            + (!p._used && p.already_ordered ? ' <span class="label label-info" title="Stok + Dalam Pesanan sudah mencukupi min stok">Sudah dipesan</span>' : '');
                    } },
                    { data: null, orderable: false, render: function (d, type, p) {
                        if (type !== 'display') return '';
                        return '<input type="text" class="form-control input-sm cek-qty" style="width:80px" data-id="' + p.id + '"'
                            + ' value="' + cekState[p.id].qty + '"' + (p._used ? ' disabled' : '') + '>'
                            + '<span class="cek-qty-k">' + cekQtyKonversi(cekState[p.id].qty, p) + '</span>';
                    } },
                ],
                createdRow: function (row, p) {
                    if (p._used) $(row).addClass('text-muted');
                    else if (p.is_under_minimum) $(row).addClass('danger');
                },
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ baris',
                    info: 'Menampilkan _START_-_END_ dari _TOTAL_ produk',
                    paginate: { previous: 'Prev', next: 'Next' },
                    zeroRecords: 'Tidak ada produk ditemukan',
                },
            });
        });

        $(document).on('input', '#tableCekBarang .cek-qty', function () {
            const id = $(this).data('id');
            const st = cekState[id];
            if (!st) return;
            const v = sanitizeQty($(this).val());
            $(this).val(v);
            st.qty = parseInt(v) || 0;
            st.checked = st.qty > 0;
            const $tr = $(this).closest('tr');
            $tr.find('.cek-product-check').prop('checked', st.checked);
            const p = cekData.find(function (x) { return String(x.id) === String(id); });
            $tr.find('.cek-qty-k').html(p ? cekQtyKonversi(st.qty, p) : '');
        });

        $(document).on('blur', '#tableCekBarang .cek-qty', function () {
            if ($(this).val() === '') $(this).val(0);
        });

        $(document).on('change', '#tableCekBarang .cek-product-check', function () {
            const st = cekState[$(this).val()];
            if (st) st.checked = this.checked;
        });

        // Centang semua = semua baris hasil pencarian saat ini yang belum ada di POUT (lintas halaman tabel).
        $(document).on('change', '#checkAll', function () {
            if (!cekBarangTable) return;
            const checked = this.checked;
            cekBarangTable.rows({ search: 'applied' }).data().each(function (p) {
                if (!p._used) cekState[p.id].checked = checked;
            });
            $('#tableCekBarang .cek-product-check:not(:disabled)').prop('checked', checked);
        });

        // Sama seperti PO (K8): tiap baris ditambahkan lalu dikirim lewat autosaveRow yang sama; autosaveRow masuk
        // antrean global (queuedAjax) sehingga terkirim satu per satu, tanpa endpoint bulk.
        $('#btnTambahkanPOUT').on('click', function () {
            if (!cekBarangTable) {
                alert('Tabel produk belum siap.');
                return;
            }

            const selected = cekData.filter(function (p) {
                const st = cekState[p.id];
                return st && st.checked && st.qty > 0 && !p._used;
            });

            if (selected.length === 0) {
                alert('Pilih minimal satu produk.');
                return;
            }

            const $firstRow = $('#product-repeater tr:first');
            if ($firstRow.length && !rowProductId($firstRow) && !$firstRow.data('item-id')) {
                $firstRow.remove();
            }

            // Pastikan tidak ada produk dobel: terhadap isi POUT saat ini DAN di antara pilihan itu sendiri
            const used = getUsedProducts();
            const skipped = [];
            const toAdd = [];

            selected.forEach(function (p) {
                const code = productCode(p.id);
                if (used.ids.has(String(p.id)) || (code && used.codes.has(code))) {
                    skipped.push(p.name);
                    return;
                }
                used.ids.add(String(p.id));
                if (code) used.codes.add(code);
                toAdd.push(p);
            });

            if (skipped.length) {
                alert('Produk berikut dilewati karena sudah ada di belanja langsung ini:\n- ' + skipped.join('\n- '));
            }

            toAdd.forEach(function (p) {
                $('#product-repeater').append(buildProductRow());
                const $newRow = $('#product-repeater tr:last');
                initializeProductRow($newRow);

                const $productSelect = $newRow.find('.product');
                $productSelect.val(p.id).trigger('change.select2');
                $productSelect.data('current-product', String(p.id));
                $newRow.data('prev-product', String(p.id));
                $newRow.find('.harga_beli').val(p.harga_beli || 0).trigger('input');
                $newRow.find('.qty').val(cekState[p.id].qty);

                updateKonversiDisplay($newRow);
                autosaveRow($newRow);   // membatalkan debounce dari trigger('input') di atas
            });

            if ($('#product-repeater tr').length === 0) addBlankRow();
            refreshProductOptions();

            $('#modalCekBarang').modal('hide');
            $('#checkAll').prop('checked', false);
        });

        // ---- Pastikan semua autosave (header + tiap baris) selesai sebelum "Selesai" submit ----
        // "Selesai" adalah submit form biasa (pindah halaman). Request yang belum selesai akan dibatalkan
        // browser, jadi tombol menunggu semua autosave beres dulu.
        function isRowBusy($row) {
            return !!($row.data('debouncePending') || $row.data('saving') || $row.data('dirty') || $row.data('retryScheduled'));
        }

        function anyPendingAutosave() {
            if (headerPending || headerRetryScheduled) return true;
            let busy = false;
            $('#product-repeater tr').each(function () {
                if (isRowBusy($(this))) { busy = true; return false; }
            });
            return busy;
        }

        function anyRowFailed() {
            return $('#product-repeater .row-status .label-danger').length > 0;
        }

        // ---- Tombol "Sync Ulang Item Gagal" (atas & bawah) ----
        function syncFailedItems() {
            const $failedRows = $('#product-repeater tr').filter(function () {
                return $(this).find('.row-status .label-danger').length > 0;
            });

            if ($failedRows.length === 0) {
                showIndicator('Tidak ada item yang gagal ✓');
                return;
            }

            $('.btn-sync-failed').prop('disabled', true).each(function () {
                $(this).data('original-text', $(this).html());
            }).html('<i class="fa fa-refresh fa-spin"></i> Menyinkronkan...');

            $failedRows.each(function () {
                const $row = $(this);
                $row.data('retryCount', 0);
                autosaveRow($row);
            });

            const start = Date.now();
            const maxWaitMs = 20000;
            (function poll() {
                const stillBusy = $failedRows.toArray().some(function (el) { return isRowBusy($(el)); });

                if (!stillBusy || Date.now() - start > maxWaitMs) {
                    $('.btn-sync-failed').prop('disabled', false).each(function () {
                        $(this).html($(this).data('original-text'));
                    });

                    const stillFailed = $('#product-repeater .row-status .label-danger').length;
                    showIndicator(
                        stillFailed > 0 ? stillFailed + ' item masih gagal, coba Sync Ulang lagi' : 'Semua item berhasil disinkronkan ✓',
                        stillFailed > 0
                    );
                    return;
                }

                setTimeout(poll, 200);
            })();
        }

        $(document).on('click', '.btn-sync-failed', syncFailedItems);

        function flushPendingAutosaves(onSettled) {
            // Jalankan sekarang juga semua debounce yang masih menunggu, tanpa menunggu sisa waktunya.
            clearTimeout(headerTimeout);
            if (headerPending && !headerRetryScheduled) {
                sendHeader();
            }
            $('#product-repeater tr').each(function () {
                const $row = $(this);
                if ($row.data('debouncePending')) autosaveRow($row);
            });

            const start = Date.now();
            const maxWaitMs = 15000;
            (function poll() {
                if (!anyPendingAutosave()) { onSettled(); return; }
                if (Date.now() - start > maxWaitMs) { onSettled(true); return; }
                setTimeout(poll, 150);
            })();
        }

        $('#finish-form').on('submit', function (e) {
            if (!anyPendingAutosave() && !anyRowFailed()) {
                return; // tidak ada yang tertunda, submit seperti biasa
            }

            e.preventDefault();
            const $btn = $(this).find('button[type="submit"]');
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Menyimpan sisa perubahan...');

            flushPendingAutosaves(function (timedOut) {
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