@extends('layouts.master')

@section('title', 'History Kasir')

@section('container')
    <section class="content-header">
        <h1>History Kasir <small>Open, BON, dan tutup cash drawer</small></h1>
    </section>
    <section class="content">
        <div class="box">
            <div class="box-header with-border">
                <a href="{{ route('penjualan.index') }}" class="btn btn-default"><i class="fa fa-arrow-left"></i> Kembali ke penjualan</a>
                <span class="text-muted" style="margin-left:10px;">Semua aktivitas dicatat dengan Spatie Activitylog.</span>
            </div>
            <div class="box-body table-responsive">
                @php($rp = fn ($number) => 'Rp ' . number_format((float) $number, 0, ',', '.'))
                @php($canSeeCash = $canSeeCash ?? false)
                @php($colspan = $canSeeCash ? 12 : 10)
                @php($authUser = auth()->user())
                @php($editLogs = $canSeeCash
                    ? $activities->filter(fn ($a) => $a->subject_type === \App\Models\CashierSession::class && isset($a->properties['edit_number']))->groupBy('subject_id')
                    : collect())
                <p class="text-muted small" style="margin-bottom:8px;">
                    <strong>Uang fisik</strong> = uang hasil penjualan tunai (ditambah kas masuk, dikurangi BON), dihitung terpisah dari
                    <strong>saldo besok</strong>. <strong>Selisih</strong> = uang fisik &minus; kas seharusnya.
                    Sesi yang ditutup sebelum perhitungan ini diperbarui tetap memakai angka lama.
                </p>
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <th>Outlet / Kassa</th>
                            <th>Saldo awal</th>
                            @if ($canSeeCash)
                                <th>Penjualan tunai</th>
                            @endif
                            <th>BON</th>
                            @if ($canSeeCash)
                                <th>Kas seharusnya</th>
                            @endif
                            <th>Uang fisik</th>
                            <th>Selisih</th>
                            <th>Saldo besok</th>
                            <th>Setoran tutup</th>
                            <th>Total disetor/keluar</th>
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
                            <tr>
                                <td>
                                    {{ $session->opened_at?->format('d/m/Y H:i') }}<br>
                                    <small class="text-muted">{{ $session->closed_at?->format('d/m/Y H:i') ?? 'masih berjalan' }}</small>
                                </td>
                                <td>
                                    {{ $session->outlet?->name }}<br>
                                    <small>Kassa (akun): {{ $session->cashier?->name ?? 'User dihapus' }}</small><br>
                                    <small>Nama kasir:</small>
                                    @forelse ($session->shifts as $shift)
                                        <br><small>&bull; {{ $shift->name }} ({{ $shift->started_at?->format('H:i') }}–{{ $shift->ended_at?->format('H:i') ?? 'sekarang' }})</small>
                                    @empty
                                        <small>—</small>
                                    @endforelse
                                </td>
                                <td>@currency($session->opening_cash)</td>
                                @if ($canSeeCash)
                                    <td>@currency($sessionSummary['cash_sales'] ?? 0)</td>
                                @endif
                                <td class="text-danger">@currency($sessionSummary['cash_out'] ?? 0)</td>
                                @if ($canSeeCash)
                                    <td>@currency($sessionSummary['expected_cash'] ?? 0)</td>
                                @endif
                                <td>{!! $isOpen ? '<span class="text-muted">—</span>' : $rp($session->closing_cash) !!}</td>
                                <td class="{{ $difference === null ? 'text-muted' : ($difference == 0 ? 'text-success' : 'text-danger') }}">
                                    @if ($difference === null)
                                        —
                                    @elseif ($difference == 0)
                                        <strong>Pas</strong>
                                    @else
                                        <strong>{{ $difference > 0 ? '+' : '-' }} {{ $rp(abs($difference)) }}</strong>
                                    @endif
                                </td>
                                <td>{!! $isOpen ? '<span class="text-muted">—</span>' : $rp($session->carry_over_cash) !!}</td>
                                <td>{!! $isOpen ? '<span class="text-muted">—</span>' : $rp($session->cash_removed) !!}</td>
                                <td>@currency(($sessionSummary['cash_out'] ?? 0) + (float) ($session->cash_removed ?? 0))</td>
                                <td>
                                    @if ($isOpen)
                                        <span class="label label-success">OPEN</span>
                                    @else
                                        <span class="label label-default">CLOSED</span><br>
                                        <a href="{{ route('cashier.print-close', ['cashierSession' => $session->id, 'reprint' => 1, 'auto' => 1, 'from' => 'history']) }}" class="btn btn-warning btn-xs" style="margin-top:4px;" title="Cetak ulang ringkasan tutup kasir"><i class="fa fa-print"></i> Re-Print</a>
                                        @if ($session->canBeEditedBy($authUser))
                                            <button type="button" class="btn btn-primary btn-xs js-edit-session" style="margin-top:4px;"
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
                            <tr class="active">
                                <td colspan="{{ $colspan }}">
                                    @include('cashier._breakdown', ['breakdown' => $breakdown, 'showCash' => $canSeeCash])
                                    <strong>Catatan:</strong> {{ $session->closing_note ?: $session->opening_note ?: '-' }}
                                    @foreach ($editLogs[$session->id] ?? [] as $log)
                                        <div class="small text-muted" style="margin-top:3px;">
                                            <i class="fa fa-pencil"></i> Edit ke-{{ $log->properties['edit_number'] }} oleh {{ $log->causer?->name ?? 'System' }} ({{ $log->created_at?->format('d/m/Y H:i') }}):
                                            uang fisik {{ $rp($log->properties['old']['closing_cash'] ?? 0) }} &rarr; {{ $rp($log->properties['attributes']['closing_cash'] ?? 0) }},
                                            saldo besok {{ $rp($log->properties['old']['carry_over_cash'] ?? 0) }} &rarr; {{ $rp($log->properties['attributes']['carry_over_cash'] ?? 0) }},
                                            catatan "{{ $log->properties['old']['closing_note'] ?? '-' }}" &rarr; "{{ $log->properties['attributes']['closing_note'] ?? '-' }}"
                                        </div>
                                    @endforeach
                                    @if ($session->drawerEntries->isNotEmpty())
                                        <span style="margin-left:18px;"><strong>Aktivitas drawer:</strong>
                                            @foreach ($session->drawerEntries as $entry)
                                                @if ($entry->type === 'check')
                                                    Cek fisik Rp {{ number_format($entry->amount, 0, ',', '.') }} (selisih {{ $entry->difference >= 0 ? '+' : '' }}Rp {{ number_format($entry->difference, 0, ',', '.') }}) — {{ $entry->note }}
                                                @elseif ($entry->type === 'cash_in')
                                                    Kas masuk Rp {{ number_format($entry->amount, 0, ',', '.') }} — {{ $entry->note }}
                                                @else
                                                    BON Rp {{ number_format($entry->amount, 0, ',', '.') }}{{ filled($entry->note) ? ' — ' . $entry->note : '' }}
                                                    <a href="{{ route('cashier.print-bon', ['entry' => $entry->id, 'reprint' => 1, 'auto' => 1, 'from' => 'history']) }}" class="btn btn-warning btn-xs" title="Cetak ulang BON"><i class="fa fa-print"></i> Re-Print</a>
                                                @endif{{ $loop->last ? '' : '; ' }}
                                            @endforeach
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $colspan }}" class="text-center text-muted">Belum ada history kasir.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
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
                                    <tr id="editDifferenceRow"><td>Selisih dengan uang fisik</td><td class="text-right"><strong id="editDifferenceText">isi uang fisik</strong></td></tr>
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
@endsection