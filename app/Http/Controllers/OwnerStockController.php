<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\OwnerStock;
use Illuminate\Http\Request;

class OwnerStockController extends Controller
{
    public function index(Request $request)
    {
        $owners = Outlet::all();
        $selectedOwner = $request->owner_id
            ? Outlet::find($request->owner_id)
            : null;

        $stocks = $selectedOwner
            ? OwnerStock::with('product')
                ->where('owner_id', $selectedOwner->id)
                ->get()
            : collect();

        dd([
            'owners' => $owners?->toArray(),
            'stocks' => $stocks?->toArray(),
            'selectedOwner' => $selectedOwner,
        ]);

        return view('owner-stocks.index', compact('owners', 'selectedOwner', 'stocks'));
    }

    public function show(Outlet $owner)
    {
        $stocks = OwnerStock::with('product')
            ->where('owner_id', $owner->id)
            ->get();

        dd([
            'owners' => $owner?->toArray(),
            'stocks' => $stocks?->toArray(),
        ]);

        return view('owner-stocks.show', compact('owner', 'stocks'));
    }
}
