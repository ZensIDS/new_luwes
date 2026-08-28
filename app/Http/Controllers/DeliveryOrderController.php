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
        $deliveryOrder->load(['requestOrder', 'owner', 'preparedBy', 'receivedBy', 'items.product', 'pickingList']);

        // Self-healing: kalau ternyata DO ini item-nya kosong (dari bug lama / proses yang gagal sebagian),
        // coba generate ulang sekarang, asalkan picking list sumbernya masih ada dan sudah completed.
        if ($deliveryOrder->items->isEmpty() && $deliveryOrder->pickingList) {
            DB::beginTransaction();
            try {
                $itemsCreated = $this->generateDeliveryOrderItems($deliveryOrder, $deliveryOrder->pickingList);

                if ($itemsCreated > 0) {
                    DB::commit();

                    \Log::info('Delivery order items berhasil di-generate ulang (self-heal) dari show()', [
                        'delivery_order_id' => $deliveryOrder->id,
                    ]);

                    $deliveryOrder->load('items.product');

                    session()->flash('toast_success', 'Data item Delivery Order berhasil diperbaiki otomatis.');
                } else {
                    DB::rollBack();

                    session()->flash('toast_error', 'Delivery Order ini tidak memiliki item, dan picking list sumbernya juga tidak ada item yang sudah di-pick. Silakan hubungi admin.');
                }
            } catch (\Throwable $e) {
                DB::rollBack();

                \Log::error('Gagal self-heal delivery order items dari show()', [
                    'delivery_order_id' => $deliveryOrder->id,
                    'error' => $e->getMessage(),
                ]);

                session()->flash('toast_error', 'Data item Delivery Order kosong dan gagal diperbaiki otomatis: ' . $e->getMessage());
            }
        }

        return view('delivery-orders.show', compact('deliveryOrder'));
    }

    public function generate(PickingList $pickingList)
    {
        if ($pickingList->status !== 'completed') {
            return back()->with('toast_error', 'Picking must be completed first');
        }

        DB::beginTransaction();
        try {
            // Cegah double-generate kalau tombol diklik dua kali / race condition
            $existingDO = DeliveryOrder::where('picking_list_id', $pickingList->id)
                ->lockForUpdate()
                ->first();

            if ($existingDO) {
                DB::commit();

                return redirect()->route('delivery-orders.show', $existingDO)
                    ->with('toast_success', 'Delivery order sudah pernah dibuat sebelumnya.');
            }

            $lastDO = DeliveryOrder::lockForUpdate()->latest('id')->first();
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

            $itemsCreated = $this->generateDeliveryOrderItems($deliveryOrder, $pickingList);

            if ($itemsCreated === 0) {
                DB::rollBack();

                \Log::warning('Delivery order gagal generate: tidak ada item dengan qty_picked > 0', [
                    'picking_list_id' => $pickingList->id,
                ]);

                return back()->with('toast_error', 'Gagal membuat Delivery Order: tidak ada item yang sudah di-pick (qty_picked > 0) pada picking list ini.');
            }

            DB::commit();

            return redirect()->route('delivery-orders.show', $deliveryOrder)
                ->with('toast_success', 'Delivery order created');
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Gagal generate delivery order', [
                'picking_list_id' => $pickingList->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('toast_error', 'Gagal membuat Delivery Order: ' . $e->getMessage());
        }
    }

    /**
     * Generate DeliveryOrderItem dari PickingListItem yang qty_picked > 0.
     * Dipakai baik dari generate() maupun sebagai self-healing dari show().
     * Return jumlah item yang berhasil dibuat.
     */
    protected function generateDeliveryOrderItems(DeliveryOrder $deliveryOrder, PickingList $pickingList): int
    {
        $count = 0;

        foreach ($pickingList->items()->with('stock')->get() as $pickItem) {
            if ($pickItem->qty_picked <= 0) {
                continue;
            }

            if (!$pickItem->stock) {
                \Log::warning('PickingListItem tanpa relasi stock, dilewati saat generate DO item', [
                    'picking_list_item_id' => $pickItem->id,
                ]);
                continue;
            }

            DeliveryOrderItem::create([
                'delivery_order_id' => $deliveryOrder->id,
                'product_id' => $pickItem->product_id,
                'stock_id' => $pickItem->stock_id,
                'qty' => $pickItem->qty_picked,
                'sku' => $pickItem->stock->sku,
                'expired_at' => $pickItem->stock->expired_at,
                'harga_beli' => $pickItem->stock->harga_beli,
            ]);

            $count++;
        }

        return $count;
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
