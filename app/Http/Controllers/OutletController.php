<?php

namespace App\Http\Controllers;

use App\Http\Requests\OutletRequest;
use App\Models\CashierSession;
use App\Models\Kas;
use App\Models\Outlet;
use App\Services\DeficitStockService;
use App\Services\ExpiredStockService;
use App\Support\OutletAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OutletController extends Controller
{
    public function getKas($outlet_id)
    {
        $kas = Kas::where('outlet_id', $outlet_id)->get();

        return response()->json($kas);
    }

    public function index()
    {
        return view('outlets.index', [
            'outlets' => Outlet::get(),
        ]);
    }

    public function create()
    {
        return view('outlets.create', []);
    }

    public function store(OutletRequest $request)
    {
        $data = $request->validated();
        $data['jenis_outlet'] = 'toko';

        // Handle file upload
        if ($request->hasFile('logo')) {
            // Get the uploaded file
            $file = $request->file('logo');

            // Generate a unique file name
            $fileName = time().'.'.$file->getClientOriginalExtension();

            // Store the file
            $file->storeAs('public/logos', $fileName);

            // Add the file path to the data array
            $data['logo'] = 'storage/logos/'.$fileName;
        }

        Outlet::create($data);

        return redirect(route('outlet.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function show(Request $request, Outlet $outlet)
    {
        $request->merge(['outlet_id' => $outlet->id]);
        OutletAccess::id($request);
        $cashierSession = CashierSession::with([
            'drawerEntries' => fn ($query) => $query->latest('recorded_at'),
            'cashier', 'activeShifts',
        ])
            ->where('outlet_id', $outlet->id)
            ->where('cashier_id', $request->user()->getAuthIdentifier())
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
        $lastClosedSession = CashierSession::query()
            ->where('outlet_id', $outlet->id)
            ->where('cashier_id', $request->user()->getAuthIdentifier())
            ->where('status', 'closed')
            ->latest('closed_at')
            ->first();

        return view('outlets.show', [
            'outlet' => $outlet,
            'cashierSession' => $cashierSession,
            'cashierSummary' => $cashierSession?->summary(),
            'suggestedOpeningCash' => $lastClosedSession?->carry_over_cash ?? 1000000,
            // Banner "ada N produk stok minus": hanya untuk admin outlet / owner / superadmin, bukan kasir.
            'deficitCount' => in_array($request->user()->role, ['staff-outlet', 'owner', 'superadmin'], true)
                ? app(DeficitStockService::class)->countOpen($outlet->id)
                : 0,
            // Notice stok expired (sudah expired / akan expired): admin outlet / owner / superadmin saja.
            'expirySummary' => in_array($request->user()->role, ['staff-outlet', 'owner', 'superadmin'], true)
                ? app(ExpiredStockService::class)->summary($outlet->id)
                : null,
        ]);
    }

    public function edit(Outlet $outlet)
    {
        return view('outlets.edit', [
            'outlet' => $outlet,
        ]);
    }

    public function update(OutletRequest $request, Outlet $outlet)
    {
        $data = $request->validated();
        $data['jenis_outlet'] = 'toko';
        if ($request->hasFile('logo')) {
            // Delete the old image file
            if ($outlet->logo) {
                Storage::delete(str_replace('storage', 'public', $outlet->logo));
            }
            // Store the new image file
            $file = $request->file('logo');
            $fileName = time().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/logos', $fileName);
            $data['logo'] = 'storage/logos/'.$fileName;
        }
        $outlet->update($data);

        return redirect(route('outlet.index'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(Outlet $outlet)
    {
        // Delete the image file
        if ($outlet->logo) {
            Storage::delete(str_replace('storage', 'public', $outlet->logo));
        }

        $outlet->delete();

        return redirect(route('outlet.index'))->with('toast_success', 'Berhasil Menghapus Data!');
    }
}