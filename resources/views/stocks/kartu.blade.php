@extends('layouts.master')
@section('title', 'Kartu Stok')
@section('container')
    <section class="content-header">
        <h1>Kartu Stok</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-sm-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><strong>KARTU STOK</strong></h3>
                    </div>
                    <div class="box-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Pilih Produk</label>
                                <select id="selectProduct" class="form-control" style="width: 100%">
                                    <option value="">-- Pilih Produk --</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button id="btnLoadKartu" class="btn btn-primary form-control" disabled>
                                    <i class="fa fa-search"></i> Tampilkan Kartu
                                </button>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <a id="btnExportKartu" href="#" class="btn btn-success form-control" style="pointer-events:none; opacity:0.6;">
                                    <i class="fa fa-file-excel-o"></i> Export Excel
                                </a>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <a id="btnExportPdfKartu" href="#" target="_blank" class="btn btn-danger form-control" style="pointer-events:none; opacity:0.6;">
                                    <i class="fa fa-file-pdf-o"></i> Export PDF
                                </a>
                            </div>
                        </div>

                        <!-- Info Product -->
                        <table class="table table-borderless" style="width: 60%">
                            <tr>
                                <td style="width: 30%">Nama Produk</td>
                                <td>: <span id="displayProduct">-</span></td>
                            </tr>
                            <tr>
                                <td>Barcode</td>
                                <td>: <span id="displayCode">-</span></td>
                            </tr>
                            <tr>
                                <td>Supplier</td>
                                <td>: <span id="displaySupplier">-</span></td>
                            </tr>
                        </table>

                        <!-- Transaction Table -->
                        <div class="table-responsive">
                            <table id="kartuTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Tanggal</th>
                                        <th>SKU</th>
                                        <th>Stok Awal</th>
                                        <th>Masuk</th>
                                        <th>Keluar</th>
                                        <th>Stok Akhir</th>
                                        <th>Harga Satuan (Rp)</th>
                                        <th>Nilai Persediaan</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <tr>
                                        <td colspan="10" class="text-center">Pilih Produk untuk menampilkan data</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr id="rowTotalStokProduk" style="display:none;">
                                        <th colspan="8" class="text-right">TOTAL STOK PRODUK (SEMUA SKU)</th>
                                        <th id="totalStokProduk">0</th>
                                        <th></th>
                                    </tr>
                                    <tr>
                                        <th colspan="8" class="text-right">TOTAL NILAI PERSEDIAAN</th>
                                        <th id="totalPersediaan">0</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <!-- Rincian stok per SKU untuk produk yang sama -->
                        <div id="productStockBreakdown" style="display:none;" class="mt-3">
                            <h5>Rincian Stok per SKU (Produk: <span id="breakdownProductName">-</span>)</h5>
                            <table class="table table-sm table-bordered" style="width: 60%">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Supplier</th>
                                        <th class="text-right">Qty Tersedia</th>
                                    </tr>
                                </thead>
                                <tbody id="breakdownBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('page-script')
    <script>
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

        function formatRupiah(amount) {
            return new Intl.NumberFormat('id-ID').format(amount);
        }

        $(document).ready(function() {
            let productMeta = {};

            // Initialize select2 - sekarang search Product, bukan SKU
            $('#selectProduct').select2({
                placeholder: '-- Pilih Produk --',
                width: '100%',
                minimumInputLength: 2,
                ajax: {
                    url: '{{ route('stocks.search') }}',
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        return {
                            q: params.term,
                            page: params.page || 1
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.results,
                            pagination: data.pagination
                        };
                    },
                    cache: true
                },
                language: {
                    inputTooShort: function () {
                        return 'Ketik minimal 2 huruf untuk mencari produk...';
                    },
                    searching: function () {
                        return 'Mencari...';
                    },
                    noResults: function () {
                        return 'Produk tidak ditemukan';
                    }
                }
            });

            // Enable button when product selected
            $('#selectProduct').on('change', function() {
                $('#btnLoadKartu').prop('disabled', !$(this).val());
            });

            // Load kartu data
            $('#btnLoadKartu').on('click', function() {
                const productId = $('#selectProduct').val();

                if (!productId) return;

                $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');

                $.ajax({
                    url: '{{ route('stock.kartu.data') }}',
                    method: 'GET',
                    data: {
                        product_id: productId
                    },
                    success: function(response) {
                        productMeta = response.product;

                        $('#displayProduct').text(response.product.name);
                        $('#displayCode').text(response.product.code);
                        $('#displaySupplier').text(response.product.suppliers);

                        renderKartuTable(response.transactions, productMeta);
                        renderProductStockSummary(response.product_summary, response.product, productMeta);

                        $('#btnLoadKartu').prop('disabled', false).html(
                            '<i class="fa fa-search"></i> Tampilkan Kartu');

                        $('#btnExportKartu')
                            .attr('href', '{{ route('laporan.kartu-stok') }}/' + productId)
                            .css({'pointer-events': 'auto', 'opacity': '1'});

                        $('#btnExportPdfKartu')
                            .attr('href', '{{ url('laporan/pdf/kartu-stok') }}/' + productId)
                            .css({'pointer-events': 'auto', 'opacity': '1'});
                    },
                    error: function() {
                        alert('Gagal memuat data kartu stok');
                        $('#btnLoadKartu').prop('disabled', false).html(
                            '<i class="fa fa-search"></i> Tampilkan Kartu');
                    }
                });
            });

            function renderKartuTable(transactions, meta) {
                meta = meta || {};
                const tbody = $('#tableBody');
                tbody.empty();

                if (!transactions || transactions.length === 0) {
                    tbody.append(
                        '<tr><td colspan="10" class="text-center">Tidak ada transaksi untuk produk ini</td></tr>');
                    $('#totalPersediaan').text('0');
                    return;
                }

                //TODO fix, use the Product's konversiDisplay instead
                function fmtQty(qty) {
                    var k = konversiDisplay(qty, meta.konversi_qty, meta.satuan_besar, meta.satuan);
                    return qty + (k ? ' <span class="label label-info">' + k + '</span>' : '');
                }

                let latestNilai = 0;

                transactions.forEach((item, index) => {
                    latestNilai = item.nilai;

                    tbody.append(`
                        <tr>
                            <td>${index + 1}</td>
                            <td>${item.tanggal}</td>
                            <td><span class="label label-default">${item.sku}</span></td>
                            <td class="text-right">${fmtQty(item.stok_awal)}</td>
                            <td class="text-right">${fmtQty(item.masuk)}</td>
                            <td class="text-right">${fmtQty(item.keluar)}</td>
                            <td class="text-right"><strong>${fmtQty(item.stok_akhir)}</strong></td>
                            <td class="text-right">${formatRupiah(item.harga)}</td>
                            <td class="text-right"><strong>${formatRupiah(item.nilai)}</strong></td>
                            <td><small>${item.keterangan}</small></td>
                        </tr>
                    `);
                });

                $('#totalPersediaan').text(formatRupiah(latestNilai));
            }

            function renderProductStockSummary(summary, product, meta) {
                if (!summary) {
                    $('#rowTotalStokProduk').hide();
                    $('#productStockBreakdown').hide();
                    return;
                }

                const totalDisplay = fmtQtyStandalone(summary.total_qty, meta);
                $('#totalStokProduk').html(totalDisplay);
                $('#rowTotalStokProduk').show();

                $('#breakdownProductName').text(product.name);
                const $body = $('#breakdownBody');
                $body.empty();

                if (!summary.breakdown || summary.breakdown.length === 0) {
                    $body.append('<tr><td colspan="3" class="text-center">Tidak ada data SKU</td></tr>');
                } else {
                    summary.breakdown.forEach(function (item) {
                        $body.append(`
                            <tr>
                                <td>${item.sku}</td>
                                <td>${item.supplier}</td>
                                <td class="text-right">${fmtQtyStandalone(item.qty_available, meta)}</td>
                            </tr>
                        `);
                    });
                }

                $('#productStockBreakdown').show();
            }

            // Helper terpisah karena fmtQty di dalam renderKartuTable pakai closure atas `meta` lokal
            function fmtQtyStandalone(qty, meta) {
                meta = meta || {};
                const k = konversiDisplay(qty, meta.konversi_qty, meta.satuan_besar, meta.satuan);
                return qty + (k ? ' <span class="label label-info">' + k + '</span>' : '');
            }
        });
    </script>
@endsection
