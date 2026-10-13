@extends('layouts.master')

@section('title', 'Stok Expired')

@section('container')
    <style>
        .expired-filter-bar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px; }
        .expired-filter { margin:0; min-width:180px; }
        .expired-filter label { display:block; margin-bottom:4px; font-size:12px; color:#666; }
        .expired-table th, .expired-table td { vertical-align:middle !important; }
        .expired-table .btn { margin:1px 0; }
        .expired-late { color:#dd4b39; font-weight:700; }
        .expired-soon { color:#c87f0a; font-weight:700; }
        .expired-combo { margin-top:4px; padding:2px 6px; font-size:12px; color:#8a6d3b; background:#fcf8e3; border:1px solid #faebcc; border-radius:3px; display:inline-block; }
        @media (max-width:767px) { .expired-filter, .expired-filter .form-control { width:100% !important; } }
    </style>

    <section class="content-header">
        <h1>Stok Expired <small>Batch yang sudah / akan expired</small></h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="callout callout-info">
                    <p>
                        Batch expired <b>tidak dijual</b> lewat kasir, tetapi tetap tercatat di halaman Stock dan Kartu Stock.
                        Setelah dicek fisik, pilih <b>Musnahkan</b> (saldo menjadi 0, alasan wajib, tercatat di Kartu Stock)
                        atau buat <b>Retur Supplier</b>. Ambang "akan expired" saat ini <b>{{ $summary['days'] }} hari</b>
                        (diatur di halaman Setting oleh owner / superadmin).
                    </p>
                </div>

                @if ($summary['combo'] > 0)
                    <div class="alert alert-warning">
                        <i class="fa fa-exclamation-triangle"></i>
                        <b>{{ $summary['combo'] }}</b> produk punya batch expired berstok <b>dan</b> stok minus.
                        Kemungkinan barang expired sudah dibuang, atau barang baru belum tercatat. Cek fisik.
                        <a href="{{ route('deficit-stocks.index', array_filter(['outlet_id' => $selectedOutletId])) }}" class="alert-link">Lihat Stok Minus</a>
                    </div>
                @endif

                <div class="box">
                    <div class="box-header">
                        <ul class="nav nav-tabs" style="margin-bottom:12px;">
                            <li class="{{ $tab === 'expired' ? 'active' : '' }}">
                                <a href="{{ route('expired-stocks.index', array_filter(['tab' => 'expired', 'outlet_id' => $selectedOutletId, 'q' => $search])) }}">
                                    Sudah Expired <span class="label label-danger">{{ $summary['expired'] }}</span>
                                </a>
                            </li>
                            <li class="{{ $tab === 'soon' ? 'active' : '' }}">
                                <a href="{{ route('expired-stocks.index', array_filter(['tab' => 'soon', 'outlet_id' => $selectedOutletId, 'q' => $search])) }}">
                                    Akan Expired &le; {{ $summary['days'] }} hari <span class="label label-warning">{{ $summary['soon'] }}</span>
                                </a>
                            </li>
                        </ul>

                        <form method="GET" action="{{ route('expired-stocks.index') }}" class="expired-filter-bar">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            @if ($canChooseOutlet)
                                <div class="expired-filter">
                                    <label for="outlet_id">Outlet</label>
                                    <select id="outlet_id" name="outlet_id" class="form-control input-sm" onchange="this.form.submit()">
                                        <option value="">Semua outlet</option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}" {{ (int) $selectedOutletId === (int) $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="expired-filter">
                                <label for="q">Cari produk / batch</label>
                                <input id="q" type="text" name="q" value="{{ $search }}" class="form-control input-sm" placeholder="Nama, kode produk, atau batch">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Cari</button>
                            <a href="{{ route('expired-stocks.index', ['tab' => $tab]) }}" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</a>
                        </form>
                    </div>

                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped expired-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;">No</th>
                                    <th>Outlet</th>
                                    <th>Kode</th>
                                    <th>Produk</th>
                                    <th>Batch</th>
                                    <th class="text-right">Qty</th>
                                    <th>Expired</th>
                                    <th class="text-right">HPP</th>
                                    <th class="text-right">Nilai</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php($expiredAt = \Carbon\Carbon::parse($row->expired_at))
                                    <tr>
                                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                                        <td>{{ $row->outlet_name }}</td>
                                        <td>{{ $row->product_code }}</td>
                                        <td>
                                            {{ $row->product_name }}
                                            @if ($row->deficit_qty !== null && $tab === 'expired')
                                                <div class="expired-combo" title="Produk ini juga sedang minus">
                                                    Stok minus {{ $row->deficit_qty }}: kemungkinan barang expired sudah dibuang / barang baru belum tercatat, cek fisik.
                                                </div>
                                            @endif
                                        </td>
                                        <td>{{ $row->batch_number ?: '-' }}</td>
                                        <td class="text-right">{{ $row->qty }} {{ $row->satuan }}</td>
                                        <td>
                                            {{ $expiredAt->format('d M Y') }}<br>
                                            @if ($row->days_left < 0)
                                                <small class="expired-late">lewat {{ abs($row->days_left) }} hari</small>
                                            @elseif ($row->days_left === 0)
                                                <small class="expired-soon">expired hari ini</small>
                                            @else
                                                <small class="expired-soon">{{ $row->days_left }} hari lagi</small>
                                            @endif
                                        </td>
                                        <td class="text-right">Rp {{ number_format((float) $row->hpp, 0, ',', '.') }}</td>
                                        <td class="text-right">Rp {{ number_format((float) $row->hpp * $row->qty, 0, ',', '.') }}</td>
                                        <td>
                                            <a class="btn btn-xs btn-default" href="{{ route('owner-stocks.kartu', ['outlet_id' => $row->owner_id, 'product_id' => $row->product_id]) }}"><i class="fa fa-list"></i> Kartu</a>
                                            @if ($tab === 'expired')
                                                <a class="btn btn-xs btn-warning" href="{{ route('refundPembelian.create') }}"><i class="fa fa-undo"></i> Retur Supplier</a>
                                                <button type="button" class="btn btn-xs btn-danger btn-write-off"
                                                    data-id="{{ $row->id }}"
                                                    data-product="{{ $row->product_name }}"
                                                    data-batch="{{ $row->batch_number ?: '-' }}"
                                                    data-qty="{{ $row->qty }}"
                                                    data-toggle="modal" data-target="#writeOffModal">
                                                    <i class="fa fa-trash"></i> Musnahkan
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="10" class="text-center text-muted">
                                        {{ $tab === 'soon' ? 'Tidak ada batch yang akan expired dalam '.$summary['days'].' hari.' : 'Tidak ada batch expired berstok.' }}
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($rows->hasPages())
                        <div class="box-footer">{{ $rows->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" id="writeOffModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="writeOffForm" method="POST" action="" class="modal-content">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Musnahkan Stok Expired</h4>
                </div>
                <div class="modal-body">
                    <p>
                        Produk <b id="writeOffProduct"></b>, batch <b id="writeOffBatch"></b>:
                        <b class="text-danger" id="writeOffQty"></b> akan dijadikan 0 dan dicatat sebagai penyesuaian di Kartu Stock.
                    </p>
                    <div class="form-group">
                        <label for="writeOffReason">Alasan <span class="text-danger">*</span></label>
                        <textarea id="writeOffReason" name="reason" class="form-control" rows="3" minlength="5" maxlength="500" required
                            placeholder="Contoh: sudah dicek fisik, barang expired dibuang"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Musnahkan</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('page-script')
    <script>
        $(function () {
            var writeOffUrl = '{{ route('expired-stocks.write-off', ['ownerStock' => ':id']) }}';

            $('#writeOffModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                $('#writeOffForm').attr('action', writeOffUrl.replace(':id', button.data('id')));
                $('#writeOffProduct').text(button.data('product'));
                $('#writeOffBatch').text(button.data('batch'));
                $('#writeOffQty').text(button.data('qty'));
                $('#writeOffReason').val('');
                $('#writeOffForm').find('button[type=submit]').prop('disabled', false);
            });

            // Cegah kirim ganda saat tombol ditekan berulang.
            $('#writeOffForm').on('submit', function () {
                $(this).find('button[type=submit]').prop('disabled', true);
            });
        });
    </script>
@endsection