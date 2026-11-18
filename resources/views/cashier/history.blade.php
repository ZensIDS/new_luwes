@extends('layouts.master')

@section('title', 'History Kasir')

@section('container')
    <style>
        .ch-table { margin-bottom:0; }
        .ch-table > thead > tr > th { background:#f3f4f6; color:#374151; font-size:12px; text-transform:uppercase; letter-spacing:.3px; vertical-align:middle; white-space:nowrap; border-bottom:2px solid #d1d5db; }
        .ch-table > tbody > tr.ch-main > td { vertical-align:middle; border-top:2px solid #d1d5db; background:#fff; }
        .ch-table > tbody > tr.ch-detail > td { background:#f9fafb; border-top:1px dashed #e5e7eb; padding:12px 14px; }
        .ch-table td.num, .ch-table th.num { text-align:right; white-space:nowrap; }
        .ch-period strong { display:block; }
        .ch-period small { color:#9ca3af; }
        .ch-outlet small { display:block; color:#6b7280; line-height:1.5; }
        .ch-shifts { margin:2px 0 0; padding-left:0; list-style:none; }
        .ch-shifts li { font-size:12px; color:#6b7280; line-height:1.5; }
        .ch-income { font-weight:700; color:#00a65a; }
        .ch-actions .btn { margin:2px 2px 0 0; }
        .ch-note { margin-top:8px; padding-top:8px; border-top:1px solid #e5e7eb; font-size:13px; }
        .ch-note .ch-note-title { color:#6b7280; font-weight:600; }
        .ch-drawer { margin-top:6px; font-size:13px; }
        .ch-drawer .ch-entry { display:block; padding:2px 0; }
        .ch-detail.is-hidden { display:none; }
        .ch-toggle .fa { transition:transform .15s; }
        .ch-toggle.is-open .fa { transform:rotate(180deg); }
        .ch-help { color:#6b7280; font-size:12px; margin:0 0 12px; }
    </style>

    <section class="content-header">
        <h1>History Kasir <small>Open, BON, dan tutup cash drawer</small></h1>
    </section>
    <section class="content">
        @php($rp = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.'))
        @php($canSeeCash = $canSeeCash ?? false)
        @php($colspan = $canSeeCash ? 11 : 8)
        @php($authUser = auth()->user())
        @php($editLogs = $canSeeCash
            ? $activities->filter(fn ($a) => $a->subject_type === \App\Models\CashierSession::class && isset($a->properties['edit_number']))->groupBy('subject_id')
            : collect())

        <div class="box box-primary">
            <div class="box-header with-border">
                <a href="{{ route('penjualan.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Kembali ke penjualan</a>
                <span class="text-muted small" style="margin-left:10px;">Semua aktivitas dicatat dengan Spatie Activitylog.</span>
                <button type="button" class="btn btn-default btn-sm pull-right" id="chToggleAll"><i class="fa fa-expand"></i> Buka semua rincian</button>
            </div>
            <div class="box-body table-responsive">
                <p class="ch-help">
                    <strong>Pendapatan</strong> = total penjualan semua metode bayar pada sesi tersebut.
                    <strong>Uang fisik</strong> = uang tunai hasil penjualan (ditambah kas masuk, dikurangi BON).
                    <strong>Selisih</strong> = uang fisik &minus; kas seharusnya.
                    Sesi yang ditutup sebelum perhitungan ini diperbarui tetap memakai angka lama.
                </p>
                <table class="table table-bordered ch-table">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <th>Outlet / Kassa</th>
                            @if ($canSeeCash)
                                <th class="num">Pendapatan</th>
                                <th class="num">Penjualan tunai</th>
                            @endif
                            <th class="num">BON</th>
                            @if ($canSeeCash)
                                <th class="num">Kas seharusnya</th>
                            @endif
                            <th class="num">Uang fisik</th>
                            <th class="num">Selisih</th>
                            <th class="num">Setoran tutup</th>
                            <th class="num">Total disetor/keluar</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            @php($isOpen = $session->status === 'open')
                            @php($sessionSummary = $isOpen ? $session->summary() : [
                                'cash_sales' => $session->cash_sales,
                                'cash_out' => $session->cash_out,
                                'expected_cash' => $session->expected_cash,
                            ])
                            @php($breakdown = $breakdowns[$session->id] ?? \App\Models\CashierSession::emptyBreakdown())
                            @php($difference = $isOpen ? null : (float) $session->discrepancy)
                            <tr class="ch-main">
                                <td class="ch-period">
                                    <strong>{{ $session->opened_at?->format('d/m/Y H:i') }}</strong>
                                    <small>s/d {{ $session->closed_at?->format('d/m/Y H:i') ?? 'masih berjalan' }}</small>
                                    <button type="button" class="btn btn-default btn-xs ch-toggle" data-target="#ch-detail-{{ $session->id }}" style="margin-top:6px;"><i class="fa fa-chevron-down"></i> Rincian</button>
                                </td>
                                <td class="ch-outlet">
                                    <strong>{{ $session->outlet?->name }}</strong>
                                    <small>Kassa (akun): {{ $session->cashier?->name ?? 'User dihapus' }}</small>
                                    <ul class="ch-shifts">
                                        @forelse ($session->shifts as $shift)
                                            <li>&bull; {{ $shift->name }} ({{ $shift->started_at?->format('H:i') }}–{{ $shift->ended_at?->format('H:i') ?? 'sekarang' }})</li>
                                        @empty
                                            <li>Nama kasir: —</li>
                                        @endforelse
                                    </ul>
                                </td>
                                @if ($canSeeCash)
                                    <td class="num ch-income">{{ $rp($breakdown['sales_total'] ?? 0) }}</td>
                                    <td class="num">@currency($sessionSummary['cash_sales'] ?? 0)</td>
                                @endif
                                <td class="num text-danger">@currency($sessionSummary['cash_out'] ?? 0)</td>
                                @if ($canSeeCash)
                                    <td class="num">@currency($sessionSummary['expected_cash'] ?? 0)</td>
                                @endif
                                <td class="num">{!! $isOpen ? '<span class="text-muted">—</span>' : $rp($session->closing_cash) !!}</td>
                                <td class="num {{ $difference === null ? 'text-muted' : ($difference == 0 ? 'text-success' : 'text-danger') }}">
                                    @if ($difference === null)
                                        —
                                    @elseif ($difference == 0)
                                        <span class="label label-success">Pas</span>
                                    @else
                                        <strong>{{ $difference > 0 ? '+' : '-' }} {{ $rp(abs($difference)) }}</strong>
                                    @endif
                                </td>
                                <td class="num">{!! $isOpen ? '<span class="text-muted">—</span>' : $rp($session->cash_removed) !!}</td>
                                <td class="num">@currency(($sessionSummary['cash_out'] ?? 0) + (float) ($session->cash_removed ?? 0))</td>
                                <td class="ch-actions">
                                    @if ($isOpen)
                                        <span class="label label-success">OPEN</span>
                                    @else
                                        <span class="label label-default">CLOSED</span><br>
                                        <a href="{{ route('cashier.print-close', ['cashierSession' => $session->id, 'reprint' => 1, 'auto' => 1, 'from' => 'history']) }}" class="btn btn-warning btn-xs" title="Cetak ulang ringkasan tutup kasir"><i class="fa fa-print"></i> Re-Print</a>
                                        @if ($session->canBeEditedBy($authUser))
                                            <button type="button" class="btn btn-primary btn-xs js-edit-session"
                                                data-action="{{ route('cashier.update-close', $session) }}"
                                                data-difference-url="{{ route('cashier.close-difference', $session) }}"
                                                data-closing="{{ (float) $session->closing_cash }}"
                                                data-carry="{{ (float) $session->carry_over_cash }}"
                                                data-note="{{ $session->closing_note }}"
                                                data-edits="{{ (int) $session->edit_count }}"
                                                @if ($canSeeCash) data-expected="{{ (float) ($session->expected_cash ?? 0) }}" @endif
                                                data-title="{{ $session->opened_at?->format('d/m/Y H:i') }} - {{ $session->outlet?->name }}"
                                                title="Edit uang fisik, saldo besok, dan catatan"><i class="fa fa-pencil"></i> Edit</button>
                                        @endif
                                        @if ((int) $session->edit_count > 0)
                                            <br><small class="text-muted">Diedit {{ (int) $session->edit_count }} kali</small>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            <tr class="ch-detail is-hidden" id="ch-detail-{{ $session->id }}">
                                <td colspan="{{ $colspan }}">
                                    @include('cashier._breakdown', ['breakdown' => $breakdown, 'showCash' => $canSeeCash, 'groupPayments' => true])
                                    <div class="ch-note">
                                        <span class="ch-note-title">Catatan:</span> {{ $session->closing_note ?: $session->opening_note ?: '-' }}
                                        @foreach ($editLogs[$session->id] ?? [] as $log)
                                            <div class="small text-muted" style="margin-top:3px;">
                                                <i class="fa fa-pencil"></i> Edit ke-{{ $log->properties['edit_number'] }} oleh {{ $log->causer?->name ?? 'System' }} ({{ $log->created_at?->format('d/m/Y H:i') }}):
                                                uang fisik {{ $rp($log->properties['old']['closing_cash'] ?? 0) }} &rarr; {{ $rp($log->properties['attributes']['closing_cash'] ?? 0) }},
                                                saldo besok {{ $rp($log->properties['old']['carry_over_cash'] ?? 0) }} &rarr; {{ $rp($log->properties['attributes']['carry_over_cash'] ?? 0) }},
                                                catatan "{{ $log->properties['old']['closing_note'] ?? '-' }}" &rarr; "{{ $log->properties['attributes']['closing_note'] ?? '-' }}"
                                            </div>
                                        @endforeach
                                        @if ($session->drawerEntries->isNotEmpty())
                                            <div class="ch-drawer"><span class="ch-note-title">Aktivitas drawer:</span>
                                                @foreach ($session->drawerEntries as $entry)
                                                    <span class="ch-entry">
                                                        @if ($entry->type === 'check')
                                                            &bull; Cek fisik Rp {{ number_format($entry->amount, 0, ',', '.') }} (selisih {{ $entry->difference >= 0 ? '+' : '' }}Rp {{ number_format($entry->difference, 0, ',', '.') }}) — {{ $entry->note }}
                                                        @elseif ($entry->type === 'cash_in')
                                                            &bull; Kas masuk Rp {{ number_format($entry->amount, 0, ',', '.') }} — {{ $entry->note }}
                                                        @elseif ($entry->type === 'refund')
                                                            &bull; Uang kembali retur Rp {{ number_format($entry->amount, 0, ',', '.') }} — {{ $entry->note }}
                                                        @else
                                                            &bull; BON Rp {{ number_format($entry->amount, 0, ',', '.') }}{{ filled($entry->note) ? ' — ' . $entry->note : '' }}
                                                            <a href="{{ route('cashier.print-bon', ['entry' => $entry->id, 'reprint' => 1, 'auto' => 1, 'from' => 'history']) }}" class="btn btn-warning btn-xs" title="Cetak ulang BON"><i class="fa fa-print"></i> Re-Print</a>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $colspan }}" class="text-center text-muted">Belum ada history kasir.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($sessions->hasPages())
                <div class="box-footer clearfix">
                    <span class="text-muted small pull-left" style="margin-top:8px;">Menampilkan {{ $sessions->firstItem() }}–{{ $sessions->lastItem() }} dari {{ $sessions->total() }} sesi</span>
                    <div class="pull-right">{{ $sessions->links() }}</div>
                </div>
            @endif
        </div>

        {{-- <div class="box"> --}}
            {{-- <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-history"></i> Activitylog kasir</h3></div> --}}
            {{-- <div class="box-body table-responsive"> --}}
                {{-- <table class="table table-condensed table-striped"> --}}
                    {{-- <thead><tr><th>Waktu</th><th>Aktivitas</th><th>User</th><th>Detail</th></tr></thead> --}}
                    {{-- <tbody> --}}
                        {{-- @forelse ($activities as $activity) --}}
                            {{-- <tr> --}}
                                {{-- <td>{{ $activity->created_at?->format('d/m/Y H:i:s') }}</td> --}}
                                {{-- <td>{{ $activity->description }}</td> --}}
                                {{-- <td>{{ $activity->causer?->name ?? 'System' }}</td> --}}
                                {{-- <td><code style="white-space:normal;">{{ json_encode($activity->properties, JSON_UNESCAPED_UNICODE) }}</code></td> --}}
                            {{-- </tr> --}}
                        {{-- @empty --}}
                            {{-- <tr><td colspan="4" class="text-center text-muted">Belum ada activitylog kasir.</td></tr> --}}
                        {{-- @endforelse --}}
                    {{-- </tbody> --}}
                {{-- </table> --}}
            {{-- </div> --}}
        {{-- </div> --}}
    </section>

        {{-- Modal edit sesi yang sudah ditutup; field sama dengan form Tutup kasir. --}}
        <div class="modal fade" id="editSessionModal" tabindex="-1" role="dialog" aria-labelledby="editSessionModalLabel">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <form id="editSessionForm" action="#" method="POST" autocomplete="off">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title" id="editSessionModalLabel"><i class="fa fa-pencil"></i> Edit tutup kasir</h4>
                            <small class="text-muted" id="editSessionTitle"></small>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning" id="editSessionLimitInfo" style="display:none;"></div>
                            <table class="table table-condensed table-bordered" style="margin-bottom:10px;">
                                <tbody>
                                    @if ($canSeeCash)
                                        <tr class="info"><th>Uang hasil penjualan yang seharusnya ada</th><th class="text-right" id="editExpectedText">Rp 0</th></tr>
                                    @endif
                                    {{-- Role kasir tidak melihat selisih di layar (sama seperti form tutup kasir); selisih tetap tercetak di slip. --}}
                                    @if (auth()->user()?->role !== 'kasir')
                                        <tr id="editDifferenceRow"><td>Selisih dengan uang fisik</td><td class="text-right"><strong id="editDifferenceText">isi uang fisik</strong></td></tr>
                                    @endif
                                    <tr><td>Total uang di laci setelah tutup <small class="text-muted">(uang fisik + saldo besok)</small></td><td class="text-right"><strong id="editDrawerTotal">Rp 0</strong></td></tr>
                                </tbody>
                            </table>
                            <div class="row">
                                <div class="col-sm-6 form-group">
                                    <label for="edit_closing_cash">Uang fisik hasil penjualan tunai</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">Rp</span>
                                        <input id="edit_closing_cash" type="text" inputmode="numeric" name="closing_cash" class="form-control" data-currency-input data-currency-decimals="0" autocomplete="off" required>
                                    </div>
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="edit_carry_over_cash">Saldo disisakan besok</label>
                                    <div class="input-group">
                                        <span class="input-group-addon">Rp</span>
                                        <input id="edit_carry_over_cash" type="text" inputmode="numeric" name="carry_over_cash" class="form-control" data-currency-input data-currency-decimals="0" autocomplete="off" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="edit_closing_note">Catatan</label>
                                <textarea id="edit_closing_note" name="closing_note" class="form-control" rows="2" maxlength="1000"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary" id="editSessionSubmit"><i class="fa fa-save"></i> Simpan perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
@endsection

@section('page-script')
    <script>
        window.CASH_KASIR_EDIT_LIMIT = {{ \App\Models\CashierSession::KASIR_EDIT_LIMIT }};
        window.CASH_ROLE = @json(auth()->user()?->role);
        window.CASH_CAN_SEE = @json((bool) ($canSeeCash ?? false));
    </script>
    @include('cashier._edit-session-script')
    <script>
        (function () {
            var toggles = document.querySelectorAll('.ch-toggle');
            function setOpen(btn, open) {
                var row = document.querySelector(btn.getAttribute('data-target'));
                if (!row) return;
                row.classList.toggle('is-hidden', !open);
                btn.classList.toggle('is-open', open);
            }
            toggles.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setOpen(btn, btn.classList.contains('is-open') ? false : true);
                });
            });
            var all = document.getElementById('chToggleAll');
            if (all) {
                var opened = false;
                all.addEventListener('click', function () {
                    opened = !opened;
                    toggles.forEach(function (btn) { setOpen(btn, opened); });
                    all.innerHTML = opened
                        ? '<i class="fa fa-compress"></i> Tutup semua rincian'
                        : '<i class="fa fa-expand"></i> Buka semua rincian';
                });
            }
        })();
    </script>
@endsection