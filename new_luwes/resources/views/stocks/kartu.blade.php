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
                                <label class="form-label">Pilih SKU</label>
                                <select id="selectStock" class="form-control select2">
                                    <option value="">-- Pilih SKU --</option>
                                    @foreach ($stocks as $stock)
                                        <option value="{{ $stock['id'] }}" data-sku="{{ $stock['sku'] }}"
                                            data-product="{{ $stock['product_name'] }}"
                                            data-supplier="{{ $stock['supplier'] }}">
                                            SKU: {{ $stock['sku'] }} - {{ $stock['product_name'] }}
                                            ({{ $stock['supplier'] }}) | {{ $stock['product_code'] }}
                                        </option>
                                    @endforeach
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

                        <!-- Info Stock -->
                        <table class="table table-borderless" style="width: 60%">
                            <tr>
                                <td style="width: 30%">SKU</td>
                                <td>: <span id="displaySku">-</span></td>
                            </tr>
                            <tr>
                                <td>Nama Produk</td>
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
                                        <td colspan="9" class="text-center">Pilih SKU untuk menampilkan data</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="7" class="text-right">TOTAL NILAI PERSEDIAAN</th>
                                        <th id="totalPersediaan">0</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
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

        $(document).ready(function() {
            let currentData = [];
            let stockMeta = {};

            // Initialize select2
            $('#selectStock').select2({
                placeholder: '-- Pilih SKU --',
                width: '100%'
            });

            // Enable button when stock selected
            $('#selectStock').on('change', function() {
                $('#btnLoadKartu').prop('disabled', !$(this).val());
            });

            // Load kartu data
            $('#btnLoadKartu').on('click', function() {
                const stockId = $('#selectStock').val();

                if (!stockId) return;

                $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');

                $.ajax({
                    url: '{{ route('stock.kartu.data') }}',
                    method: 'GET',
                    data: {
                        stock_id: stockId
                    },
                    success: function(response) {
                        currentData = response;
                        stockMeta = response.stock;

                        // Update info
                        $('#displaySku').text(response.stock.sku);
                        $('#displayProduct').text(response.stock.product_name);
                        $('#displayCode').text(response.stock.product_code);
                        $('#displaySupplier').text(response.stock.supplier);

                        // Render table
                        renderKartuTable(response.transactions, stockMeta);

                        $('#btnLoadKartu').prop('disabled', false).html(
                            '<i class="fa fa-search"></i> Tampilkan Kartu');

                        $('#btnExportKartu')
                            .attr('href', '{{ route('laporan.kartu-stok') }}/' + stockId)
                            .css({'pointer-events': 'auto', 'opacity': '1'});

                        $('#btnExportPdfKartu')
                        .attr('href', '{{ url('laporan/pdf/kartu-stok') }}/' + stockId)
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

                if (transactions.length === 0) {
                    tbody.append(
                        '<tr><td colspan="9" class="text-center">Tidak ada transaksi untuk SKU ini</td></tr>');
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

            function formatRupiah(amount) {
                return new Intl.NumberFormat('id-ID').format(amount);
            }
        });
    </script>
@endsection
