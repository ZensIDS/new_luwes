@extends('layouts.master')

@section('title', $outlet->name . ' POS')

@section('body_class', 'sidebar-collapse pos-mode')

@section('container')
    @php($summary = $cashierSummary ?? [])
    @php($activeShift = $cashierSession?->activeShifts?->first())
    <section class="content">
        <div class="pos-header">
            <form action="{{ route('logout') }}" method="POST" class="pos-logout"@if ($cashierSession) onsubmit="return confirm('Logout dari akun? Sesi kasir tetap terbuka.')"@endif>
                @csrf
                <button type="submit" title="Logout" tabindex="-1"><i class="fa fa-power-off"></i></button>
            </form>
            <div>
                <strong>{{ $outlet->name }}</strong>
                @if ($cashierSession)
                    <span class="label label-success pos-open-label"><i class="fa fa-unlock"></i> Kasir terbuka</span>
                @else
                    <span class="label label-default pos-open-label"><i class="fa fa-lock"></i> Kasir belum dibuka</span>
                @endif
                @if ($cashierSession)
                    <div class="pos-cash-summary">
                        <span>Kassa <b>{{ $cashierSession->cashier?->name ?? '—' }}</b></span>
                        <span>Nama kasir <b>{{ $activeShift?->name ?? '—' }}</b></span>
                        <span>Saldo awal <b>Rp {{ number_format($summary['opening_cash'] ?? 0, 0, ',', '.') }}</b></span>
                        <span>Penjualan tunai <b>Rp {{ number_format($summary['cash_sales'] ?? 0, 0, ',', '.') }}</b></span>
                        <span>Non-tunai <b>Rp {{ number_format($summary['breakdown']['non_cash_total'] ?? 0, 0, ',', '.') }}</b></span>
                        <span>Voucher <b>{{ number_format($summary['breakdown']['voucher_count'] ?? 0, 0, ',', '.') }}x / Rp {{ number_format($summary['breakdown']['voucher_total'] ?? 0, 0, ',', '.') }}</b></span>
                        <span>BON <b class="text-danger">Rp {{ number_format($summary['cash_out'] ?? 0, 0, ',', '.') }}</b></span>
                        <span title="Penjualan tunai + kas masuk - BON, di luar saldo awal">Kas penjualan <b>Rp {{ number_format($summary['expected_cash'] ?? 0, 0, ',', '.') }}</b></span>
                        <span title="Saldo awal + kas penjualan">Isi laci <b>Rp {{ number_format($summary['drawer_total'] ?? 0, 0, ',', '.') }}</b></span>
                    </div>
                @endif
            </div>
            <div class="pos-header-actions">
                <a class="btn btn-xs btn-warning" href="{{ route('refundPenjualan.create', ['outlet_id' => $outlet->id]) }}">
                    <i class="fa fa-exchange"></i> Retur barang
                </a>
                @if ($cashierSession)
                    <button type="button" class="btn btn-xs btn-warning" data-toggle="modal" data-target="#bonModal">
                        <i class="fa fa-minus-circle"></i> Catat BON
                    </button>
                    <button type="button" class="btn btn-xs btn-warning" data-toggle="modal" data-target="#changeShiftModal">
                        <i class="fa fa-refresh"></i> Ganti shift
                    </button>
                    <a class="btn btn-xs btn-info" href="{{ route('cashier.history', ['outlet_id' => $outlet->id]) }}" target="_blank">
                        <i class="fa fa-history"></i> History
                    </a>
                    <a class="btn btn-xs btn-info" href="{{ route('penjualan.index', ['outlet_id' => $outlet->id]) }}" target="_blank">
                        <i class="fa fa-file-text"></i> Daftar Penjualan
                    </a>
                    <button type="button" class="btn btn-xs btn-danger" data-toggle="modal" data-target="#closeCashierModal">
                        <i class="fa fa-lock"></i> Tutup kasir
                    </button>
                @endif
            </div>
        </div>

        @if (($deficitCount ?? 0) > 0)
            <div class="alert alert-warning" style="margin:8px 0;">
                <i class="fa fa-exclamation-triangle"></i>
                Ada <b>{{ $deficitCount }}</b> produk stok minus, perlu dicek.
                <a href="{{ route('deficit-stocks.index', ['outlet_id' => $outlet->id]) }}" class="alert-link">Lihat Stok Minus</a>
            </div>
        @endif

        @if ($expirySummary && ($expirySummary['expired'] > 0 || $expirySummary['soon'] > 0))
            <div class="alert alert-{{ $expirySummary['expired'] > 0 ? 'danger' : 'warning' }}" style="margin:8px 0;">
                <i class="fa fa-calendar-times-o"></i>
                @if ($expirySummary['expired'] > 0)
                    <b>{{ $expirySummary['expired'] }}</b> batch sudah expired
                    @if ($expirySummary['soon'] > 0), <b>{{ $expirySummary['soon'] }}</b> batch akan expired &le; {{ $expirySummary['days'] }} hari @endif.
                @else
                    <b>{{ $expirySummary['soon'] }}</b> batch akan expired &le; {{ $expirySummary['days'] }} hari.
                @endif
                @if ($expirySummary['combo'] > 0)
                    <b>{{ $expirySummary['combo'] }}</b> produk expired sekaligus minus, cek fisik.
                @endif
                <a href="{{ route('expired-stocks.index', ['outlet_id' => $outlet->id]) }}" class="alert-link">Lihat Stok Expired</a>
            </div>
        @endif

        @if (! $cashierSession)
            <div class="cashier-open-gate">
                <div class="cashier-open-card">
                    <div class="cashier-open-icon"><i class="fa fa-unlock-alt"></i></div>
                    <h2>Buka kasir</h2>
                    <form action="{{ route('cashier.open', $outlet) }}" method="POST" autocomplete="off">
                        @csrf
                        <div class="form-group text-left">
                            <label for="opening_cashier_name">Nama kasir</label>
                            <input id="opening_cashier_name" type="text" name="opening_cashier_name" class="form-control input-lg" value="{{ old('opening_cashier_name') }}" maxlength="150" placeholder="Contoh: Siti" required autofocus>
                        </div>
                        <div class="form-group text-left">
                            <label for="opening_cash">Saldo Stock</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-addon">Rp</span>
                                <input id="opening_cash" type="text" inputmode="numeric" name="opening_cash" class="form-control" min="0" data-currency-input data-currency-decimals="0" autocomplete="off"
                                    value="{{ old('opening_cash', $suggestedOpeningCash ?? 1000000) }}" required>
                            </div>
                        </div>
                        <button class="btn btn-success btn-lg btn-block" type="submit"><i class="fa fa-unlock"></i> Buka kasir</button>
                    </form>
                    <div class="cashier-open-links">
                        <a class="btn btn-default" href="{{ route('cashier.history', ['outlet_id' => $outlet->id]) }}"><i class="fa fa-history"></i> History</a>
                        <a class="btn btn-default" href="{{ route('penjualan.index', ['outlet_id' => $outlet->id]) }}"><i class="fa fa-file-text"></i> Daftar Penjualan</a>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="cashier-open-logout">
                        @csrf
                        <button type="submit" class="btn btn-danger btn-block"><i class="fa fa-sign-out"></i> Logout</button>
                    </form>
                    <script>document.getElementById('opening_cashier_name').focus();</script>
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
                            <div class="form-group">
                                <label for="bon_amount">Nominal</label>
                                <div class="input-group">
                                    <span class="input-group-addon">Rp</span>
                                    <input id="bon_amount" type="text" inputmode="numeric" name="amount" class="form-control" data-currency-input data-currency-decimals="0" autocomplete="off" placeholder="0" required>
                                </div>
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

        <div class="modal fade" id="changeShiftModal" tabindex="-1" role="dialog" aria-labelledby="changeShiftModalLabel">
            <div class="modal-dialog modal-sm" role="document">
                <div class="modal-content">
                    <form action="{{ route('cashier.change-shift', $cashierSession) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title" id="changeShiftModalLabel"><i class="fa fa-refresh"></i> Ganti shift</h4>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">Akun kassa tetap login. Pergantian ini hanya mengganti nama kasir dan dapat dilakukan berkali-kali dalam satu sesi.</p>
                            <div class="form-group">
                                <label for="shift_cashier_name">Nama kasir baru</label>
                                <input id="shift_cashier_name" type="text" name="name" class="form-control" maxlength="150" placeholder="Contoh: Budi" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-warning"><i class="fa fa-refresh"></i> Simpan ganti shift</button>
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
                            <table class="table table-condensed table-bordered" style="margin-bottom:10px;">
                                <tbody>
                                    <tr><td>Penjualan tunai</td><td class="text-right">Rp {{ number_format($summary['cash_sales'] ?? 0, 0, ',', '.') }}</td></tr>
                                    <tr><td>Kas masuk (mis. selisih tukar barang)</td><td class="text-right">Rp {{ number_format($summary['cash_in'] ?? 0, 0, ',', '.') }}</td></tr>
                                    <tr><td>BON / uang keluar</td><td class="text-right text-danger">- Rp {{ number_format($summary['cash_out'] ?? 0, 0, ',', '.') }}</td></tr>
                                    <tr id="closeExpectedRow" class="info" data-expected="{{ (float) ($summary['expected_cash'] ?? 0) }}">
                                        <th>Uang hasil penjualan yang seharusnya ada</th>
                                        <th class="text-right">Rp {{ number_format($summary['expected_cash'] ?? 0, 0, ',', '.') }}</th>
                                    </tr>
                                    <tr id="closeDifferenceRow">
                                        <td>Selisih dengan uang fisik</td>
                                        <td class="text-right"><strong id="closeDifferenceText">isi uang fisik</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Total uang di laci setelah tutup <small class="text-muted">(uang fisik + saldo besok)</small></td>
                                        <td class="text-right"><strong id="closeDrawerTotal">Rp 0</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="text-muted small" style="margin-top:-4px;">
                                Saldo awal Rp {{ number_format($summary['opening_cash'] ?? 0, 0, ',', '.') }} tidak ikut dihitung di atas.
                            </p>

                            @include('cashier._breakdown', ['breakdown' => $summary['breakdown'] ?? \App\Models\CashierSession::emptyBreakdown()])

                            <div class="row">
                                <div class="col-sm-6 form-group">
                                    <label for="closing_cash">Uang fisik hasil penjualan tunai</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">Rp</span>
                                        <input id="closing_cash" type="text" inputmode="numeric" name="closing_cash" class="form-control" min="0" data-currency-input data-currency-decimals="0" autocomplete="off" placeholder="Hitung uang fisik"
                                            value="{{ old('closing_cash') }}" required>
                                    </div>
                                    <small class="help-block">Hanya uang dari penjualan, di luar saldo yang disisakan untuk besok.</small>
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="carry_over_cash">Saldo disisakan besok</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">Rp</span>
                                        <input id="carry_over_cash" type="text" inputmode="numeric" name="carry_over_cash" class="form-control" min="0" data-currency-input data-currency-decimals="0" autocomplete="off"
                                            value="{{ old('carry_over_cash', $summary['opening_cash'] ?? 1000000) }}" required>
                                    </div>
                                    <small class="help-block">Terpisah dari uang fisik di samping.</small>
                                </div>
                            </div>

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
    <script>
        // Selisih tutup kasir dihitung langsung saat uang fisik / saldo besok diketik.
        // Baris "seharusnya" hijau bila pas, merah bila selisih, biru selama uang fisik belum diisi.
        (function () {
            const expectedRow = document.getElementById('closeExpectedRow');
            const diffRow = document.getElementById('closeDifferenceRow');
            const closing = document.getElementById('closing_cash');
            const carry = document.getElementById('carry_over_cash');
            const text = document.getElementById('closeDifferenceText');
            const drawer = document.getElementById('closeDrawerTotal');
            if (!expectedRow || !diffRow || !closing || !carry || !text || !drawer) return;

            const expected = Number(expectedRow.dataset.expected || 0);
            const toNumber = (value) => Number(String(value || '').replace(/[^\d]/g, '')) || 0;
            const rp = (value) => 'Rp ' + Math.abs(value).toLocaleString('id-ID');
            const states = ['info', 'success', 'danger'];

            const paint = (state) => {
                [expectedRow, diffRow].forEach((row) => {
                    states.forEach((name) => row.classList.remove(name));
                    row.classList.add(state);
                });
            };

            const render = () => {
                const hasClosing = String(closing.value || '').trim() !== '';
                const physical = toNumber(closing.value);
                drawer.textContent = rp(physical + toNumber(carry.value));

                if (!hasClosing) {
                    text.textContent = 'isi uang fisik';
                    expectedRow.classList.remove('success', 'danger');
                    expectedRow.classList.add('info');
                    diffRow.classList.remove('info', 'success', 'danger');
                    return;
                }

                const diff = physical - expected;
                if (diff === 0) {
                    text.textContent = 'Pas (Rp 0)';
                    paint('success');
                } else {
                    text.textContent = (diff > 0 ? 'Lebih ' : 'Kurang ') + rp(diff);
                    paint('danger');
                }
            };

            closing.addEventListener('input', render);
            carry.addEventListener('input', render);
            render();
        })();

        // Modal BON & Tutup kasir: kursor langsung di kolom isian utama begitu modal terbuka.
        if (window.jQuery) {
            const focusOnShown = (modalId, inputId) => {
                window.jQuery(modalId).on('shown.bs.modal', function () {
                    const input = document.getElementById(inputId);
                    if (input) { input.focus(); input.select(); }
                });
            };
            focusOnShown('#bonModal', 'bon_amount');
            focusOnShown('#closeCashierModal', 'closing_cash');
        }
    </script>
    @if ($cashierSession)
        @vite(['resources/js/pos.js'])
    @endif
    <style>
        html:has(body.pos-mode), body.pos-mode { height:100%; overflow:hidden; }
        .pos-mode .wrapper { height:100vh; min-height:0; overflow:hidden; }
        .pos-mode .main-header, .pos-mode .main-sidebar, .pos-mode .main-footer { display:none !important; }
        .pos-mode { background:#f4f6f9; }
        body.pos-mode.sidebar-collapse .content-wrapper, body.pos-mode.sidebar-mini.sidebar-collapse .content-wrapper { margin-left:0 !important; min-height:0 !important; height:100vh; overflow:hidden; background:#f4f6f9; }
        .pos-mode .content { height:100%; min-height:0 !important; padding:15px; overflow:hidden; background:#f4f6f9; display:flex; flex-direction:column; }
        .pos-header { position:relative; padding-right:52px !important; }
        .pos-logout { position:absolute; top:8px; right:10px; margin:0; }
        .pos-logout button { width:30px; height:30px; padding:0; border:1px solid #d9534f; border-radius:50%; background:#fff; color:#d9534f; font-size:14px; line-height:28px; cursor:pointer; }
        .pos-logout button:hover { background:#d9534f; color:#fff; }
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
        .pos-quick-field { position:fixed; left:-10000px; top:0; width:420px; max-width:92vw; }
        .pos-quick-field:focus-within { left:50%; top:25%; transform:translateX(-50%); z-index:1060; padding:14px; background:#fff; border-radius:4px; box-shadow:0 8px 30px rgba(0,0,0,.35); }
        .pos-cashier-details > .pos-quick-field { flex:0 0 auto; }
        .cashier-open-gate { flex:1 1 auto; display:flex; align-items:center; justify-content:center; overflow:auto; }
        .cashier-open-card { width:100%; max-width:460px; padding:30px; text-align:center; background:#fff; border-top:4px solid #00a65a; box-shadow:0 2px 8px rgba(0,0,0,.1); }
        .cashier-open-card h2 { margin-top:10px; }
        .cashier-open-links { display:flex; gap:8px; margin-top:14px; }
        .cashier-open-links .btn { flex:1 1 0; }
        .cashier-open-logout { margin-top:8px; }
        .cashier-open-icon { width:64px; height:64px; margin:0 auto; border-radius:50%; background:#e9f8ef; color:#00a65a; font-size:32px; line-height:64px; }
        @media (max-width: 900px) {
            .pos-header { display:block; }
            .pos-header-actions { justify-content:flex-start; margin-top:8px; }
            .pos-header-actions .pos-shortcuts { display:none; }
        }
    </style>
@endsection