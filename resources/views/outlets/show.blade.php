@extends('layouts.master')

@section('title', $outlet->name . ' POS')

@section('body_class', 'sidebar-collapse pos-mode')

@section('container')
    @php($summary = $cashierSummary ?? [])
    <section class="content">
        <div class="pos-header">
            <div>
                <strong>{{ $outlet->name }}</strong>
                @if ($cashierSession)
                    <span class="label label-success pos-open-label"><i class="fa fa-unlock"></i> Kasir terbuka</span>
                @else
                    <span class="label label-default pos-open-label"><i class="fa fa-lock"></i> Kasir belum dibuka</span>
                @endif
                @if ($cashierSession)
                    <div class="pos-cash-summary">
                        <span>Saldo awal <b>Rp {{ number_format($summary['opening_cash'] ?? 0, 0, ',', '.') }}</b></span>
                        <span>Penjualan tunai <b>Rp {{ number_format($summary['cash_sales'] ?? 0, 0, ',', '.') }}</b></span>
                        <span>BON <b class="text-danger">Rp {{ number_format($summary['cash_out'] ?? 0, 0, ',', '.') }}</b></span>
                        <span>Kas berjalan <b>Rp {{ number_format($summary['expected_cash'] ?? 0, 0, ',', '.') }}</b></span>
                    </div>
                @endif
            </div>
            <div class="pos-header-actions">
                <span class="pos-shortcuts">F2 Cari · F3 Scan · F8 Voucher · F10 Process</span>
                @if ($cashierSession)
                    <button type="button" class="btn btn-xs btn-warning" data-toggle="modal" data-target="#bonModal">
                        <i class="fa fa-minus-circle"></i> Catat BON
                    </button>
                    <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#drawerCheckModal">
                        <i class="fa fa-calculator"></i> Cek drawer
                    </button>
                    <a class="btn btn-xs btn-default" href="{{ route('cashier.print.products', ['outlet_id' => $outlet->id]) }}" target="_blank">
                        <i class="fa fa-tags"></i> Label produk
                    </a>
                    <a class="btn btn-xs btn-default" href="{{ route('cashier.print.vouchers', ['outlet_id' => $outlet->id]) }}" target="_blank">
                        <i class="fa fa-barcode"></i> Label voucher
                    </a>
                    <a class="btn btn-xs btn-info" href="{{ route('cashier.history', ['outlet_id' => $outlet->id]) }}" target="_blank">
                        <i class="fa fa-history"></i> History
                    </a>
                    <button type="button" class="btn btn-xs btn-danger" data-toggle="modal" data-target="#closeCashierModal">
                        <i class="fa fa-lock"></i> Tutup kasir
                    </button>
                @endif
            </div>
        </div>

        @if (! $cashierSession)
            <div class="cashier-open-gate">
                <div class="cashier-open-card">
                    <div class="cashier-open-icon"><i class="fa fa-money"></i></div>
                    <h2>Buka kasir</h2>
                    <p class="text-muted">Cek uang fisik di cash drawer sebelum mulai transaksi, lalu simpan sebagai saldo awal sesi.</p>
                    <form action="{{ route('cashier.open', $outlet) }}" method="POST">
                        @csrf
                        <div class="form-group text-left">
                            <label for="opening_cash">Nominal cash drawer saat buka</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-addon">Rp</span>
                                <input id="opening_cash" type="text" inputmode="numeric" name="opening_cash" class="form-control" min="0" data-currency-input data-currency-decimals="0" autocomplete="off"
                                    value="{{ old('opening_cash', $suggestedOpeningCash ?? 1000000) }}" required autofocus>
                            </div>
                            <small class="help-block">Saran saldo dari penutupan terakhir: Rp {{ number_format($suggestedOpeningCash ?? 1000000, 0, ',', '.') }}.</small>
                        </div>
                        <div class="form-group text-left">
                            <label for="opening_note">Catatan pembukaan <small>(opsional)</small></label>
                            <textarea id="opening_note" name="opening_note" class="form-control" rows="2" placeholder="Contoh: saldo awal dihitung bersama supervisor">{{ old('opening_note') }}</textarea>
                        </div>
                        <button class="btn btn-success btn-lg btn-block" type="submit"><i class="fa fa-unlock"></i> Buka kasir</button>
                    </form>
                </div>
            </div>
        @else
            <div id="cart"></div>
        @endif
    </section>

    @if ($cashierSession)
        <div class="modal fade" id="bonModal" tabindex="-1" role="dialog" aria-labelledby="bonModalLabel">
            <div class="modal-dialog modal-sm" role="document">
                <div class="modal-content">
                    <form action="{{ route('cashier.drawer-entry', $cashierSession) }}" method="POST">
                        @csrf
                        <input type="hidden" name="type" value="bon">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title" id="bonModalLabel"><i class="fa fa-minus-circle"></i> Catat BON / Uang Keluar</h4>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">Catat setiap uang yang dikeluarkan dari cash drawer selama jam kerja.</p>
                            <div class="form-group">
                                <label for="bon_amount">Nominal</label>
                                <div class="input-group">
                                    <span class="input-group-addon">Rp</span>
                                    <input id="bon_amount" type="number" name="amount" class="form-control" min="1" step="1000" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="bon_note">Keperluan</label>
                                <textarea id="bon_note" name="note" class="form-control" rows="3" placeholder="Contoh: setor hasil penjualan ke owner" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Simpan BON</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="drawerCheckModal" tabindex="-1" role="dialog" aria-labelledby="drawerCheckModalLabel">
            <div class="modal-dialog modal-sm" role="document">
                <div class="modal-content">
                    <form action="{{ route('cashier.drawer-entry', $cashierSession) }}" method="POST">
                        @csrf
                        <input type="hidden" name="type" value="check">
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title" id="drawerCheckModalLabel"><i class="fa fa-calculator"></i> Cek cash drawer</h4>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">Hitung uang fisik sekarang. Sistem menyimpan nominal ini dan selisih terhadap kas berjalan untuk history.</p>
                            <div class="form-group">
                                <label for="check_amount">Uang fisik saat dicek</label>
                                <div class="input-group">
                                    <span class="input-group-addon">Rp</span>
                                    <input id="check_amount" type="text" inputmode="numeric" name="amount" class="form-control" min="0" data-currency-input data-currency-decimals="0" autocomplete="off" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="check_note">Catatan</label>
                                <textarea id="check_note" name="note" class="form-control" rows="2" placeholder="Contoh: pengecekan tengah shift" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan cek</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="closeCashierModal" tabindex="-1" role="dialog" aria-labelledby="closeCashierModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form action="{{ route('cashier.close', $cashierSession) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title" id="closeCashierModalLabel"><i class="fa fa-lock"></i> Tutup kasir</h4>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info small">
                                Saldo sistem saat ini: <strong>Rp {{ number_format($summary['expected_cash'] ?? 0, 0, ',', '.') }}</strong>.
                                Hitung uang fisik, lalu sisakan saldo untuk besok.
                            </div>
                            <div class="row">
                                <div class="col-sm-6 form-group">
                                    <label for="closing_cash">Uang fisik saat tutup</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">Rp</span>
                                        <input id="closing_cash" type="text" inputmode="numeric" name="closing_cash" class="form-control" min="0" data-currency-input data-currency-decimals="0" autocomplete="off"
                                            value="{{ old('closing_cash', $summary['expected_cash'] ?? 0) }}" required>
                                    </div>
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="carry_over_cash">Saldo disisakan besok</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">Rp</span>
                                        <input id="carry_over_cash" type="text" inputmode="numeric" name="carry_over_cash" class="form-control" min="0" data-currency-input data-currency-decimals="0" autocomplete="off"
                                            value="{{ old('carry_over_cash', $summary['opening_cash'] ?? 1000000) }}" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="closing_note">Catatan penutupan <small>(opsional)</small></label>
                                <textarea id="closing_note" name="closing_note" class="form-control" rows="2" placeholder="Contoh: setoran dibawa supervisor">{{ old('closing_note') }}</textarea>
                            </div>
                            <p class="text-muted small">Setoran/uang yang diambil saat tutup dihitung otomatis: uang fisik dikurangi saldo besok. Jika berbeda dari saldo sistem, selisihnya masuk history.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Tutup sesi kasir dan kembali ke daftar penjualan?')"><i class="fa fa-lock"></i> Tutup kasir</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@section('page-script')
    <script>
        window.outlet = @json($outlet);
        window.POS_OUTLET_ID = @json($outlet->id);
        window.POS_PRODUCTS_URL = @json(route('outlet.products', ['outlet' => $outlet->id]));
        window.user = @json(auth()->user());
    </script>
    <style>
        html:has(body.pos-mode), body.pos-mode { height:100%; overflow:hidden; }
        .pos-mode .wrapper { height:100vh; min-height:0; overflow:hidden; }
        .pos-mode .main-header, .pos-mode .main-sidebar, .pos-mode .main-footer { display:none !important; }
        .pos-mode { background:#f4f6f9; }
        body.pos-mode.sidebar-collapse .content-wrapper, body.pos-mode.sidebar-mini.sidebar-collapse .content-wrapper { margin-left:0 !important; min-height:0 !important; height:100vh; overflow:hidden; background:#f4f6f9; }
        .pos-mode .content { height:100%; min-height:0 !important; padding:15px; overflow:hidden; background:#f4f6f9; display:flex; flex-direction:column; }
        .pos-header { flex:0 0 auto; display:flex; justify-content:space-between; align-items:flex-start; gap:15px; min-height:46px; margin-bottom:12px; padding:10px 14px; background:#fff; border-left:4px solid #605ca8; box-shadow:0 1px 2px rgba(0,0,0,.08); font-size:16px; }
        .pos-header-actions { display:flex; justify-content:flex-end; align-items:center; gap:4px; flex-wrap:wrap; }
        .pos-header-actions .pos-shortcuts { margin-right:5px; }
        .pos-header span { font-size:12px; color:#777; }
        .pos-open-label { margin-left:7px; vertical-align:2px; font-size:10px !important; }
        .pos-cash-summary { display:flex; gap:14px; flex-wrap:wrap; margin-top:5px; font-size:11px; color:#777; }
        .pos-cash-summary b { color:#333; }
        #cart { flex:1 1 auto; min-height:0; overflow:hidden; }
        #cart > .pos-cashier-layout, #cart > .pos-cashier-layout > .pos-cashier-column { height:100%; min-height:0; }
        #cart > .pos-cashier-layout > .pos-cashier-column { display:flex; flex-direction:column; }
        .pos-cashier-details { flex:1 1 auto; min-height:0; display:flex; flex-direction:column; overflow:hidden; }
        .pos-cashier-details > .table-responsive:first-child { flex:1 1 0; min-height:0; max-height:none !important; overflow-y:auto !important; }
        .pos-cashier-details > .table-responsive:nth-child(2), .pos-cashier-details > .row, .pos-cashier-details > p, .pos-cashier-details > .alert { flex:0 0 auto; }
        .pos-cashier-details > .row { margin-bottom:0; }
        .pos-cashier-details > .table-responsive:nth-child(2) table { margin-bottom:0; }
        .pos-cashier-details tr[tabindex="0"]:focus, .pos-mode tr[tabindex="0"]:focus { outline:2px solid #605ca8; outline-offset:-2px; }
        .cashier-open-gate { flex:1 1 auto; display:flex; align-items:center; justify-content:center; overflow:auto; }
        .cashier-open-card { width:100%; max-width:460px; padding:30px; text-align:center; background:#fff; border-top:4px solid #00a65a; box-shadow:0 2px 8px rgba(0,0,0,.1); }
        .cashier-open-card h2 { margin-top:10px; }
        .cashier-open-icon { width:64px; height:64px; margin:0 auto; border-radius:50%; background:#e9f8ef; color:#00a65a; font-size:32px; line-height:64px; }
        @media (max-width: 900px) {
            .pos-header { display:block; }
            .pos-header-actions { justify-content:flex-start; margin-top:8px; }
            .pos-header-actions .pos-shortcuts { display:none; }
        }
    </style>
@endsection
