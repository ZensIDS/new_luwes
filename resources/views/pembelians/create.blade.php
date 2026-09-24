@extends('layouts.master')

@section('title', 'Tambah PO')

@section('container')
    <section class="content">
        <div class="row">
            <!-- left column -->
            <div class="col-md-12">
                <!-- general form elements -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tambah PO</h3>
                    </div><!-- /.box-header -->
                    <!-- form start -->
                    <form action="{{ route('pembelian.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="box-body">
                            {{--<div class="form-group">
                                <label for="">Kode PO</label>
                                <input type="text" class="form-control" name="code" value="{{ old('code', $code) }}"
                                    placeholder="Masukkan Kode PO">
                                @error('code')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>--}}
                            <div class="form-group">
                                <label>Supplier</label>
                                <select class="form-control select2" name="supplier_id" data-placeholder="Pilih Supplier"
                                    style="width: 100%;">
                                    <option value="" selected disabled>Pilih Supplier</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}"
                                            {{ old('supplier_id', request('supplier_id')) == $supplier->id ? 'selected' : '' }}>
                                            {{ $supplier->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('supplier_id')
                                    <div class="invalid-feedback text-danger">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>
                            <hr>
                            <table class="table table-bordered table-striped" id="example">
                                <thead>
                                    <tr>
                                        <td>Nama Product</td>
                                        <td>Qty</td>
                                        <td>Harga Beli</td>
                                        <td>Sub Total</td>
                                        <td>Aksi</td>
                                    </tr>
                                </thead>
                                <tbody id="product-repeater">
                                    <tr>
                                        <td>
                                            <select class="form-control select2 product" data-placeholder="Pilih Product"
                                                name="product[0][product_id]" required style="width:100%">
                                                <option value="" disabled selected>Pilih Produk</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="form-control qty" name="product[0][qty]" required
                                                value="1" min="1">
                                            <span class="konversi-display"></span>
                                        </td>
                                        <td>
                                            <input type="text" class="form-control harga_beli numeral-mask"
                                                name="product[0][harga_beli]" required>
                                        </td>
                                        <td>
                                            <input class="form-control subtotal" name="product[0][subtotal]" required
                                                readonly>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-danger" onclick="removeBahanBaku(this)"
                                                type="button">Remove</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="d-flex gap-2 mb-2">
                                <button class="btn btn-sm btn-warning" type="button" data-toggle="modal" data-target="#modalCekBarang">
                                    <i class="fa fa-search"></i> Cek Barang
                                </button>
                                <button class="btn btn-sm btn-primary" onclick="addBahanBaku()" type="button">
                                    <i class="fa fa-plus"></i> Add Row
                                </button>
                            </div>
                            <hr>
                            <div class="form-group">
                                <label>Total</label>
                                <input type="text" required class="form-control" name="total" id="total" readonly>
                            </div>
                        </div><!-- /.box-body -->

                        <div class="box-footer">
                            <a href="{{ route('pembelian.index') }}" class="btn btn-default">Kembali</a>
                            <button type="submit" class="btn btn-primary">Simpan</button>
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
                </div><!-- /.box -->
            </div>
        </div>
    </section>
@endsection
@section('page-script')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    <script>
        let currentProducts = null;
        let productIndex = 0;
        let supplierRequest = null;
        let selectedSupplierId = $('[name="supplier_id"]').val() || null;

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

        function buildProductRow(index) {
            return `
                <tr>
                    <td>
                        <select required class="form-control select2 product" name="product[${index}][product_id]" data-placeholder="Pilih Product" style="width:100%;">
                            <option value="" disabled selected>Pilih Produk</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" required value="1" min="1" class="form-control qty" name="product[${index}][qty]">
                        <span class="konversi-display"></span>
                    </td>
                    <td><input required type="text" class="form-control harga_beli numeral-mask" name="product[${index}][harga_beli]"></td>
                    <td><input type="text" required class="form-control subtotal" name="product[${index}][subtotal]" readonly></td>
                    <td><button class="btn btn-sm btn-danger" onclick="removeBahanBaku(this)" type="button">Remove</button></td>
                </tr>`;
        }

        function initializeProductRow($row) {
            $row.find('.numeral-mask').mask("#,##0", { reverse: true });
            $row.find('.select2').select2();

            if (currentProducts) {
                populateProductSelects(currentProducts, $row.find('.product'));
            }

            updateSubtotalAndTotal();
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
            productIndex = 0;
            $('#product-repeater').html(buildProductRow(0));
            initializeProductRow($('#product-repeater tr:first'));
        }


        function populateProductSelects(products, target = '.product') {
            $(target).each(function() {
                let $select = $(this);
                let currentVal = $select.val(); // preserve selected value if still valid

                $select.empty().append('<option value="" disabled selected>Pilih Produk</option>');
                $.each(products, function(i, product) {
                    // Include stock count if your API returns it; otherwise omit.
                    let stockText = product.stock_count ? ' [' + product.stock_count + ']' : '';
                    $select.append($('<option>', {
                        value: product.id,
                        text: product.code + ' ' + product.name + stockText,
                        'data-serialized': product.is_serialized ? 1 : 0
                    }));
                });

                // Try to reselect previous value if it exists in new options
                if (currentVal && products.some(p => p.id == currentVal)) {
                    $select.val(currentVal);
                }

                // Refresh Select2
                $select.trigger('change.select2');
            });
        }

        // Helper: format number with thousand separators (Indonesian style)
        function formatRupiah(angka) {
            if (!angka) return '0';
            return angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function addBahanBaku() {
            productIndex++;
            $('#product-repeater').append(buildProductRow(productIndex));
            initializeProductRow($('#product-repeater tr:last'));
        }

        $(document).on('change', '.qty, .harga_beli', function() {
            updateSubtotalAndTotal();
        });

        // Handle serial number input changes
        $(document).on('input', '.serial-numbers', function() {
            let serialText = $(this).val();
            let serialLines = serialText.split('\n').filter(line => line.trim() !== '');
            let qtyInput = $(this).closest('tr').find('.qty');
            let isProductSerialized = $(this).closest('tr').find('.product option:selected').data('serialized');

            if (isProductSerialized) {
                qtyInput.val(serialLines.length);
                updateSubtotalAndTotal();
            }
        });

        function updateSubtotalAndTotal() {
            let total = 0;
            $('#product-repeater tr').each(function() {
                let $row = $(this);
                let qty = $row.find('.qty').val();
                let $hargaInput = $row.find('.harga_beli');
                // Use cleanVal() only when mask is initialized (has data from plugin)
                let harga_beli = ($hargaInput.data('mask') !== undefined)
                    ? ($hargaInput.cleanVal() || 0)
                    : (parseFloat($hargaInput.val()) || 0);
                let subtotal = (qty || 0) * harga_beli;
                // Set formatted subtotal (readonly)
                $row.find('.subtotal').val(formatRupiah(subtotal));
                total += subtotal;
            });
            // Set formatted total
            $('#total').val(formatRupiah(total));
        }

        $('.numeral-mask').mask("#,##0", {
            reverse: true
        });
        updateSubtotalAndTotal();

        function removeBahanBaku(button) {
            if ($('#example tbody tr').length > 1) {
                $(button).closest('tr').remove();
                updateSubtotalAndTotal();
            }
        }

        function updateKonversiDisplay($row) {
            if (!currentProducts) return;
            let productId = $row.find('.product').val();
            let qty = parseInt($row.find('.qty').val()) || 0;
            let prod = currentProducts.find(function(p) { return p.id == productId; });
            let k = prod ? konversiDisplay(qty, prod.konversi_qty, prod.satuan_besar, prod.satuan) : null;
            $row.find('.konversi-display').html(k ? '<span class="label label-info">' + k + '</span>' : '');
        }

        $(document).on('change', '.product', function() {
            let $row = $(this).closest('tr');
            let harga_beli = $row.find('.harga_beli');
            let product_id = $(this).val();
            let isProductSerialized = $(this).find('option:selected').data('serialized');
            let serialContainer = $row.find('.serial-container');
            let noSerialMessage = $row.find('.no-serial-message');
            let qtyInput = $row.find('.qty');

            if (isProductSerialized) {
                serialContainer.show();
                noSerialMessage.hide();
                qtyInput.prop('readonly', true);
                qtyInput.val(1);
            } else {
                serialContainer.hide();
                noSerialMessage.show();
                qtyInput.prop('readonly', false);
                qtyInput.val(1);
            }

            updateKonversiDisplay($row);

            $.get('/product/' + product_id, function(data) {
                // Set raw value and trigger input to apply mask formatting
                harga_beli.val(data.harga_beli).trigger('input');
                updateSubtotalAndTotal();
            });
        });

        $(document).on('change input', '.qty', function() {
            updateKonversiDisplay($(this).closest('tr'));
        });

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
                    if (String($('[name="supplier_id"]').val() || '') !== String(supplierId)) {
                        return;
                    }

                    currentProducts = products;
                    populateProductSelects(products);

                    $('.product').each(function() {
                        $(this).trigger('change');
                    });
                })
                .fail(function() {
                    alert('Gagal memuat daftar produk supplier. Silakan refresh halaman.');
                })
                .always(function() {
                    supplierRequest = null;
                });
        }

        // Handle product change on page load for existing rows
        $(document).ready(function() {
            loadProductsForSupplier(selectedSupplierId);

            $('.harga_beli').each(function() {
                $(this).trigger('input');
            });
        });

        $('[name="supplier_id"]').on('change', function() {
            var nextSupplierId = $(this).val() || null;

            if (String(selectedSupplierId || '') !== String(nextSupplierId || '')) {
                currentProducts = [];
                resetProductRowsForSupplierChange();
            }

            selectedSupplierId = nextSupplierId;
            loadProductsForSupplier(selectedSupplierId);
        });

        $('#kas').prop('disabled', true);
        $('#outlet').on('change', function() {
            let outlet_id = $(this).val();
            $.get('/outlet/' + outlet_id + '/kas', function(data) {
                $('#kas').find('option').remove();
                let defaultOption = $('<option>').val('').text('Pilih Kas').prop('disabled', true).prop(
                    'selected', true);
                $('#kas').append(defaultOption);
                data.forEach(function(kas) {
                    let option = $('<option>').val(kas.id).text(kas.name);
                    $('#kas').append(option);
                });
                $('#kas').trigger('change.select2');
            });
            $('#kas').prop('disabled', false);
        });

        // ---- Cek Barang Modal ----
        let cekBarangTable = null;

        $('#modalCekBarang').on('show.bs.modal', function (e) {
            if (!$('[name="supplier_id"]').val()) {
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
                const suggestedQty = Math.max(1, (p.effective_min || p.min_stock || 0) - (p.stock_count || 0));

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
            if ($firstRow.find('.product').val() === null || $firstRow.find('.product').val() === '') {
                $firstRow.remove();
            }

            selected.forEach(function (item) {
                addBahanBaku();

                const $newRow = $('#product-repeater tr:last');
                const $productSelect = $newRow.find('.product');
                const $hargaInput = $newRow.find('.harga_beli');
                const $qtyInput = $newRow.find('.qty');

                $productSelect.val(item.product_id).trigger('change.select2');
                $hargaInput.val(item.harga).trigger('input');
                $qtyInput.val(item.qty);

                updateSubtotalAndTotal();
            });

            $('#modalCekBarang').modal('hide');

            // Reset semua checkbox setelah tambahkan
            cekBarangTable.rows().nodes().each(function (node) {
                $(node).find('.cek-product-check').prop('checked', false);
            });
            $('#checkAll').prop('checked', false);
        });
    </script>
@endsection
