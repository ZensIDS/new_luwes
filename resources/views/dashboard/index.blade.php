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
        @if (($outletDeficitCount ?? null) !== null && $outletDeficitCount > 0)
        <div class="row">
            <div class="col-md-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3>{{ number_format($outletDeficitCount) }}</h3>
                        <p>Produk Stok Minus, Perlu Dicek</p>
                    </div>
                    <div class="icon"><i class="fa fa-minus-circle"></i></div>
                    <a href="{{ route('deficit-stocks.index') }}" class="small-box-footer">
                        Lihat Stok Minus <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>
        @endif
        @if (! empty($outletExpiry) && ($outletExpiry['expired'] > 0 || $outletExpiry['soon'] > 0))
        <div class="row">
            @if ($outletExpiry['expired'] > 0)
            <div class="col-md-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3>{{ number_format($outletExpiry['expired']) }}</h3>
                        <p>Batch Sudah Expired{{ $outletExpiry['combo'] > 0 ? ' ('.$outletExpiry['combo'].' produk juga minus, cek fisik)' : '' }}</p>
                    </div>
                    <div class="icon"><i class="fa fa-calendar-times-o"></i></div>
                    <a href="{{ route('expired-stocks.index') }}" class="small-box-footer">
                        Lihat Stok Expired <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            @endif
            @if ($outletExpiry['soon'] > 0)
            <div class="col-md-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3>{{ number_format($outletExpiry['soon']) }}</h3>
                        <p>Batch Akan Expired &le; {{ $outletExpiry['days'] }} Hari</p>
                    </div>
                    <div class="icon"><i class="fa fa-hourglass-half"></i></div>
                    <a href="{{ route('expired-stocks.index', ['tab' => 'soon']) }}" class="small-box-footer">
                        Lihat Stok Expired <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            @endif
        </div>
        @endif
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
                                    <th class="text-center">Stok Gudang</th>
                                    <th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($slowMovingProducts as $p)
                                    <tr>
                                        <td><small>{{ $p->code }}</small></td>
                                        <td>{{ $p->name }}</td>
                                        <td class="text-center">{{ (int)($p->stocks_sum_qty ?? 0) }}</td>
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
                                            <span class="label label-{{ $p->min_source === 'auto' ? 'primary' : 'default' }}">{{ $p->min_source }}</span>
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
                                    <th>Qty Gudang</th>
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
                                        <td class="text-center">{{ $stock->qty }}</td>
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
            @if(in_array(auth()->user()->role, ['admin-gudang', 'owner', 'superadmin']))
            <div class="col-xs-12" id="widgetDbSync">
                <div class="box box-default" id="dbSyncBox">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-database"></i>
                            Status Database Server Hosting
                            <span class="label label-default" id="dbSyncBadge">Memeriksa…</span>
                        </h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" id="dbSyncRefresh" title="Cek ulang">
                                <i class="fa fa-refresh"></i>
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <p id="dbSyncMessage" class="text-muted" style="margin-bottom:10px">Memeriksa koneksi ke database hosting…</p>
                        <table class="table table-condensed" style="margin-bottom:0">
                            <tbody>
                                <tr><th style="width:200px">Mode aplikasi ini</th><td id="dbSyncNode">—</td></tr>
                                <tr id="dbSyncServerRow"><th>Database hosting</th><td id="dbSyncServer">—</td></tr>
                                <tr id="dbSyncConnRow"><th>Koneksi</th><td id="dbSyncConn">—</td></tr>
                                <tr><th>Sinkron terakhir</th><td id="dbSyncLastRun">—</td></tr>
                                <tr><th>Sinkron berhasil terakhir</th><td id="dbSyncLastSuccess">—</td></tr>
                            </tbody>
                        </table>
                        <ul id="dbSyncErrors" class="text-danger small" style="display:none;margin:10px 0 0;padding-left:18px"></ul>

                        <div id="dbSyncConflicts" style="display:none;margin-top:12px">
                            <h4 style="font-size:15px;margin:0 0 6px">
                                <i class="fa fa-exclamation-triangle text-yellow"></i> Konflik dilewati
                                <small>data diubah berbeda di lokal & hosting. Pilih versi yang dipakai.</small>
                            </h4>
                            <div class="table-responsive" style="max-height:260px;overflow-y:auto">
                                <table class="table table-bordered table-condensed" style="margin-bottom:0">
                                    <thead>
                                        <tr><th>Tabel</th><th>ID</th><th>Perbedaan</th><th style="width:230px">Pakai versi</th></tr>
                                    </thead>
                                    <tbody id="dbSyncConflictRows"></tbody>
                                </table>
                            </div>
                        </div>

                        @if(in_array(auth()->user()->role, \App\Http\Controllers\DbSyncStatusController::MANAGER_ROLES, true))
                        <div id="dbSyncActions" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid #f4f4f4">
                            <div class="form-inline" style="margin-bottom:8px">
                                <div class="form-group" style="margin-right:10px">
                                    <label for="dbSyncTable" style="margin-right:6px">Tabel</label>
                                    <select id="dbSyncTable" class="form-control input-sm" style="min-width:220px">
                                        <option value="">Semua tabel</option>
                                    </select>
                                </div>
                                <div class="checkbox" style="margin-right:10px">
                                    <label title="Izinkan penghapusan massal yang ditahan pengaman (--force)">
                                        <input type="checkbox" id="dbSyncForce"> Izinkan hapus massal
                                    </label>
                                </div>
                            </div>
                            <div class="btn-group-sm">
                                <button type="button" class="btn btn-default btn-sm" id="dbSyncBtnStatus">
                                    <i class="fa fa-plug"></i> Cek Koneksi
                                </button>
                                <button type="button" class="btn btn-info btn-sm" id="dbSyncBtnDry">
                                    <i class="fa fa-search"></i> Lihat Rencana (dry-run)
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" id="dbSyncBtnRun">
                                    <i class="fa fa-refresh"></i> Sinkron Sekarang
                                </button>
                            </div>
                            <div id="dbSyncResult" style="display:none;margin-top:10px"></div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif
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

