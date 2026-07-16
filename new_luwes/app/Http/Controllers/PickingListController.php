<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\RequestOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PickingListController extends Controller
{
    public function index()
    {
        $pickingLists = PickingList::with(['requestOrder.owner', 'picker', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();

        $outlets = Outlet::orderBy('name')->get();

        return view('picking-lists.index', compact('pickingLists', 'outlets'));
    }

    public function show(PickingList $pickingList)
    {
        $pickingList->load(['requestOrder.owner', 'picker', 'items.product']);

        return view('picking-lists.show', compact('pickingList'));
    }

    public function pick(PickingList $pickingList)
    {
        $pickingList->load(['items.product', 'items.stock']);

        return view('picking-lists.pick', compact('pickingList'));
    }

    public function generate(RequestOrder $requestOrder)
    {
        if ($requestOrder->status !== 'approved' && $requestOrder->status !== 'partial') {
            return back()->with('toast_error', 'Can only generate picking list for approved requests');
        }

        DB::beginTransaction();
        try {
            $lastPicking = PickingList::latest('id')->first();
            $nextNumber = $lastPicking ? ((int) substr($lastPicking->code, 4) + 1) : 1;
            $code = 'PICK'.str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            $pickingList = PickingList::create([
                'code' => $code,
                'request_order_id' => $requestOrder->id,
                'status' => 'draft',
            ]);

            foreach ($requestOrder->items()->where('qty_approved', '>', 0)->get() as $item) {

                PickingListItem::create([
                    'picking_list_id' => $pickingList->id,
                    'product_id' => $item->product_id,
                    'stock_id' => $item->stock->id,
                    'qty_to_pick' => min($item->qty_approved, $item->stock->qty_reserved),
                    'location' => $item->stock->product->lokasi,
                    'sku' => $item->stock->sku,
                ]);

                // $remainingQty = $item->qty_approved;

                // $stocks = Stock::where('product_id', $item->product_id)
                //     ->where('qty_reserved', '>', 0)
                //     ->orderBy('expired_at', 'asc')
                //     ->orderBy('created_at', 'asc')
                //     ->get();

                // foreach ($stocks as $stock) {
                //     if ($remainingQty <= 0) { break; }

                //     $qtyToPick = min($remainingQty, $stock->qty_reserved);

                //     PickingListItem::create([
                //         'picking_list_id' => $pickingList->id,
                //         'product_id' => $item->product_id,
                //         'stock_id' => $stock->id,
                //         'qty_to_pick' => $qtyToPick,
                //         'location' => $stock->product->lokasi,
                //         'sku' => $stock->sku,
                //     ]);

                //     $remainingQty -= $qtyToPick;
                // }
            }

            DB::commit();

            return redirect()->route('picking-lists.show', $pickingList)
                ->with('toast_success', 'Picking list generated');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('toast_error', $e->getMessage());
        }
    }

    public function startPicking(Request $request, PickingList $pickingList)
    {
        $request->validate([
            'picker_name' => 'nullable|string|max:255',
        ]);

        $pickingList->update([
            'status'      => 'in_progress',
            'picker_id'   => auth()->id(),
            'picker_name' => $request->filled('picker_name') ? $request->picker_name : auth()->user()->name,
            'started_at'  => now(),
        ]);

        return redirect()->route('picking-lists.pick', $pickingList);
    }

    public function updatePickerName(Request $request, PickingList $pickingList)
    {
        $request->validate([
            'picker_name' => 'required|string|max:255',
        ]);

        $pickingList->update(['picker_name' => $request->picker_name]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('toast_success', 'Nama picker diperbarui.');
    }

    public function updateItem(Request $request, PickingListItem $item)
    {
        $validator = Validator::make($request->all(), [
            'qty_picked' => 'required|integer|min:0|max:'.$item->qty_to_pick,
        ], [
            'qty_picked.required' => 'Jumlah yang diambil harus diisi.',
            'qty_picked.integer'  => 'Jumlah yang diambil harus berupa angka.',
            'qty_picked.min'      => 'Jumlah yang diambil minimal 0.',
            'qty_picked.max'      => 'Jumlah yang diambil tidak boleh melebihi '.$item->qty_to_pick.'.',
        ]);

        if ($validator->fails()) {
            $msg = $validator->errors()->first();
            if ($request->wantsJson()) {
                return response()->json(['error' => $msg], 422);
            }

            return back()->withErrors($validator);
        }

        if ($request->filled('val_barcode') && $request->val_barcode !== $item->product->code) {
            $msg = 'Barcode tidak cocok untuk '.$item->product->name.' (expected: '.$item->product->code.')';
            if ($request->wantsJson()) {
                return response()->json(['error' => $msg], 422);
            }

            return back()->with('toast_error', $msg);
        }

        $item->update([
            'qty_picked' => $request->qty_picked,
            'is_picked'  => $request->qty_picked == $item->qty_to_pick,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success'   => true,
                'is_picked' => (bool) $item->is_picked,
                'message'   => 'Item '.$item->product->name.' berhasil diperbarui.',
            ]);
        }

        return redirect()->back()->with('toast_success', 'Item '.$item->product->name.' berhasil diperbarui.');
    }

    public function complete(Request $request, PickingList $pickingList)
    {
        $allPicked = $pickingList->items()->where('is_picked', false)->count() === 0;

        if (! $allPicked) {
            return back()->with('toast_error', 'All items must be picked');
        }

        $data = ['status' => 'completed', 'completed_at' => now()];
        if ($request->filled('picker_name')) {
            $data['picker_name'] = $request->picker_name;
        }

        $pickingList->update($data);

        return redirect()->route('picking-lists.show', $pickingList)
            ->with('toast_success', 'Picking completed');
    }

    public function bulkUpdate(Request $request, PickingList $pickingList)
    {
        $validator = Validator::make($request->all(), [
            'items'              => 'required|array',
            'items.*.qty_picked' => 'required|integer|min:0',
        ], [
            'items.required'              => 'Item harus diisi.',
            'items.*.qty_picked.required' => 'Jumlah yang diambil harus diisi.',
            'items.*.qty_picked.integer'  => 'Jumlah yang diambil harus berupa angka.',
            'items.*.qty_picked.min'      => 'Jumlah yang diambil minimal 0.',
        ]);

        if ($validator->fails()) {
            $msg = $validator->errors()->first();
            if ($request->wantsJson()) {
                return response()->json(['error' => $msg], 422);
            }

            return back()->withErrors($validator);
        }

        if ($request->filled('picker_name')) {
            $pickingList->update(['picker_name' => $request->picker_name]);
        }

        $items   = $pickingList->items()->get()->keyBy('id');
        $updated = [];
        $errors  = [];

        foreach ($request->items as $itemId => $data) {
            if (! isset($items[$itemId])) { continue; }
            $item = $items[$itemId];
            $qty  = (int) $data['qty_picked'];

            if ($qty > $item->qty_to_pick) {
                $errors[$itemId] = "Qty picked untuk {$item->product->name} melebihi maksimal ({$item->qty_to_pick})";

                continue;
            }

            $item->update([
                'qty_picked' => $qty,
                'is_picked'  => $qty == $item->qty_to_pick,
            ]);
            $updated[$itemId] = ['is_picked' => (bool) $item->is_picked];
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'updated' => $updated, 'errors' => $errors]);
        }

        return redirect()->back()->with('toast_success', 'Bulk update successful');
    }
}
