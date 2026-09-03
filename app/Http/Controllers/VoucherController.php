<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoucherRequest;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use App\Support\OutletAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureManagementAccess();
        $query = Voucher::withCount('redemptions')->with('product')->latest();
        if ($request->wantsJson()) {
            $vouchers = $query
                ->whereDoesntHave('redemptions')
                ->where(function ($query) {
                    $query->whereNull('start_at')->orWhere('start_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('end_at')->orWhere('end_at', '>=', now());
                })
                ->get()
                ->filter(fn (Voucher $voucher) => $voucher->isActive())
                ->values();

            return response()->json($vouchers);
        }

        return view('vouchers.index', ['vouchers' => $query->paginate(50)]);
    }

    public function lookup(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:100',
            'outlet_id' => 'nullable|integer|exists:outlets,id',
        ]);
        $outletId = $request->filled('outlet_id') ? OutletAccess::id($request, false) : null;
        $voucher = Voucher::where('code', strtoupper(trim($request->code)))
            ->when($outletId, fn ($query) => $query->where(function ($scopeQuery) use ($outletId) {
                $scopeQuery->whereNull('outlet_id')->orWhere('outlet_id', $outletId);
            }))
            ->first();

        if (! $voucher || ! $voucher->isActive() || $voucher->redemptions()->exists()) {
            return response()->json(['message' => 'Voucher tidak ditemukan, sudah digunakan, atau tidak aktif.'], 404);
        }

        return response()->json([
            'id' => $voucher->id,
            'name' => $voucher->name,
            'code' => $voucher->code,
            'type' => $voucher->type,
            'value' => $voucher->value,
            'min_purchase' => $voucher->min_purchase,
            'max_discount_amount' => $voucher->max_discount_amount,
            'outlet_id' => $voucher->outlet_id,
            'product_id' => $voucher->product_id,
            'product_name' => $voucher->product?->name,
            'start_at' => $voucher->start_at,
            'end_at' => $voucher->end_at,
        ]);
    }

    public function create()
    {
        $this->ensureManagementAccess();
        return view('vouchers.form', [
            'voucher' => new Voucher(['type' => 'nominal']),
            'kasirs' => User::where('role', 'kasir')->get(),
            'products' => Product::orderBy('name')->get(),
            'outlets' => OutletAccess::outlets(),
            'isEdit' => false,
        ]);
    }

    public function store(VoucherRequest $request)
    {
        $data = $request->validated();
        [$startAt, $endAt] = $this->parseDateRange($request->input('daterange'));
        $baseCode = strtoupper(trim($data['code']));
        $quantity = (int) ($data['quantity'] ?? 1);
        $codes = $this->generatedCodes($baseCode, $quantity);

        if (Voucher::whereIn('code', $codes)->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode voucher atau variannya sudah digunakan.']);
        }

        DB::transaction(function () use ($data, $codes, $startAt, $endAt) {
            foreach ($codes as $code) {
                Voucher::create([
                    'name' => $data['name'],
                    'code' => $code,
                    'type' => $data['type'],
                    'jenis' => 'keseluruhan',
                    'limit' => 1,
                    'value' => $data['value'],
                    'min_purchase' => $data['min_purchase'] ?? 0,
                    'max_discount_amount' => $data['max_discount_amount'] ?? null,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'desc' => $data['desc'] ?? null,
                    'product_id' => $data['product_id'] ?? null,
                'kasir_id' => $data['kasir_id'] ?? null,
                    'outlet_id' => $data['outlet_id'] ?? null,
                ]);
            }
        });

        return redirect()->route('voucher.index')->with('toast_success', "{$quantity} voucher berhasil dibuat.");
    }

    public function show(Voucher $voucher)
    {
        $this->ensureManagementAccess();
        return view('vouchers.show', ['voucher' => $voucher->loadCount('redemptions')]);
    }

    public function edit(Voucher $voucher)
    {
        $this->ensureManagementAccess();
        return view('vouchers.form', [
            'voucher' => $voucher,
            'kasirs' => User::where('role', 'kasir')->get(),
            'products' => Product::orderBy('name')->get(),
            'outlets' => OutletAccess::outlets(),
            'isEdit' => true,
        ]);
    }

    public function update(VoucherRequest $request, Voucher $voucher)
    {
        $data = $request->validated();
        $code = strtoupper(trim($data['code']));
        if (Voucher::where('code', $code)->where('id', '!=', $voucher->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode voucher sudah digunakan.']);
        }
        [$startAt, $endAt] = $this->parseDateRange($request->input('daterange'));

        $voucher->update([
            'name' => $data['name'],
            'code' => $code,
            'type' => $data['type'],
            'value' => $data['value'],
            'min_purchase' => $data['min_purchase'] ?? 0,
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'desc' => $data['desc'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'kasir_id' => $data['kasir_id'] ?? null,
            'outlet_id' => $data['outlet_id'] ?? null,
        ]);

        return redirect()->route('voucher.index')->with('toast_success', 'Voucher berhasil diperbarui.');
    }

    public function destroy(Voucher $voucher)
    {
        $this->ensureManagementAccess();
        if ($voucher->redemptions()->exists()) {
            return redirect()->back()->with('toast_error', 'Voucher yang sudah digunakan tidak dapat dihapus.');
        }
        $voucher->delete();

        return redirect()->route('voucher.index')->with('toast_success', 'Voucher berhasil dihapus.');
    }

    private function generatedCodes(string $baseCode, int $quantity): array
    {
        if ($quantity === 1) {
            return [$baseCode];
        }

        return collect(range(1, $quantity))
            ->map(fn ($number) => $baseCode . '-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT))
            ->all();
    }

    private function parseDateRange(?string $range): array
    {
        if (! $range) {
            return [null, null];
        }
        $parts = array_map('trim', explode(' - ', $range, 2));

        return [
            Carbon::parse($parts[0]),
            isset($parts[1]) ? Carbon::parse($parts[1]) : Carbon::parse($parts[0])->endOfDay(),
        ];
    }

    private function ensureManagementAccess(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['superadmin', 'admin-gudang', 'owner'], true), 403);
    }
}