@if(!($isStaffOutletDashboard ?? false) && in_array(auth()->user()->role, ['admin-gudang', 'superadmin']))
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
                            <th>Min Default (manual)</th>
                            <th>Min Final (sekarang)</th>
                            <th>Min Efektif (tanggal)</th>
                        </tr>
                    </thead>
                    <tbody id="adjProductBody"></tbody>
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
            yAxis: { min: 0, title: { text: 'Qty Gudang' } },
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
                name: 'Stok Gudang',
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
        // Min Stock Adjustment Modal (hanya admin-gudang & superadmin).
        // Daftar produk dimuat lewat AJAX saat modal pertama kali dibuka.
        let adjTable = null;
        let adjLoading = false;

        function escHtml(v) {
            return $('<div>').text(v == null ? '' : v).html();
        }

        $('#modalMinStockAdj').on('shown.bs.modal', function () {
            if (adjTable || adjLoading) return;
            adjLoading = true;
            $('#adjProductBody').html('<tr><td colspan="7" class="text-center">Memuat data...</td></tr>');

            $.ajax({
                url: '{{ route("product.minimum-adjustment.products") }}',
                method: 'GET',
                success: function (res) {
                    let rows = '';
                    (res.data || []).forEach(function (p) {
                        const diff = p.effective_min > p.min_dasar
                            ? ' <span class="label label-info">+' + (p.effective_min - p.min_dasar) + '</span>' : '';
                        const src = p.min_source === 'auto'
                            ? ' <span class="label label-primary" title="Hasil hitung penjualan 3 bulan">auto</span>'
                            : ' <span class="label label-default" title="Default manual (belum 3 bulan data penjualan)">manual</span>';
                        const period = (p.active_from || p.active_until)
                            ? escHtml(p.active_from || '') + ' - ' + escHtml(p.active_until || '') : '';
                        rows += '<tr>' +
                            '<td class="text-center"><input type="checkbox" class="adj-product-check" value="' + p.id + '"></td>' +
                            '<td>' + escHtml(p.code) + '</td>' +
                            '<td>' + escHtml(p.name) + '</td>' +
                            '<td class="text-center">' + p.current_stock + '</td>' +
                            '<td class="text-center">' + p.min_stock + '</td>' +
                            '<td class="text-center">' + p.effective_min + diff + src + '</td>' +
                            '<td>' + period + '</td>' +
                            '</tr>';
                    });
                    $('#adjProductBody').html(rows);

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
                },
                error: function () {
                    $('#adjProductBody').html('<tr><td colspan="7" class="text-center text-danger">Gagal memuat data produk. Tutup lalu buka lagi modal ini.</td></tr>');
                },
                complete: function () {
                    adjLoading = false;
                }
            });
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
    @if(in_array(auth()->user()->role, ['admin-gudang', 'owner', 'superadmin']))
    <script>
        // Status koneksi & sinkron database hosting (lihat config/dbsync.php)
        (function () {
            const HEALTH = {
                ok: { box: 'box-success', label: 'label-success', text: 'Sehat · Terhubung' },
                warning: { box: 'box-warning', label: 'label-warning', text: 'Peringatan' },
                error: { box: 'box-danger', label: 'label-danger', text: 'Error' },
                unconfigured: { box: 'box-default', label: 'label-default', text: 'Belum dikonfigurasi' },
            };
            const STATUS = { success: 'Berhasil', partial: 'Sebagian gagal', failed: 'Gagal', running: 'Berjalan' };
            const esc = (s) => $('<div>').text(s ?? '').html();
            let canManage = false;
            let resolved = { run: null, keys: new Set() };   // konflik yang sudah diselesaikan sejak sinkron terakhir

            function render(s) {
                const h = HEALTH[s.health] || HEALTH.unconfigured;
                $('#dbSyncBox').removeClass('box-default box-success box-warning box-danger').addClass(h.box);
                $('#dbSyncBadge').attr('class', 'label ' + h.label).text(h.text);
                $('#dbSyncMessage').attr('class', s.health === 'ok' ? 'text-success' : (s.health === 'error' ? 'text-danger' : 'text-muted'))
                    .text(s.message);

                $('#dbSyncNode').text(s.node === 'local'
                    ? 'Server lokal (offline) — sinkron otomatis tiap ' + s.interval + ' menit'
                    : (s.node ? 'Hosting — menerima sinkron dari server lokal' : '—'));

                $('#dbSyncServerRow').toggle(!!s.server);
                $('#dbSyncConnRow').toggle(!!s.connection);
                if (s.server) {
                    $('#dbSyncServer').text(s.server.database + ' @ ' + s.server.host);
                }
                if (s.connection) {
                    $('#dbSyncConn').html(s.connection.ok
                        ? '<span class="text-success"><i class="fa fa-check-circle"></i> Terhubung</span> <small class="text-muted">('
                            + s.connection.latency_ms + ' ms, ' + esc(s.connection.version) + ')</small>'
                        : '<span class="text-danger"><i class="fa fa-times-circle"></i> Gagal terhubung</span><br><small class="text-danger">'
                            + esc(s.connection.error) + '</small>');
                }

                const run = s.last_run;
                $('#dbSyncLastRun').html(run
                    ? esc(STATUS[run.status] || run.status) + ' · ' + esc(run.started_human)
                        + ' · ' + run.rows_changed + ' baris berubah'
                        + (run.conflict_count ? ' · <span class="text-warning"><b>' + run.conflict_count + ' konflik dilewati</b></span>' : '')
                        + (run.error_count ? ' · <span class="text-danger">' + run.error_count + ' error</span>' : '')
                    : '<span class="text-muted">Belum pernah</span>');
                $('#dbSyncLastSuccess').text(s.last_success_human || '—');

                const errors = (run?.error ? [run.error] : []).concat(run?.errors || []);
                $('#dbSyncErrors').toggle(errors.length > 0)
                    .html(errors.map((e) => '<li>' + esc(e) + '</li>').join(''));

                canManage = !!s.can_manage;
                if (resolved.run !== run?.started_at) {
                    resolved = { run: run?.started_at, keys: new Set() };
                }
                renderConflicts((run?.conflicts || []).filter((c) => !resolved.keys.has(c.table + '#' + c.id)));
                $('#dbSyncActions').toggle(canManage);

                const $table = $('#dbSyncTable');
                if (canManage && $table.children().length <= 1) {
                    (s.tables || []).forEach((t) => $table.append($('<option>').val(t).text(t)));
                }
            }

            function renderConflicts(conflicts) {
                $('#dbSyncConflicts').toggle(conflicts.length > 0);
                $('#dbSyncConflictRows').html(conflicts.map((c) => {
                    const data = 'data-table="' + esc(c.table) + '" data-id="' + esc(String(c.id)) + '"';
                    const actions = canManage
                        ? '<button class="btn btn-xs btn-default dbsync-keep" data-keep="local" ' + data + '>Lokal</button> '
                            + '<button class="btn btn-xs btn-default dbsync-keep" data-keep="server" ' + data + '>Hosting</button> '
                            + '<button class="btn btn-xs btn-default dbsync-keep" data-keep="newest" ' + data + '>Terbaru</button>'
                        : '<small class="text-muted">Hubungi owner / superadmin</small>';

                    return '<tr><td>' + esc(c.table) + '</td><td>' + esc(String(c.id)) + '</td>'
                        + '<td><small>' + esc(c.detail) + '</small></td><td>' + actions + '</td></tr>';
                }).join(''));
            }

            const LABELS = {
                pulled: 'tarik', pushed: 'kirim', merged: 'gabung', deleted_local: 'hapus lokal',
                deleted_server: 'hapus hosting', baseline: 'disamakan', deferred: 'tunda', conflicts: 'konflik', failed: 'error',
            };

            function renderResult(r) {
                const rows = Object.entries(r.tables || {}).map(([table, st]) => {
                    const parts = Object.keys(LABELS).filter((k) => st[k]).map((k) => LABELS[k] + ' ' + st[k]);
                    const notes = (st.errors || []).concat(st.warnings || []);

                    return '<tr><td>' + esc(table) + '</td><td>' + esc(parts.join(' · ') || '—')
                        + (notes.length ? '<br><small class="text-danger">' + notes.map(esc).join('<br>') + '</small>' : '')
                        + '</td></tr>';
                });
                const cls = r.status === 'success' ? 'success' : (r.status === 'failed' ? 'danger' : 'warning');

                $('#dbSyncResult').show().html(
                    '<div class="alert alert-' + cls + '" style="margin-bottom:8px;padding:8px 12px">'
                    + (r.dry_run ? '<b>Rencana (dry-run, belum ada data yang diubah):</b> ' : '<b>Hasil sinkron:</b> ')
                    + esc(STATUS[r.status] || r.status) + ' · ' + r.rows_changed + ' baris '
                    + (r.dry_run ? 'akan berubah' : 'berubah') + ' · ' + (r.conflict_count || 0) + ' konflik · '
                    + r.error_count + ' error · ' + r.duration_ms + ' ms'
                    + (r.error ? '<br>' + esc(r.error) : '') + '</div>'
                    + (rows.length
                        ? '<div class="table-responsive" style="max-height:260px;overflow-y:auto"><table class="table table-condensed table-bordered" style="margin-bottom:0">'
                            + '<thead><tr><th>Tabel</th><th>Perubahan</th></tr></thead><tbody>' + rows.join('') + '</tbody></table></div>'
                        : '<small class="text-muted">Tidak ada perubahan — data lokal & hosting sudah sama.</small>')
                );
            }

            function busy(on, text) {
                $('#dbSyncActions button, .dbsync-keep').prop('disabled', on);
                if (on) {
                    $('#dbSyncResult').show().html('<p class="text-muted"><i class="fa fa-spinner fa-spin"></i> ' + esc(text) + '</p>');
                }
            }

            function post(url, data) {
                return $.ajax({ url, method: 'POST', dataType: 'json', data: Object.assign({ _token: @json(csrf_token()) }, data) });
            }

            const failMessage = (xhr) => xhr.responseJSON?.message || ('Gagal (HTTP ' + xhr.status + ').');

            function runSync(dryRun) {
                const table = $('#dbSyncTable').val();
                const force = $('#dbSyncForce').is(':checked');
                const scope = table ? 'tabel ' + table : 'SEMUA tabel';

                if (!dryRun && !confirm('Sinkron sekarang untuk ' + scope + (force ? ' DAN izinkan penghapusan massal' : '') + '?')) {
                    return;
                }

                busy(true, dryRun
                    ? 'Menghitung rencana sinkron…'
                    : 'Menyinkronkan… sinkron pertama bisa memakan beberapa menit, jangan tutup halaman ini.');

                post(@json(route('db-sync.run')), { dry_run: dryRun ? 1 : 0, force: force ? 1 : 0, table: table || '' })
                    .done((r) => { renderResult(r); if (!dryRun) load(); })
                    .fail((xhr) => $('#dbSyncResult').show().html('<div class="alert alert-danger" style="padding:8px 12px">' + esc(failMessage(xhr)) + '</div>'))
                    .always(() => busy(false));
            }

            $('#dbSyncBtnStatus').on('click', load);
            $('#dbSyncBtnDry').on('click', () => runSync(true));
            $('#dbSyncBtnRun').on('click', () => runSync(false));

            $(document).on('click', '.dbsync-keep', function () {
                const { table, id, keep } = $(this).data();
                const label = { local: 'versi LOKAL', server: 'versi HOSTING', newest: 'versi yang TERAKHIR diubah' }[keep];
                if (!confirm('Samakan ' + table + ' #' + id + ' di lokal & hosting memakai ' + label + '?')) {
                    return;
                }

                const $row = $(this).closest('tr');
                busy(true, 'Menyelesaikan konflik ' + table + ' #' + id + '…');
                post(@json(route('db-sync.resolve')), { table, id: String(id), keep })
                    .done((r) => {
                        resolved.keys.add(table + '#' + id);
                        $row.remove();
                        $('#dbSyncConflicts').toggle($('#dbSyncConflictRows tr').length > 0);
                        $('#dbSyncResult').show().html('<div class="alert alert-success" style="padding:8px 12px">' + esc(r.message) + '</div>');
                    })
                    .fail((xhr) => $('#dbSyncResult').show().html('<div class="alert alert-danger" style="padding:8px 12px">' + esc(failMessage(xhr)) + '</div>'))
                    .always(() => busy(false));
            });

            function load() {
                $('#dbSyncRefresh i').addClass('fa-spin');
                $.getJSON(@json(route('db-sync.status')))
                    .done(render)
                    .fail(() => render({ health: 'error', message: 'Gagal memuat status sinkronisasi.', node: null }))
                    .always(() => $('#dbSyncRefresh i').removeClass('fa-spin'));
            }

            $('#dbSyncRefresh').on('click', load);
            load();
            setInterval(load, 60000);
        })();
    </script>
    @endif
    @endif
@endsection