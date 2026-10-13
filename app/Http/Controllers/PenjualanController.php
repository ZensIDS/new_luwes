<?php

namespace App\Http\Controllers;

use App\Http\Requests\PenjualanRequest;
use App\Models\Kas;
use App\Models\Outlet;
use App\Models\Penjualan;
use App\Models\Stock;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CashierSaleService;
use App\Services\SaleCancellationService;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PDF;
use RuntimeException;

class PenjualanController extends Controller
{
    public function getPenjualan($outlet_id)
    {
        $penjualans = Penjualan::where('outlet_id', $outlet_id)->get();

        return response()->json($penjualans);
    }

    public function getItems($penjualan_id)
    {
        $penjualan = Penjualan::find($penjualan_id);
        if ($penjualan) {
            $items = $penjualan->items;

            return response()->json($items);
        } else {
            return response()->json([], 404);
        }
    }

    public function marketplace()
    {
        return view('penjualan.marketplace', [
            'penjualan' => Penjualan::has('transaction')->orderBy('created_at', 'desc')->get(),
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $isCashier = $user->role === 'kasir';
        $isStaffOutlet = $user->role === 'staff-outlet';

        // Akun yang boleh dipilih di filter (hanya role kasir):
        // - kasir        : hanya dirinya sendiri
        // - staff-outlet : kasir di outlet-nya saja
        // - lainnya      : semua akun kasir
        $cashiers = User::query()
            ->where('role', 'kasir')
            ->when($isCashier, fn ($q) => $q->whereKey($user->id))
            ->when($isStaffOutlet, fn ($q) => $q->where('outlet_id', $user->outlet_id))
            ->with('outlet:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'outlet_id']);

        // Kasir otomatis terkunci ke akunnya sendiri (input request diabaikan).
        // Role lain default kosong dan harus memilih akun dulu.
        $selectedCashierId = $isCashier ? $user->id : $request->input('kasir_id');
        if ($selectedCashierId && ! $cashiers->contains('id', (int) $selectedCashierId)) {
            $selectedCashierId = null;
        }

        return view('penjualan.index', [
            'cashiers' => $cashiers,
            'selectedCashierId' => $selectedCashierId ? (int) $selectedCashierId : null,
            'isCashier' => $isCashier,
            'canManageSales' => ! $isStaffOutlet,
        ]);
    }

    /**
     * Server-side DataTables untuk riwayat penjualan per akun kasir.
     * Default rentang tanggal = hari ini; hanya satu halaman (maks 100 baris) yang dikirim,
     * relasi hanya dimuat untuk baris di halaman itu. Search berbasis AWALAN (LIKE 'kata%'):
     * kode invoice, serta kode/nama produk dan serial number pada item.
     */
    public function getIndexData(Request $request, SaleCancellationService $cancellation)
    {
        $user = $request->user();
        $isCashier = $user->role === 'kasir';
        $isStaffOutlet = $user->role === 'staff-outlet';
        $canManageSales = ! $isStaffOutlet;

        $draw = (int) $request->input('draw');
        $empty = fn () => response()->json([
            'draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [],
        ]);

        $kasirId = $isCashier ? (int) $user->id : (int) $request->input('kasir_id');
        if (! $kasirId) {
            return $empty();
        }

        // Akun yang boleh dilihat sama seperti dropdown di index().
        $allowed = User::query()
            ->whereKey($kasirId)
            ->where('role', 'kasir')
            ->when($isStaffOutlet, fn ($q) => $q->where('outlet_id', $user->outlet_id))
            ->exists();
        if (! $allowed) {
            return $empty();
        }

        [$from, $to] = $this->resolveDateRange($request->input('date_from'), $request->input('date_to'));

        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 25);
        $length = $length > 0 ? min($length, 100) : 25;
        $searchValue = trim((string) $request->input('search.value', ''));
        $prefix = $searchValue !== '' ? addcslashes($searchValue, '\\%_') . '%' : null;

        $hasOrder = $request->has('order.0.column');
        $orderDir = $hasOrder && strtolower((string) $request->input('order.0.dir')) === 'asc' ? 'asc' : 'desc';
        $sortable = [1 => 'penjualans.created_at', 2 => 'penjualans.code'];
        $orderBy = $hasOrder ? ($sortable[(int) $request->input('order.0.column')] ?? 'penjualans.created_at') : 'penjualans.created_at';

        // Tidak memakai doesntHave('transaction'): penjualan POS yang memilih metode bayar ikut membuat
        // record Transaction. Penjualan marketplace tidak punya kasir_id, jadi tidak masuk filter ini.
        $base = Penjualan::query()
            ->where('penjualans.kasir_id', (string) $kasirId)
            ->whereBetween('penjualans.created_at', [$from, $to])
            ->when($isStaffOutlet, fn ($q) => $q->where('penjualans.outlet_id', $user->outlet_id));

        $recordsTotal = (clone $base)->count('penjualans.id');

        if ($prefix !== null) {
            // EXISTS berkorelasi: dicek hanya untuk penjualan pada rentang tanggal (kecil), bukan
            // mengumpulkan seluruh riwayat penjualan produk tsb. Tidak menggandakan baris.
            $base->where(function ($q) use ($prefix) {
                $q->where('penjualans.code', 'like', $prefix)
                    ->orWhereExists(function ($sub) use ($prefix) {
                        $sub->select(DB::raw(1))
                            ->from('penjualan_items as pi')
                            ->join('products as p', 'p.id', '=', 'pi.product_id')
                            ->whereColumn('pi.penjualan_id', 'penjualans.id')
                            ->whereNull('pi.deleted_at')
                            ->where(function ($w) use ($prefix) {
                                $w->where('p.name', 'like', $prefix)
                                    ->orWhere('p.code', 'like', $prefix)
                                    ->orWhere('pi.serial_number', 'like', $prefix);
                            });
                    });
            });
        }

        $recordsFiltered = $prefix === null ? $recordsTotal : (clone $base)->count('penjualans.id');

        // Deferred join: urutkan/paginate hanya kolom id, baru muat baris lengkap untuk halaman ini.
        $pageIds = $base
            ->orderBy($orderBy, $orderDir)
            ->orderBy('penjualans.id', $orderDir)
            ->offset($start)
            ->limit($length)
            ->pluck('penjualans.id');

        $order = $pageIds->flip();
        $penjualans = Penjualan::with([
            'outlet:id,name',
            'kasir:id,name',
            'cashierShift',
            'cashierSession:id,status',
            'voucher',
            // withTrashed: produk yang sudah di-soft-delete tetap tampil di riwayat penjualan
            'items.product' => fn ($q) => $q->withTrashed()->select('id', 'code', 'name'),
        ])
            ->withCount('refundPenjualans')
            ->whereIn('id', $pageIds)
            ->get()
            ->sortBy(fn ($penjualan) => $order[$penjualan->id])
            ->values();

        $data = $penjualans->map(function ($value) use ($canManageSales, $cancellation, $user) {
            $aksi = '<a class="btn btn-info" href="' . e(route('penjualan.show', $value->id)) . '">Show</a> '
                . '<a class="btn btn-warning" href="' . e(route('penjualan.print', $value->id)) . '">Re-Print</a>';
            if ($canManageSales) {
                $block = $cancellation->blockReason($value, $user);
                if ($block) {
                    $aksi .= ' <button type="button" class="border-0 btn btn-danger" disabled title="' . e($block) . '">Hapus</button>';
                } else {
                    $aksi .= ' <form action="' . e(route('penjualan.destroy', $value->id)) . '" method="post" class="form-delete-sale" style="display:inline;"'
                        . ' data-code="' . e($value->code) . '"'
                        . ' data-items="' . (int) $value->items->count() . '"'
                        . ' data-qty="' . (int) $value->items->sum('qty') . '">'
                        . method_field('delete') . csrf_field()
                        . '<input type="hidden" name="reason" value="">'
                        . '<button type="button" class="border-0 btn btn-danger btn-delete-sale">Hapus</button></form>';
                }
            }

            return [
                '',
                e($value->created_at?->format('d/m/Y H:i')),
                e($value->code),
                e($value->outlet->name ?? '___customer'),
                e($value->kasir->name ?? '___customer'),
                e($value->cashierShift?->name ?? '—'),
                view('penjualan._detail', ['value' => $value])->render(),
                $aksi,
            ];
        });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    /**
     * date_from / date_to (Y-m-d). Default hari ini; tanggal tidak valid diabaikan; urutan dibalik bila terbalik.
     */
    private function resolveDateRange($from, $to): array
    {
        $parse = function ($value) {
            try {
                return $value ? Carbon::createFromFormat('Y-m-d', (string) $value) : null;
            } catch (\Throwable $e) {
                return null;
            }
        };

        $start = $parse($from) ?? ($parse($to) ?? now());
        $end = $parse($to) ?? $start;
        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->copy()->startOfDay(), $end->copy()->endOfDay()];
    }

    /**
     * Kasir hanya boleh membuka penjualannya sendiri; staff-outlet hanya
     * penjualan di outlet-nya.
     */
    private function ensurePenjualanAccess(Penjualan $penjualan): void
    {
        $user = auth()->user();

        if ($user->role === 'kasir') {
            abort_unless((string) $penjualan->kasir_id === (string) $user->id, 403, 'Anda tidak memiliki akses ke penjualan ini.');
        } elseif ($user->role === 'staff-outlet') {
            abort_unless((int) $penjualan->outlet_id === (int) $user->outlet_id, 403, 'Anda tidak memiliki akses ke penjualan ini.');
        }
    }

    public function create()
    {
        if (auth()->user()->role == 'kasir' | auth()->user()->role == 'admin'){
            return redirect()->route('outlet.show', auth()->user()->outlet_id);
        }

        return view('penjualan.create', [
            'outlets' => Outlet::get(),
        ]);
    }

    public function store(Request $request, CashierSaleService $sales)
    {
        // Total, subtotal, diskon, promo, dan kembalian dihitung server dari keranjang
        // (CashierSaleService), jadi tidak diterima dari client. customer_id boleh kosong
        // (pelanggan Umum).
        $data = $request->validate([
            'outlet_id' => 'nullable|integer|exists:outlets,id',
            'customer_id' => 'nullable|exists:users,id',
            'salesman_id' => 'nullable',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|integer|exists:payment_methods,id',
            'payment_method_name' => 'nullable|string|max:255',
            'payment_reference' => 'nullable|string|max:255',
            'voucher_codes' => 'nullable|array',
            'voucher_codes.*' => 'string|max:100',
            'promotion_codes' => 'nullable|array',
            'promotion_codes.*' => 'string|max:100',
        ]);

        // Kasir / staff outlet hanya boleh bertransaksi di outletnya sendiri.
        $data['outlet_id'] = OutletAccess::id($request);

        try {
            $order = $sales->checkout($request->user(), $data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pesanan berhasil dibuat.',
            'redirect' => route('outlet.show', $order->outlet_id),
            'print' => route('penjualan.print', [$order]),
            'order' => $order,
        ], 201);
    }

    public function show(Penjualan $penjualan, SaleCancellationService $cancellation)
    {
        $this->ensurePenjualanAccess($penjualan);
        $penjualan->loadMissing('cashierSession');

        // dd($penjualan->load(['kasir', 'customer', 'items.product'])->toArray());
        // $pdf = PDF::loadView('penjualan.penjualan_pdf', ['penjualan' => $penjualan]);

        // return $pdf->download('penjualan_'.$penjualan->id.'.pdf');
        return view('penjualan.show', [
            'penjualan' => $penjualan,
            'canManageSales' => auth()->user()->role !== 'staff-outlet',
            'deleteBlock' => $cancellation->blockReason($penjualan, auth()->user()),
        ]);
    }

    public function print(Penjualan $penjualan)
    {
        $this->ensurePenjualanAccess($penjualan);

        return view('penjualan.print', [
            'penjualan' => $penjualan,
        ]);
    }

    // public function edit(Penjualan $penjualan)
    // {
    //     return view('penjualan.edit', [
    //         'penjualan' => $penjualan,
    //     ]);
    // }

    // public function update(PenjualanRequest $request, Penjualan $penjualan)
    // {
    //     $data = $request->validated();

    //     $penjualan->update($data);

    //     return redirect(route('penjualan.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    // }

    /**
     * Hapus penjualan = batalkan: stok kembali ke batch asal, voucher bisa dipakai lagi
     * (lihat SaleCancellationService). Alasan wajib untuk penjualan outlet/kasir.
     * Penjualan marketplace (outlet_id null) hanya di-soft-delete seperti sebelumnya.
     */
    public function destroy(Request $request, Penjualan $penjualan, SaleCancellationService $cancellation)
    {
        $this->ensurePenjualanAccess($penjualan);
        // staff-outlet hanya boleh melihat penjualan
        abort_if(auth()->user()->role === 'staff-outlet', 403, 'Staff outlet tidak dapat menghapus penjualan.');

        $validator = Validator::make($request->all(), [
            'reason' => [$penjualan->outlet_id ? 'required' : 'nullable', 'string', 'min:5', 'max:255'],
        ], [
            'reason.required' => 'Alasan penghapusan wajib diisi.',
            'reason.min' => 'Alasan penghapusan minimal 5 karakter.',
            'reason.max' => 'Alasan penghapusan maksimal 255 karakter.',
        ]);
        if ($validator->fails()) {
            return back()->with('toast_error', $validator->errors()->first());
        }

        try {
            $summary = $cancellation->cancel($penjualan, $request->user(), $request->input('reason'));
        } catch (RuntimeException $e) {
            return back()->with('toast_error', $e->getMessage());
        }

        $message = 'Berhasil menghapus penjualan.';
        if ($penjualan->outlet_id !== null) {
            $message = "Penjualan dihapus. Stok {$summary['items']} item (total qty {$summary['qty']}) dikembalikan.";
            if ($summary['skipped'] > 0) {
                $message .= " {$summary['skipped']} item tanpa batch stok tidak dapat dikembalikan otomatis.";
            }
        }

        return redirect(route('penjualan.index'))->with('toast_success', $message);
    }
}