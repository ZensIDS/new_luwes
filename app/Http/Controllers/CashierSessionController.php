<?php

namespace App\Http\Controllers;

use App\Models\CashierDrawerEntry;
use App\Models\CashierSession;
use App\Models\CashierShift;
use App\Models\Outlet;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\IndonesianNumber;
use Spatie\Activitylog\Models\Activity;

class CashierSessionController extends Controller
{
    public function open(Request $request, Outlet $outlet)
    {
        $request->merge(['outlet_id' => $outlet->id]);
        OutletAccess::id($request);
        $request->merge([
            'opening_cashier_name' => trim((string) $request->input('opening_cashier_name')),
        ]);

        $request->merge([
            'opening_cash' => IndonesianNumber::parse($request->input('opening_cash')),
        ]);

        $data = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'opening_note' => ['nullable', 'string', 'max:1000'],
            'opening_cashier_name' => ['required', 'string', 'max:150'],
        ]);

        if (CashierSession::query()
            ->where('outlet_id', $outlet->id)
            ->where('cashier_id', $request->user()->getAuthIdentifier())
            ->where('status', 'open')
            ->exists()) {
            return redirect()->route('outlet.show', $outlet)
                ->with('toast_error', 'Kasir untuk user ini sudah terbuka.');
        }

        DB::transaction(function () use ($request, $outlet, $data): void {
            $session = CashierSession::create([
                'outlet_id' => $outlet->id,
                'cashier_id' => $request->user()->getAuthIdentifier(),
                'status' => 'open',
                'opening_cash' => $data['opening_cash'],
                'opening_note' => $data['opening_note'] ?? null,
                'opened_at' => now(),
            ]);

            CashierShift::create([
                'cashier_session_id' => $session->id,
                'created_by' => $request->user()->getAuthIdentifier(),
                'name' => trim($data['opening_cashier_name']),
                'started_at' => now(),
            ]);
        });

        return redirect()->route('outlet.show', $outlet)
            ->with('toast_success', 'Kasir berhasil dibuka. Periksa cash drawer sebelum mulai menjual.');
    }

    public function changeShift(Request $request, CashierSession $cashierSession)
    {
        $this->ensureOperationAccess($cashierSession);
        abort_unless($cashierSession->status === 'open', 422, 'Sesi kasir sudah ditutup.');
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
        ]);

        DB::transaction(function () use ($request, $cashierSession, $data): void {
            $session = CashierSession::query()->lockForUpdate()->findOrFail($cashierSession->id);
            abort_unless($session->status === 'open', 422, 'Sesi kasir sudah ditutup.');

            $now = now();
            CashierShift::query()
                ->where('cashier_session_id', $session->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => $now]);

            CashierShift::create([
                'cashier_session_id' => $session->id,
                'created_by' => $request->user()->getAuthIdentifier(),
                'name' => trim($data['name']),
                'started_at' => $now,
            ]);
        });

        return redirect()->route('outlet.show', $cashierSession->outlet_id)
            ->with('toast_success', 'Shift berhasil diganti tanpa logout dari akun kassa.');
    }

    public function entry(Request $request, CashierSession $cashierSession)
    {
        $this->ensureOperationAccess($cashierSession);
        abort_unless($cashierSession->status === 'open', 422, 'Sesi kasir sudah ditutup.');

        $request->merge([
            'amount' => IndonesianNumber::parse($request->input('amount')),
        ]);

        $data = $request->validate([
            'type' => ['required', 'in:bon,check'],
            'amount' => ['required', 'numeric', $request->input('type') === 'bon' ? 'gt:0' : 'min:0'],
            // Catatan wajib hanya untuk cek drawer; BON cukup nominal.
            'note' => [$request->input('type') === 'bon' ? 'nullable' : 'required', 'string', 'max:1000'],
        ]);

        $summary = $cashierSession->summary();
        $isCheck = $data['type'] === 'check';

        $entry = CashierDrawerEntry::create([
            'cashier_session_id' => $cashierSession->id,
            'outlet_id' => $cashierSession->outlet_id,
            'cashier_id' => $request->user()->getAuthIdentifier(),
            'type' => $data['type'],
            'amount' => $data['amount'],
            'expected_cash' => $isCheck ? $summary['expected_cash'] : null,
            'difference' => $isCheck ? ((float) $data['amount'] - $summary['expected_cash']) : null,
            'note' => $data['note'] ?? null,
            'recorded_at' => now(),
        ]);

        // BON langsung dicetak otomatis (QZ Tray); cek drawer tidak dicetak.
        $redirect = $isCheck
            ? redirect()->route('outlet.show', $cashierSession->outlet_id)
            : redirect()->route('cashier.print-bon', ['entry' => $entry->id, 'auto' => 1]);

        return $redirect
            ->with('toast_success', $isCheck ? 'Cek cash drawer berhasil dicatat.' : 'BON / uang keluar berhasil dicatat.');
    }

    public function close(Request $request, CashierSession $cashierSession)
    {
        $this->ensureOperationAccess($cashierSession);
        abort_unless($cashierSession->status === 'open', 422, 'Sesi kasir sudah ditutup.');

        $data = $request->validate([
            'closing_cash' => ['required', 'numeric', 'min:0'],
            'carry_over_cash' => ['required', 'numeric', 'min:0'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);

        // closing_cash = uang fisik HASIL PENJUALAN (tunai + kas masuk - BON), dihitung terpisah dari
        // carry_over_cash (saldo yang disisakan untuk besok). Keduanya tidak saling mengurangi.
        $closingCash = (float) $data['closing_cash'];

        DB::transaction(function () use ($cashierSession, $data, $closingCash) {
            $cashierSession = CashierSession::query()->lockForUpdate()->findOrFail($cashierSession->id);
            abort_unless($cashierSession->status === 'open', 422, 'Sesi kasir sudah ditutup.');
            $summary = $cashierSession->summary();
            CashierShift::query()
                ->where('cashier_session_id', $cashierSession->id)
                ->whereNull('ended_at')
                ->update(['ended_at' => now()]);
            $cashierSession->update([
                'status' => 'closed',
                'closing_cash' => $closingCash,
                'expected_cash' => $summary['expected_cash'],
                'cash_sales' => $summary['cash_sales'],
                'cash_in' => $summary['cash_in'],
                'cash_out' => $summary['cash_out'],
                // Seluruh uang hasil penjualan dibawa keluar (setoran); saldo besok tetap di laci.
                'cash_removed' => $closingCash,
                'carry_over_cash' => $data['carry_over_cash'],
                'discrepancy' => $closingCash - $summary['expected_cash'],
                'closing_note' => $data['closing_note'] ?? null,
                'closed_at' => now(),
            ]);
        });

        // Setelah tutup kasir, langsung cetak ringkasan (auto), lalu kembali ke halaman penjualan.
        return redirect()->route('cashier.print-close', ['cashierSession' => $cashierSession->id, 'auto' => 1])
            ->with('toast_success', 'Kasir ditutup. Ringkasan cash drawer tersimpan di history kasir.');
    }

    public function history(Request $request)
    {
        $outletId = OutletAccess::id($request, false);
        $query = CashierSession::with([
            'outlet', 'cashier', 'shifts', 'drawerEntries',
        ])
            ->orderByDesc('opened_at');
        if ($outletId) {
            $query->where('outlet_id', $outletId);
        }
        $sessions = $query->get();

        $sessionIds = $sessions->pluck('id');
        $entryIds = $sessions->flatMap(fn (CashierSession $session) => $session->drawerEntries->pluck('id'));
        $activities = Activity::query()
            ->where('log_name', 'Cashier')
            ->with('causer')
            ->when($outletId, function ($activityQuery) use ($sessionIds, $entryIds) {
                $activityQuery->where(function ($query) use ($sessionIds, $entryIds) {
                    $query->where(function ($subjectQuery) use ($sessionIds) {
                        $subjectQuery->where('subject_type', CashierSession::class)
                            ->whereIn('subject_id', $sessionIds->all() ?: [0]);
                    })->orWhere(function ($subjectQuery) use ($entryIds) {
                        $subjectQuery->where('subject_type', CashierDrawerEntry::class)
                            ->whereIn('subject_id', $entryIds->all() ?: [0]);
                    });
                });
            })
            ->latest()
            ->limit(100)
            ->get();

        // Rincian per metode bayar + voucher untuk semua sesi (2 query, bukan per baris).
        $breakdowns = CashierSession::breakdownFor($sessions->pluck('id')->all());

        return view('cashier.history', compact('sessions', 'activities', 'breakdowns'));
    }

    private function ensureOperationAccess(CashierSession $cashierSession): void
    {
        $user = auth()->user();
        if (in_array($user?->role, ['staff-outlet', 'kasir'], true)) {
            abort_unless((int) $cashierSession->cashier_id === (int) $user->getAuthIdentifier(), 403);
            abort_unless((int) $cashierSession->outlet_id === (int) $user->outlet_id, 403);
        }
    }
}