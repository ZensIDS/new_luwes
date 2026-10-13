@extends('layouts.master')

@section('title', 'Stok Minus')

@section('container')
    <style>
        .deficit-filter-bar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px; }
        .deficit-filter { margin:0; min-width:180px; }
        .deficit-filter label { display:block; margin-bottom:4px; font-size:12px; color:#666; }
        .deficit-table th, .deficit-table td { vertical-align:middle !important; }
        .deficit-table .btn { margin:1px 0; }
        .deficit-qty { color:#dd4b39; font-weight:700; }
        @media (max-width:767px) { .deficit-filter, .deficit-filter .form-control { width:100% !important; } }
    </style>

    <section class="content-header">
        <h1>Stok Minus <small>Penjualan yang melebihi stok sistem</small></h1>
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
                        Produk di sini terjual saat stok sistem 0 (barang ada secara fisik tetapi belum tercatat).
                        Minus <b>ditutup otomatis</b> saat barang dicatat lewat penerimaan (Belanja Langsung, kiriman DO, atau retur).
                        Gunakan <b>Tutup minus</b> hanya bila barang tidak akan dicatat; alasan wajib diisi dan tercatat di Kartu Stock.
                    </p>
                </div>

                <div class="box">
                    <div class="box-header">
                        <form method="GET" action="{{ route('deficit-stocks.index') }}" class="deficit-filter-bar">
                            @if ($canChooseOutlet)
                                <div class="deficit-filter">
                                    <label for="outlet_id">Outlet</label>
                                    <select id="outlet_id" name="outlet_id" class="form-control input-sm" onchange="this.form.submit()">
                                        <option value="">Semua outlet</option>
                                        @foreach ($outlets as $outlet)
                                            <option value="{{ $outlet->id }}" {{ (int) $selectedOutletId === (int) $outlet->id ? 'selected' : '' }}>{{ $outlet->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="deficit-filter">
                                <label for="q">Cari produk</label>
                                <input id="q" type="text" name="q" value="{{ $search }}" class="form-control input-sm" placeholder="Nama atau kode produk">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Cari</button>
                            <a href="{{ route('deficit-stocks.index') }}" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Reset</a>
                            <a href="{{ route('deficit-stocks.oversold', array_filter(['outlet_id' => $selectedOutletId])) }}" class="btn btn-warning btn-sm">
                                <i class="fa fa-file-text-o"></i> Laporan Penjualan Melebihi Stok
                            </a>
                        </form>
                        <p class="text-muted" style="margin:0;">
                            Total <strong>{{ $totalOpen }}</strong> produk sedang minus{{ $selectedOutletId ? ' di outlet ini' : '' }}.
                        </p>
                    </div>

                    <div class="box-body table-responsive">
                        <table class="table table-bordered table-striped deficit-table">
                            <thead>
                                <tr>
                                    <th style="width:40px;">No</th>
                                    <th>Outlet</th>
                                    <th>Kode</th>
                                    <th>Produk</th>
                                    <th class="text-right">Qty Minus</th>
                                    <th>Sejak</th>
                                    <th class="text-right">Penjualan Terkait</th>
                                    <th class="text-right">HPP Perkiraan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php($since = $row->since ? \Carbon\Carbon::parse($row->since) : null)
                                    <tr>
                                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                                        <td>{{ $row->outlet_name }}</td>
                                        <td>{{ $row->product_code }}</td>
                                        <td>
                                            {{ $row->product_name }}
                                            @if ($row->expired_qty > 0)
                                                <div style="margin-top:4px;padding:2px 6px;font-size:12px;color:#8a6d3b;background:#fcf8e3;border:1px solid #faebcc;border-radius:3px;display:inline-block;">
                                                    Ada batch expired ({{ $row->expired_qty }}): kemungkinan barang expired sudah dibuang / barang baru belum tercatat, cek fisik.
                                                    <a href="{{ route('expired-stocks.index', ['outlet_id' => $row->owner_id, 'q' => $row->product_code]) }}">Lihat</a>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-right"><span class="deficit-qty">{{ $row->qty }}</span> {{ $row->satuan }}</td>
                                        <td>{{ $since ? $since->format('d M Y H:i') : '-' }}</td>
                                        <td class="text-right">{{ $row->sales_count }} invoice</td>
                                        <td class="text-right">Rp {{ number_format((float) $row->hpp, 0, ',', '.') }}</td>
                                        <td>
                                            <a class="btn btn-xs btn-info" href="{{ route('deficit-stocks.oversold', array_filter([
                                                'outlet_id' => $row->owner_id,
                                                'product_id' => $row->product_id,
                                                'tanggal_mulai' => $since?->toDateString(),
                                                'tanggal_selesai' => now()->toDateString(),
                                            ])) }}"><i class="fa fa-file-text-o"></i> Penjualan</a>
                                            <a class="btn btn-xs btn-default" href="{{ route('owner-stocks.kartu', ['outlet_id' => $row->owner_id, 'product_id' => $row->product_id]) }}"><i class="fa fa-list"></i> Kartu</a>
                                            <a class="btn btn-xs btn-primary" href="{{ route('outlet-purchases.create', ['outlet_id' => $row->owner_id]) }}"><i class="fa fa-plus"></i> Buat Penerimaan</a>
                                            <button type="button" class="btn btn-xs btn-danger btn-close-deficit"
                                                data-id="{{ $row->id }}"
                                                data-product="{{ $row->product_name }}"
                                                data-qty="{{ abs($row->qty) }}"
                                                data-toggle="modal" data-target="#closeDeficitModal">
                                                <i class="fa fa-check-circle"></i> Tutup Minus
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="text-center text-muted">Tidak ada produk dengan stok minus.</td></tr>
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

    <div class="modal fade" id="closeDeficitModal" tabindex="-1">
        <div class="modal-dialog">
            <form id="closeDeficitForm" method="POST" action="" class="modal-content">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Tutup Stok Minus</h4>
                </div>
                <div class="modal-body">
                    <p>
                        Produk <b id="closeDeficitProduct"></b> minus <b class="text-danger" id="closeDeficitQty"></b>.
                        Saldo akan dijadikan 0 dan dicatat sebagai penyesuaian di Kartu Stock.
                    </p>
                    <div class="form-group">
                        <label for="closeDeficitReason">Alasan <span class="text-danger">*</span></label>
                        <textarea id="closeDeficitReason" name="reason" class="form-control" rows="3" minlength="5" maxlength="500" required
                            placeholder="Contoh: barang memang ada tapi tidak pernah dicatat, sudah dicek fisik"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger"><i class="fa fa-check-circle"></i> Tutup Minus</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('page-script')
    <script>
        $(function () {
            var closeUrl = '{{ route('deficit-stocks.close', ['ownerStock' => ':id']) }}';

            $('#closeDeficitModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                $('#closeDeficitForm').attr('action', closeUrl.replace(':id', button.data('id')));
                $('#closeDeficitProduct').text(button.data('product'));
                $('#closeDeficitQty').text('-' + button.data('qty'));
                $('#closeDeficitReason').val('');
            });

            // Cegah kirim ganda saat tombol ditekan berulang.
            $('#closeDeficitForm').on('submit', function () {
                $(this).find('button[type=submit]').prop('disabled', true);
            });
        });
    </script>
@endsection