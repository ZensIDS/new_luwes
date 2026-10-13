<?php

namespace App\Http\Controllers;

use App\Models\OwnerStock;
use App\Services\ExpiredStockService;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Halaman admin outlet untuk stok expired. Route dibatasi role superadmin|owner|staff-outlet;
 * staff-outlet otomatis hanya melihat outlet miliknya lewat OutletAccess.
 */
class ExpiredStockController extends Controller
{
    public function __construct(protected ExpiredStockService $expired)
    {
    }

    public function index(Request $request)
    {
        $outlets = OutletAccess::outlets();
        $outletId = OutletAccess::id($request, false);
        $tab = $request->input('tab') === 'soon' ? 'soon' : 'expired';
        $search = trim((string) $request->input('q', ''));
        $summary = $this->expired->summary($outletId);

        return view('expired-stocks.index', [
            'outlets' => $outlets,
            'selectedOutletId' => $outletId,
            'tab' => $tab,
            'search' => $search,
            'summary' => $summary,
            'rows' => $this->expired->paginate($tab, $outletId, $search),
            'canChooseOutlet' => ! in_array($request->user()->role, ['staff-outlet', 'kasir'], true),
        ]);
    }

    public function writeOff(Request $request, OwnerStock $ownerStock)
    {
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Alasan wajib diisi untuk memusnahkan stok expired.',
            'reason.min' => 'Alasan terlalu pendek (minimal 5 karakter).',
        ]);

        // Pastikan batch ini memang milik outlet yang boleh diakses user.
        $request->merge(['outlet_id' => $ownerStock->owner_id]);
        OutletAccess::id($request);

        try {
            $this->expired->writeOff($ownerStock, $data['reason'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('toast_error', $e->getMessage());
        }

        return back()->with('toast_success', 'Stok expired dimusnahkan dan tercatat di Kartu Stock.');
    }
}