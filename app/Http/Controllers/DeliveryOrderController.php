<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Outlet;
use App\Models\OwnerStock;
use App\Models\PickingList;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
{
    public function index()
    {
        $user  = auth()->user();
        $query = DeliveryOrder::with(['requestOrder', 'owner', 'items.product'])
            ->orderBy('created_at', 'desc');

        // if ($user->role === 'staff-outlet') {
        //     $query->where('owner_id', $user->outlet_id);
        // }

        $deliveryOrders = $query->get();
        $outlets        = Outlet::orderBy('name')->get();

        return view('delivery-orders.index', compact('deliveryOrders', 'outlets'));
    }

    public function show(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load(['requestOrder', 'owner', 'preparedBy', 'receivedBy', 'items.product']);

        return view('delivery-orders.show', compact('deliveryOrder'));
    }

    public function generate(PickingList $pickingList)
    {
        if ($pickingList->status !== 'completed') {
            return back()->with('toast_error', 'Picking must be completed first');
        }

        DB::beginTransaction();
        try {
            $lastDO = DeliveryOrder::latest('id')->first();
            $nextNumber = $lastDO ? ((int) substr($lastDO->code, 2) + 1) : 1;
            $code = 'DO' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

            $requestOrder = $pickingList->requestOrder;

            $deliveryOrder = DeliveryOrder::create([
                'code' => $code,
                'request_order_id' => $requestOrder->id,
                'picking_list_id' => $pickingList->id,
                'owner_id' => $requestOrder->owner_id,
                'prepared_by' => auth()->id(),
                'delivery_date' => now(),
                'status' => 'sent',
            ]);

            foreach ($pickingList->items as $pickItem) {
                if ($pickItem->qty_picked > 0) {
                    DeliveryOrderItem::create([
                        'delivery_order_id' => $deliveryOrder->id,
                        'product_id' => $pickItem->product_id,
                        'stock_id' => $pickItem->stock_id,
                        'qty' => $pickItem->qty_picked,
                        'sku' => $pickItem->stock->sku,
                        'expired_at' => $pickItem->stock->expired_at,
                        'harga_beli' => $pickItem->stock->harga_beli,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('delivery-orders.show', $deliveryOrder)
                ->with('toast_success', 'Delivery order created');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('toast_error', $e->getMessage());
        }
    }

    public function send(Request $request, DeliveryOrder $deliveryOrder)
    {
        $request->validate([
            'photo'   => 'nullable|image|max:2048',
            'items'   => 'required|array',
            'samples' => 'nullable|array',
        ], [
            'items.required' => 'Data item pengiriman wajib diisi.',
        ]);

        // Validate sample qty when additionalNotes exist
        $notes = $deliveryOrder->requestOrder?->additionalNotes ?? collect();
        if ($notes->isNotEmpty()) {
            $samples = $request->input('samples', []);
            foreach ($notes as $note) {
                if (! isset($samples[$note->id]['qty_sample'])) {
                    return back()->with('toast_error', "Qty sample untuk \"{$note->kategori}\" wajib diisi.");
                }
                $qtySample = (int) $samples[$note->id]['qty_sample'];
                if ($qtySample !== (int) $note->qty) {
                    return back()->with('toast_error', "Qty sample untuk \"{$note->kategori}\" harus tepat {$note->qty} (diisi: {$qtySample}).");
                }
            }
        }

        $itemData = $request->input('items', []);

        DB::beginTransaction();
        try {
            foreach ($deliveryOrder->items as $item) {
                $qtySent = max(0, (int) ($itemData[$item->id]['qty_sent'] ?? $item->qty));
                $stock   = $item->stock;

                $item->update(['qty_sent' => $qtySent]);

                // DIHAPUS: $stock->allocate($qtySent);
                // Stock sudah dialokasikan di completeAndShip() saat picking selesai

                // Create/update owner stock using admin-confirmed qty
                OwnerStock::updateOrCreate(
                    [
                        'owner_id'   => $deliveryOrder->owner_id,
                        'product_id' => $item->product_id,
                        'stock_id'   => $stock->id,
                        'sku'        => $stock->sku,
                    ],
                    [
                        'qty'        => DB::raw('qty + ' . $qtySent),
                        'expired_at' => $item->expired_at,
                        'harga_beli' => $item->harga_beli,
                    ]
                );

                // Log movement tetap berjalan
                StockMovement::create([
                    'product_id'     => $item->product_id,
                    'user_id'        => auth()->id(),
                    'type'           => 'out',
                    'reference_type' => DeliveryOrder::class,
                    'reference_id'   => $deliveryOrder->id,
                    'qty_out'        => $qtySent,
                    'balance'        => $item->product->stocks()->sum('qty'),
                    'notes'          => "Delivery to {$deliveryOrder->owner->name} - SKU: {$stock->sku}",
                ]);
            }

            $data = [
                'status' => 'delivered',
                'received_by' => auth()->id(),
                'received_date' => now(),
            ];

            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('delivery-proofs', 'public');
                $data['photo_path'] = $path;
            }

            $deliveryOrder->update($data);

            DB::commit();

            return redirect()->route('delivery-orders.show', $deliveryOrder)->with('toast_success', 'Delivery order sent');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('toast_error', $e->getMessage());
        }
    }

    public function receive(Request $request, DeliveryOrder $deliveryOrder)
    {
        $request->validate([
            'photo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('delivery-proofs', 'public');
            $deliveryOrder->photo_path = $path;
        }

        $deliveryOrder->update([
            'status' => 'delivered',
            'received_by' => auth()->id(),
            'received_date' => now(),
        ]);

        return redirect()->route('delivery-orders.show', $deliveryOrder)
            ->with('toast_success', 'Delivery received');
    }
}
