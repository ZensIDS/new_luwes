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
                            <th>Penjualan tunai</th>
                            <th>BON</th>
                            <th>Kas seharusnya</th>
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
                                <td>@currency($sessionSummary['cash_sales'] ?? 0)</td>
                                <td class="text-danger">@currency($sessionSummary['cash_out'] ?? 0)</td>
                                <td>@currency($sessionSummary['expected_cash'] ?? 0)</td>
                                <td>{!! $isOpen ? '<span class="text-muted">—</span>' : $rp($session->closing_cash) !!}</td>
                                <td class="{{ $difference === null ? 'text-muted' : ($difference == 0 ? 'text-success' : 'text-danger') }}">
                                    @if ($difference === null)
                                        —
                                    @elseif ($difference == 0)
                                        <strong>Pas</strong>
                                    @else
                                        <strong>{{ $difference > 0 ? 'Lebih' : 'Kurang' }}</strong><br>
                                        {{ $difference > 0 ? '+' : '-' }}{{ $rp(abs($difference)) }}
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
                                    @endif
                                </td>
                            </tr>
                            <tr class="active">
                                <td colspan="12">
                                    @include('cashier._breakdown', ['breakdown' => $breakdown])
                                    <strong>Catatan:</strong> {{ $session->closing_note ?: $session->opening_note ?: '-' }}
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
                            <tr><td colspan="12" class="text-center text-muted">Belum ada history kasir.</td></tr>
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
@endsection