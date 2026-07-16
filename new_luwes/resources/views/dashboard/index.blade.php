@extends('layouts.master')

@section('title', 'Dashboard')

@section('container')
    <section class="content-header">
        <h1>
            Selamat Datang di Gudang, {{ ucfirst(auth()->user()->name) }}!
            @if(in_array(auth()->user()->role, ['admin-gudang', 'superadmin']))
            <small>
                <button type="button" class="btn btn-xs btn-warning" data-toggle="modal" data-target="#modalMinStockAdj">
                    <i class="fa fa-sliders"></i> Pengaturan Min Stok
                </button>
            </small>
            @endif
        </h1>
        <ol class="breadcrumb">
            <li class="active">Dashboard</li>
        </ol>
    </section>

    <section class="content">
        @if($isStaffOutletDashboard ?? false)
        <div class="row">
            <div class="col-md-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3>{{ number_format($outletRequestTotal ?? 0) }}</h3>
                        <p>Total Jumlah Data Outlet Minta Gudang</p>
                    </div>
                    <div class="icon"><i class="fa fa-list-alt"></i></div>
                    <a href="{{ route('request-orders.index') }}" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3>{{ number_format($outletRequestPending ?? 0) }}</h3>
                        <p>Total Outlet Minta Gudang Status Pending</p>
                    </div>
                    <div class="icon"><i class="fa fa-clock-o"></i></div>
                    <a href="{{ route('request-orders.index') }}" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>
        @else
        <!-- Baris 1: STAT CARDS -->
        <div class="row">
            <div class="col-md-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3>{{ number_format($totalStock) }}</h3>
                        <p>Total Stok Gudang</p>
                    </div>
                    <div class="icon"><i class="fa fa-cubes"></i></div>
                    <a href="{{ route('stock.index') }}" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3>{{ number_format($pendingOrdersCount) }}</h3>
                        <p>Pending Order</p>
                    </div>
                    <div class="icon"><i class="fa fa-clock-o"></i></div>
                    <a href="{{ route('request-orders.index') }}" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3>{{ number_format($deliveredCount) }}</h3>
                        <p>Order Terkirim</p>
                    </div>
                    <div class="icon"><i class="fa fa-truck"></i></div>
                    <a href="{{ route('delivery-orders.index') }}" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3>{{ number_format($lowStockCount) }}</h3>
                        <p>Stok Kurang</p>
                    </div>
                    <div class="icon"><i class="fa fa-warning"></i></div>
                    <a href="#widgetLowStock" class="small-box-footer">
                        Lihat Detail <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>
        <!-- END Baris 1 -->

        <!-- Baris 2: INVENTORY + PRODUK TERLARIS -->
        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-bar-chart"></i> Inventory (Top 5 Stok Terbanyak)</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div id="chartInventory" style="min-height:260px"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-star"></i> Produk Terlaris (Top 5 Terkirim ke Outlet)</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div id="chartTopProducts" style="min-height:260px"></div>
                    </div>
                </div>
            </div>
        </div>
        <!-- END Baris 2 -->

        <!-- Baris 3: PESANAN TERBARU + SLOW MOVING -->
        <div class="row">
            <div class="col-md-12">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list-alt"></i> Pesanan Terbaru</h3>
                        <div class="box-tools pull-right">
                            <a href="{{ route('request-orders.index') }}" class="btn btn-xs btn-default">Lihat Semua</a>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID Pesanan</th>
                                    <th>Outlet</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentOrders as $order)
                                    <tr>
                                        <td><strong>#{{ $order->code ?? $order->id }}</strong></td>
                                        <td>{{ $order->owner?->name ?? '—' }}</td>
                                        <td>
                                            @php
                                                $statusMap = [
                                                    'pending'  => ['label-warning', 'Pending'],
                                                    'approved' => ['label-success', 'Disetujui'],
                                                    'partial'  => ['label-info', 'Sebagian'],
                                                    'rejected' => ['label-danger', 'Ditolak'],
                                                ];
                                                [$cls, $lbl] = $statusMap[$order->status] ?? ['label-default', $order->status];
                                            @endphp
                                            <span class="label {{ $cls }}">{{ $lbl }}</span>
                                        </td>
                                        <td>{{ $order->created_at->format('d M Y') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Belum ada pesanan</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-12">
                <div class="box box-default" id="widgetSlowMoving">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-hourglass-half text-muted"></i> Produk Slow Moving</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Kode</th>
                                    <th>Produk</th>
                                    <th class="text-center">Stok Tersedia</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($slowMovingProducts as $p)
                                    <tr>
                                        <td><small>{{ $p->code }}</small></td>
                                        <td>{{ $p->name }}</td>
                                        <td class="text-center">{{ (int)($p->stocks_sum_qty_available ?? 0) }}</td>
                                        <td class="text-center"><span class="label label-default"><i class="fa fa-minus-circle"></i> Tidak Aktif</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted" style="padding:20px">Tidak ada produk slow moving</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer text-muted">
                        <small><i class="fa fa-info-circle"></i> Produk yang tidak ada pengiriman dalam 90 hari terakhir.</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-calendar-times-o"></i> Deadline PO Supplier
                            @if($urgentSuppliers->isNotEmpty())
                                <span class="badge bg-red">{{ $urgentSuppliers->count() }}</span>
                            @endif
                        </h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body" style="max-height:300px;overflow-y:auto">
                        @if($urgentSuppliers->isNotEmpty())
                            @foreach($urgentSuppliers as $s)
                                @php $days = \Carbon\Carbon::today()->diffInDays($s->next_deadline, false); @endphp
                                <div class="callout {{ $days === 0 ? 'callout-danger' : 'callout-warning' }}">
                                    <p>
                                        <strong>{{ $s->name }}</strong><br>
                                        <small>{{ $s->next_deadline->isoFormat('DD MMM YYYY') }}</small>
                                    </p>
                                    <div>
                                        @if($days === 0)
                                            <span class="label label-danger">HARI INI</span>
                                        @else
                                            <span class="label label-warning">H-{{ $days }}</span>
                                        @endif
                                        <a href="{{ route('pembelian.create', ['supplier_id' => $s->id]) }}" class="btn btn-xs btn-primary pull-right">
                                            <i class="fa fa-plus"></i> Buat PO
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-center text-muted" style="padding:30px">
                                <i class="fa fa-check-circle fa-3x"></i>
                                <p>Tidak ada deadline PO mendesak</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-6" id="widgetLowStock">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-exclamation-triangle"></i> Produk di Bawah Min Stok
                            <span class="badge bg-red">{{ $lowVelocityProducts->count() }}</span>
                        </h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding" style="max-height:300px;overflow-y:auto">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th class="text-center">Stok</th>
                                    <th class="text-center">Min</th>
                                    <th class="text-center">Defisit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lowVelocityProducts as $p)
                                    <tr class="{{ $p->current_stock === 0 ? 'danger' : 'warning' }}">
                                        <td>
                                            <strong>{{ $p->name }}</strong>
                                            <br><small class="text-muted">{{ $p->code }}</small>
                                        </td>
                                        <td class="text-center">{{ $p->current_stock }}</td>
                                        <td class="text-center">
                                            {{ $p->effective_min }}
                                            @if($p->adjustment_percentage > 0)
                                                <br><small class="text-info">+{{ $p->adjustment_percentage }}%</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <span class="label label-danger">-{{ $p->deficit }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-success" style="padding:20px"><i class="fa fa-check"></i> Semua stok mencukupi</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="box-footer text-muted">
                        <small><i class="fa fa-info-circle"></i> Produk dengan penyesuaian aktif menggunakan min stok efektif.</small>
                    </div>
                </div>
            </div>
            <div class="col-xs-12">
                <div class="box box-warning collapsed-box">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-clock-o"></i>
                            Stok Mendekati Expired
                            <span class="badge bg-red">{{ $nearExpiryStocks->count() }}</span>
                        </h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse">
                                <i class="fa fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="box-body table-responsive" style="max-height:300px;overflow-y:auto">
                        <table class="table table-bordered table-condensed table-hover">
                            <thead>
                                <tr>
                                    <th>Produk</th>
                                    <th>Kode</th>
                                    <th>Batch / SKU</th>
                                    <th>Qty Tersedia</th>
                                    <th>Tanggal Expired</th>
                                    <th>Sisa Hari</th>
                                </tr>
                            </thead>
                            <tbody>
                            @if($nearExpiryStocks->isNotEmpty())
                                @foreach($nearExpiryStocks as $stock)
                                    @php
                                        $daysLeft = (int) \Carbon\Carbon::today()->diffInDays($stock->expired_at, false);
                                    @endphp
                                    <tr class="{{ $daysLeft <= 7 ? 'danger' : ($daysLeft <= 14 ? 'warning' : '') }}">
                                        <td>{{ $stock->product?->name ?? '—' }}</td>
                                        <td>{{ $stock->product?->code ?? '—' }}</td>
                                        <td>{{ $stock->batch_number ?? $stock->sku ?? '—' }}</td>
                                        <td class="text-center">{{ $stock->qty_available }}</td>
                                        <td>{{ \Carbon\Carbon::parse($stock->expired_at)->format('d M Y') }}</td>
                                        <td class="text-center">
                                            @if($daysLeft <= 7)
                                                <span class="label label-danger">{{ $daysLeft }} hari</span>
                                            @elseif($daysLeft <= 14)
                                                <span class="label label-warning">{{ $daysLeft }} hari</span>
                                            @else
                                                <span class="label label-default">{{ $daysLeft }} hari</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Tidak ada stok mendekati expired</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6" style="display:none;">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-check-square-o"></i> PO Perlu ACC Owner</h3>
                        <div class="box-tools pull-right">
                            <span class="badge bg-blue">{{ $pendingOwnerApprovalCount ?? 0 }}</span>
                        </div>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Kode PO</th>
                                    <th>Supplier</th>
                                    <th>Tanggal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingOwnerApprovals as $po)
                                    <tr>
                                        <td><strong>{{ $po->code }}</strong></td>
                                        <td>{{ $po->supplier?->name ?? '—' }}</td>
                                        <td>{{ $po->created_at->format('d M Y') }}</td>
                                        <td>
                                            <a href="{{ $po->canBeEditedBy(auth()->user()) ? route('pembelian.edit', $po->id) : route('pembelian.show', $po->id) }}" class="btn btn-xs btn-primary">
                                                {{ $po->canBeEditedBy(auth()->user()) ? 'Buka PO' : 'Lihat PO' }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Tidak ada PO yang menunggu ACC owner</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <!-- END Baris 3 -->
        @endif

    </section>

@if(!($isStaffOutletDashboard ?? false))
<!-- STOCK ADJUSTMENT MODAL -->
<div class="modal fade" id="modalMinStockAdj" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <i class="fa fa-sliders"></i> Pengaturan Perubahan Minimal Stok Produk
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Persentase Kenaikan (%)</label>
                            <input type="number" class="form-control" id="adjPercentage"
                                min="1" max="500" placeholder="Contoh: 20 untuk +20%">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Aktif Dari</label>
                            <input type="date" class="form-control" id="adjActiveFrom"
                                value="{{ now()->toDateString() }}">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Aktif Sampai <small class="text-muted">(kosongkan = selamanya)</small></label>
                            <input type="date" class="form-control" id="adjActiveUntil">
                        </div>
                    </div>
                </div>
                <table id="tableAdjProducts" class="table table-bordered table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="adjCheckAll"></th>
                            <th>Kode</th>
                            <th>Nama Produk</th>
                            <th>Stok Saat Ini</th>
                            <th>Min Stok</th>
                            <th>Min Efektif (sekarang)</th>
                            <th>Min Efektif (tanggal)</th>
                        </tr>
                    </thead>
                    <tbody id="adjProductBody">
                        @foreach($adjustmentProducts as $p)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="adj-product-check" value="{{ $p->id }}">
                                </td>
                                <td>{{ $p->code }}</td>
                                <td>{{ $p->name }}</td>
                                <td class="text-center">{{ $p->current_stock }}</td>
                                <td class="text-center">{{ $p->min_stock }}</td>
                                <td class="text-center">
                                    {{ $p->effective_min }}
                                    @if($p->effective_min > $p->min_stock)
                                        <span class="label label-info">+{{ $p->effective_min - $p->min_stock }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $p->active_from?->format('d M Y') }} - {{ $p->active_until?->format('d M Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="btnSimpanAdj">
                    <i class="fa fa-save"></i> Simpan Adjustment
                </button>
            </div>
        </div>
    </div>
</div>
<!-- END STOCK ADJUSTMENT MODAL -->
@endif
@endsection

@section('page-script')
    @if(!($isStaffOutletDashboard ?? false))
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/10.3.3/highcharts.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/10.3.3/modules/exporting.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highcharts/10.3.3/modules/accessibility.min.js"></script>
    <script>
        // Inventory bar chart
        Highcharts.chart('chartInventory', {
            chart: { type: 'column', backgroundColor: 'transparent' },
            title: { text: null },
            credits: { enabled: false },
            xAxis: {
                categories: {!! json_encode($inventoryChart->map(fn($i) => $i->product?->name ?? '—')->toArray()) !!},
                labels: { style: { fontSize: '11px' } }
            },
            yAxis: { min: 0, title: { text: 'Qty Tersedia' } },
            plotOptions: {
                column: {
                    colorByPoint: true,
                    dataLabels: { enabled: true }
                }
            },
            tooltip: {
                formatter: function() {
                    return '<b>' + this.x + '</b><br/>Qty: <b>' + Highcharts.numberFormat(this.y, 0) + '</b>';
                }
            },
            series: [{
                name: 'Stok Tersedia',
                data: {!! json_encode($inventoryChart->map(fn($i) => (int)$i->total_qty)->toArray()) !!}
            }],
            legend: { enabled: false }
        });

        // Top products pie chart
        Highcharts.chart('chartTopProducts', {
            chart: { type: 'pie', backgroundColor: 'transparent' },
            title: { text: null },
            credits: { enabled: false },
            tooltip: {
                pointFormat: '{series.name}: <b>{point.y} unit</b> ({point.percentage:.1f}%)'
            },
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    innerSize: '40%',
                    dataLabels: {
                        enabled: true,
                        format: '<b>{point.name}</b>: {point.percentage:.1f}%',
                        style: { fontSize: '11px' }
                    }
                }
            },
            series: [{
                name: 'Unit Terkirim',
                colorByPoint: true,
                data: {!! json_encode(
                    $topProducts->map(fn($i) => [
                        'name' => $i->product?->name ?? '—',
                        'y'    => (int)$i->total_qty,
                    ])->toArray()
                ) !!}
            }]
        });
    </script>
    <script>
        // Min Stock Adjustment Modal
        let adjTable = null;

        $('#modalMinStockAdj').on('shown.bs.modal', function () {
            if (!adjTable) {
                adjTable = $('#tableAdjProducts').DataTable({
                    pageLength: 10,
                    order: [[2, 'asc']],
                    columnDefs: [{ orderable: false, targets: [0] }],
                    language: {
                        search: "Cari:",
                        lengthMenu: "Tampilkan _MENU_ baris",
                        info: "Menampilkan _START_-_END_ dari _TOTAL_ produk",
                        paginate: { previous: "Prev", next: "Next" },
                        zeroRecords: "Tidak ada produk"
                    }
                });
            }
        });

        $(document).on('change', '#adjCheckAll', function () {
            const checked = $(this).prop('checked');
            if (adjTable) {
                adjTable.rows().nodes().each(function (node) {
                    $(node).find('.adj-product-check').prop('checked', checked);
                });
            }
        });

        $('#btnSimpanAdj').on('click', function () {
            const productIds = [];
            if (adjTable) {
                adjTable.rows().nodes().each(function (node) {
                    const $cb = $(node).find('.adj-product-check:checked');
                    if ($cb.length) productIds.push($cb.val());
                });
            }

            const pct        = parseInt($('#adjPercentage').val()) || 0;
            const activeFrom = $('#adjActiveFrom').val();
            const activeUntil = $('#adjActiveUntil').val();

            if (productIds.length === 0) { alert('Pilih minimal satu produk.'); return; }
            if (pct < 1)                  { alert('Persentase kenaikan minimal 1%.'); return; }
            if (!activeFrom)              { alert('Isi tanggal aktif dari.'); return; }

            $('#btnSimpanAdj').prop('disabled', true).text('Menyimpan...');

            $.ajax({
                url: '{{ route("product.minimum-adjustment.store") }}',
                method: 'POST',
                data: {
                    _token:                '{{ csrf_token() }}',
                    product_ids:           productIds,
                    adjustment_percentage: pct,
                    active_from:           activeFrom,
                    active_until:          activeUntil || null,
                },
                success: function (res) {
                    alert(res.message);
                    $('#modalMinStockAdj').modal('hide');
                    location.reload();
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON?.message || 'Terjadi kesalahan.';
                    alert('Gagal: ' + msg);
                },
                complete: function () {
                    $('#btnSimpanAdj').prop('disabled', false).text('Simpan Adjustment');
                }
            });
        });
    </script>
    @endif
@endsection
