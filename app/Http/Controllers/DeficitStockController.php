<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\OwnerStock;
use App\Models\Product;
use App\Services\DeficitStockService;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Halaman admin outlet untuk stok minus (penjualan melebihi stok sistem).
 * Route dibatasi role superadmin|owner|staff-outlet; staff-outlet otomatis
 * hanya melihat outlet miliknya lewat OutletAccess.
 */
class DeficitStockController extends Controller
{
    public function __construct(protected DeficitStockService $deficits)
    {
    }

    public function index(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $outletId = OutletAccess::id($request, false);

        return view('deficit-stocks.index', [
            'outlets' => $outlets,
            'selectedOutletId' => $outletId,
            'search' => trim((string) $request->input('q', '')),
            'rows' => $this->deficits->paginateOpen($outletId, trim((string) $request->input('q', ''))),
            'totalOpen' => $this->deficits->countOpen($outletId),
            'canChooseOutlet' => ! in_array($request->user()->role, ['staff-outlet', 'kasir'], true),
        ]);
    }

    public function close(Request $request, OwnerStock $ownerStock)
    {
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Alasan wajib diisi untuk menutup stok minus.',
            'reason.min' => 'Alasan terlalu pendek (minimal 5 karakter).',
        ]);

        // Pastikan batch ini memang milik outlet yang boleh diakses user.
        $request->merge(['outlet_id' => $ownerStock->owner_id]);
        OutletAccess::id($request);

        try {
            $this->deficits->close($ownerStock, $data['reason'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('toast_error', $e->getMessage());
        }

        // Badge sidebar di-cache 60 detik; segarkan agar angkanya langsung turun.
        Cache::forget('deficit-count:all');
        Cache::forget('deficit-count:'.$ownerStock->owner_id);

        return back()->with('toast_success', 'Stok minus berhasil ditutup dan tercatat di Kartu Stock.');
    }

    public function oversold(Request $request)
    {
        $request->validate([
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'product_id' => 'nullable|integer|exists:products,id',
        ]);

        $outlets = OutletAccess::outlets();
        $outletId = OutletAccess::id($request, false);
        $from = $request->input('tanggal_mulai', now()->startOfMonth()->toDateString());
        $to = $request->input('tanggal_selesai', now()->toDateString());
        $productId = $request->filled('product_id') ? (int) $request->input('product_id') : null;

        $report = $this->deficits->oversoldReport($outletId, $from, $to, $productId);

        return view('deficit-stocks.oversold', [
            'outlets' => $outlets,
            'selectedOutletId' => $outletId,
            'from' => $from,
            'to' => $to,
            'product' => $productId ? Product::select(['id', 'code', 'name'])->find($productId) : null,
            'rows' => $report['rows'],
            'summary' => $report['summary'],
            'canChooseOutlet' => ! in_array($request->user()->role, ['staff-outlet', 'kasir'], true),
            'outletName' => $outletId ? Outlet::whereKey($outletId)->value('name') : null,
        ]);
    }
}