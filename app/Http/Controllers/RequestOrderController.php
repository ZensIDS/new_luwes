<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\RequestOrder;
use App\Models\RequestOrderItem;
use App\Models\RequestOrderNote;
use App\Models\PickingList;
use App\Models\PickingListItem;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestOrderController extends Controller
{
    public function index()
    {
        $user  = auth()->user();
        $query = RequestOrder::with(['owner', 'requestedBy'])
            ->orderBy('created_at', 'desc');

        // if ($user->role === 'staff-outlet') {
        //     $query->where('owner_id', $user->outlet_id);
        // }

        $requests = $query->get();
        $outlets  = Outlet::orderBy('name')->get();

        return view('request-orders.index', compact('requests', 'outlets'));
    }

    public function create()
    {
        return view('request-orders.create', [
            'outlets'    => Outlet::get(),
            'categories' => Category::orderBy('name')->get(),
            'products' => Product::with(['stocks' => function ($q) {
                $q->where('qty_available', '>=', 0)
                    ->where('status', 'available');
            }])->whereHas('stocks', function ($q) {
                $q->where('qty_available', '>=', 0)
                    ->where('status', 'available');
            })
                // ->where('is_serialized', false)
                ->get()
                ->map(function ($product) {
                    $product->total_available = (int) $product->stocks->sum('qty_available');

                    return $product;
                }),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'owner_id'                   => 'required|exists:outlets,id',
            'request_date'               => 'required|date',
            'items'                      => 'required|array',
            'items.*.product_id'         => 'required|exists:products,id|distinct',
            'items.*.qty_requested'      => 'required|numeric|min:1',            // changed to numeric
            'extra_notes'                => 'nullable|array',
            'extra_notes.*.kategori'     => 'required_with:extra_notes|string|max:255',
            'extra_notes.*.qty'          => 'required_with:extra_notes|numeric|min:0', // changed to numeric
            'extra_notes.*.nama_pj'      => 'nullable|string|max:255',
        ], [
            'owner_id.required'                     => 'Outlet harus dipilih.',
            'owner_id.exists'                       => 'Outlet yang dipilih tidak ditemukan.',
            'request_date.required'                 => 'Tanggal permintaan harus diisi.',
            'request_date.date'                     => 'Tanggal permintaan harus berupa tanggal yang valid.',
            'items.required'                        => 'Item harus diisi.',
            'items.array'                           => 'Item harus berupa array.',
            'items.*.product_id.required'           => 'Produk harus dipilih.',
            'items.*.product_id.exists'             => 'Produk yang dipilih tidak ditemukan.',
            'items.*.product_id.distinct'           => 'Produk tidak boleh sama di baris yang berbeda.',
            'items.*.qty_requested.required'        => 'Jumlah diminta harus diisi.',
            'items.*.qty_requested.numeric'         => 'Jumlah diminta harus berupa angka.',
            'items.*.qty_requested.min'             => 'Jumlah diminta minimal 1.',
            'extra_notes.array'                     => 'sample barang harus berupa array.',
            'extra_notes.*.kategori.required_with'  => 'Kategori harus diisi jika ada sample barang.',
            'extra_notes.*.kategori.string'         => 'Kategori harus berupa teks.',
            'extra_notes.*.kategori.max'            => 'Kategori maksimal 255 karakter.',
            'extra_notes.*.qty.required_with'       => 'Jumlah harus diisi jika ada sample barang.',
            'extra_notes.*.qty.numeric'             => 'Jumlah harus berupa angka.',
            'extra_notes.*.qty.min'                 => 'Jumlah minimal 0.',
            'extra_notes.*.nama_pj.string'          => 'Nama penanggung jawab harus berupa teks.',
            'extra_notes.*.nama_pj.max'             => 'Nama penanggung jawab maksimal 255 karakter.',
        ]);

        DB::beginTransaction();
        try {
            $lastRequest = RequestOrder::withTrashed()->latest('id')->first();
            $nextNumber = $lastRequest ? ((int) substr($lastRequest->code, 3) + 1) : 1;
            $code = 'REQ' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

            $requestOrder = RequestOrder::create([
                'code' => $code,
                'owner_id' => $request->owner_id,
                'requested_by' => auth()->id(),
                'request_date' => $request->request_date,
                'notes' => $request->notes,
                'status' => 'pending',
            ]);

            foreach ($request->items as $item) {
                RequestOrderItem::create([
                    'request_order_id' => $requestOrder->id,
                    'product_id'       => $item['product_id'],
                    'stock_id'         => null,
                    'qty_requested'    => $item['qty_requested'],
                    'notes'            => $item['notes'] ?? null,
                ]);
            }

            foreach ($request->input('extra_notes', []) as $note) {
                if (! empty($note['kategori'])) {
                    RequestOrderNote::create([
                        'request_order_id' => $requestOrder->id,
                        'kategori'         => $note['kategori'],
                        'qty'              => (int) ($note['qty'] ?? 0),
                        'nama_pj'          => $note['nama_pj'] ?? null,
                    ]);
                }
            }

            DB::commit();

            // return redirect()->route('request-orders.verify', $requestOrder)
            //     ->with('toast_success', 'Request created successfully. Please assign stocks.');

            return redirect()->route('request-orders.index')
                ->with('toast_success', 'Request created successfully. Please assign stocks.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('toast_error', $e->getMessage());
        }
    }

    public function verify(RequestOrder $requestOrder)
    {
        return redirect()->route('request-orders.show', $requestOrder);
    }

    public function show($id)
    {
        $pickingList = PickingList::where('request_order_id', $id)->first();
        $requestOrder = RequestOrder::with([
            'items.product.stocks',
            'items.stock',
            'requestedBy',
            'verifiedBy',
            'additionalNotes',
            'deliveryOrder.owner',
            'deliveryOrder.requestOrder.additionalNotes',
            'deliveryOrder.items.product',
            'deliveryOrder.items.stock',
        ])->findOrFail($id);
        // dd($pickingList);

        if (auth()->user()->role === 'staff-outlet') {
            return view('request-orders.show', compact('requestOrder'));
        }

        return view('request-orders.verify', compact('requestOrder', 'pickingList'));
    }

    public function processVerification(Request $request, RequestOrder $requestOrder)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:request_order_items,id',
            'items.*.qty_approved' => 'required|integer|min:1',
            'items.*.item_status' => 'required|in:approved,partial,rejected',
        ], [
            'items.required' => 'Item harus diisi.',
            'items.array' => 'Item harus berupa array.',
            'items.*.id.required' => 'ID item harus diisi.',
            'items.*.id.exists' => 'Item permintaan tidak ditemukan.',
            'items.*.qty_approved.required' => 'Jumlah disetujui harus diisi.',
            'items.*.qty_approved.integer' => 'Jumlah disetujui harus berupa angka.',
            'items.*.qty_approved.min' => 'Jumlah disetujui minimal 0.',
            'items.*.item_status.required' => 'Status item harus diisi.',
            'items.*.item_status.in' => 'Status item harus dipilih antara approved, partial, atau rejected.',
        ]);

        // Validate qty_approved against specific SKU stock
        foreach ($request->items as $itemData) {
            $item = RequestOrderItem::find($itemData['id']);
            $stock = $item->stock;

            if (! $stock) {
                return back()->withErrors([
                    'items.' . array_search($itemData, $request->items) . '.qty_approved' => "Stock not found for product {$item->product->name}"
                ])->withInput();
            }

            // Validate against requested qty
            if ($itemData['qty_approved'] > $item->qty_requested) {
                return back()->withErrors([
                    'items.' . array_search($itemData, $request->items) . '.qty_approved' => "Product {$item->product->name}: Approved qty cannot exceed requested qty ({$item->qty_requested})"
                ])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            // FIRST: Unreserve all previous reservations
            foreach ($request->items as $itemData) {
                $item = RequestOrderItem::find($itemData['id']);
                $stock = $item->stock;

                if ($item->qty_approved > 0 && $stock) {
                    $stock->unreserve($item->qty_approved);
                }
            }

            // SECOND: Refresh stocks and validate new quantities
            foreach ($request->items as $itemData) {
                $item = RequestOrderItem::find($itemData['id']);
                $stock = $item->stock->fresh(); // Refresh from DB after unreserve

                // Skip validation if rejected
                if ($itemData['item_status'] === 'rejected') {
                    continue;
                }

                // Validate available stock after unreserving
                if ($itemData['qty_approved'] > 0) {
                    if ($stock->qty_available < $itemData['qty_approved']) {
                        // Rollback and show error with current available
                        DB::rollBack();

                        return back()->withErrors([
                            'items.' . array_search($itemData, $request->items) . '.qty_approved' => "Product {$item->product->name} (SKU: {$stock->sku}): Only {$stock->qty_available} available after releasing previous reservation. Cannot approve {$itemData['qty_approved']}."
                        ])->withInput();
                    }
                }
            }

            // THIRD: Update items and reserve new quantities
            $hasApproved = false;
            $hasPartial = false;
            $allRejected = true;

            foreach ($request->items as $itemData) {
                $item = RequestOrderItem::find($itemData['id']);
                $stock = $item->stock->fresh();

                // Handle rejected status
                if ($itemData['item_status'] === 'rejected') {
                    $item->update([
                        'qty_approved' => 0,
                        'item_status' => 'rejected',
                        'notes' => $itemData['notes'] ?? null,
                    ]);

                    continue;
                }

                $item->update([
                    'qty_approved' => $itemData['qty_approved'],
                    'item_status' => $itemData['item_status'],
                    'notes' => $itemData['notes'] ?? null,
                ]);

                // Reserve new quantity
                if ($itemData['qty_approved'] > 0) {
                    $stock->reserve($itemData['qty_approved']);
                }

                // Determine overall status
                if ($itemData['item_status'] === 'approved') {
                    $hasApproved = true;
                }
                if ($itemData['item_status'] === 'partial') {
                    $hasPartial = true;
                    $hasApproved = true;
                }
                if ($itemData['item_status'] !== 'rejected') {
                    $allRejected = false;
                }
            }

            // Update request order status
            if ($allRejected) {
                $status = 'rejected';
            } elseif ($hasPartial) {
                $status = 'partial';
            } else {
                $status = 'approved';
            }

            $requestOrder->update([
                'status' => $status,
                'verified_by' => auth()->id(),
                'verified_date' => now(),
                'verification_notes' => $request->verification_notes,
            ]);

            DB::commit();

            $message = $requestOrder->wasChanged('status')
                ? 'Request verified successfully'
                : 'Request verification updated successfully';

            // return redirect()->route('request-orders.verify', $requestOrder)
            //     ->with('toast_success', $message);
            return redirect()->route('request-orders.index')
                ->with('toast_success', $message);
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('toast_error', $e->getMessage());
        }
    }

    public function updateStocks(Request $request, RequestOrder $requestOrder)
    {
        $request->validate([
            'stock_assignments' => 'required|array',
            'stock_assignments.*.item_id' => 'required|exists:request_order_items,id',
            'stock_assignments.*.stock_id' => 'required|exists:stocks,id|distinct',
            'stock_assignments.*.qty' => 'required|integer|min:1',
        ], [
            'stock_assignments.required' => 'Penugasan stok harus diisi.',
            'stock_assignments.array' => 'Penugasan stok harus berupa array.',
            'stock_assignments.*.item_id.required' => 'ID item harus diisi.',
            'stock_assignments.*.item_id.exists' => 'Item permintaan tidak ditemukan.',
            'stock_assignments.*.stock_id.required' => 'Stok harus dipilih.',
            'stock_assignments.*.stock_id.exists' => 'Stok yang dipilih tidak ditemukan.',
            'stock_assignments.*.stock_id.distinct' => 'Terdapat stok yang sama (ID :input) dimasukkan lebih dari satu kali.',
            'stock_assignments.*.qty.required' => 'Jumlah stok harus diisi.',
            'stock_assignments.*.qty.integer' => 'Jumlah stok harus berupa angka.',
            'stock_assignments.*.qty.min' => 'Jumlah stok minimal 1.',
        ]);

        DB::beginTransaction();
        try {
            // Group by item_id
            $grouped = collect($request->stock_assignments)->groupBy('item_id');
            $hasPartial = false;

            foreach ($grouped as $itemId => $assignments) {
                $originalItem = RequestOrderItem::find($itemId);
                $totalQty = $assignments->sum('qty');

                if ($totalQty > $originalItem->qty_requested) {
                    throw new \Exception("Product {$originalItem->product->name}: Total assigned qty ({$totalQty}) tidak boleh melebihi qty request ({$originalItem->qty_requested})");
                }

                // Delete original item (will be replaced by split items)
                $originalItem->delete();

                // Create new items for each stock assignment
                foreach ($assignments as $assignment) {
                    $stock = Stock::find($assignment['stock_id']);

                    if ($stock->qty_available < $assignment['qty']) {
                        throw new \Exception("Stock {$stock->sku}: Only {$stock->qty_available} available, cannot assign {$assignment['qty']}");
                    }

                    RequestOrderItem::create([
                        'request_order_id' => $requestOrder->id,
                        'product_id' => $originalItem->product_id,
                        'stock_id' => $assignment['stock_id'],
                        'qty_requested' => $assignment['qty'],
                        'qty_approved' => $assignment['qty'],
                        'item_status' => 'approved',
                        'notes' => $originalItem->notes,
                    ]);

                    $stock->reserve($assignment['qty']);
                }

                if ($totalQty < $originalItem->qty_requested) {
                    $hasPartial = true;

                    RequestOrderItem::create([
                        'request_order_id' => $requestOrder->id,
                        'product_id' => $originalItem->product_id,
                        'stock_id' => null,
                        'qty_requested' => $originalItem->qty_requested - $totalQty,
                        'qty_approved' => 0,
                        'item_status' => 'rejected',
                        'notes' => trim(($originalItem->notes ? $originalItem->notes . ' | ' : '') . 'Sisa qty belum teralokasi saat verifikasi otomatis.'),
                    ]);
                }
            }

            $requestOrder->update([
                'status' => $hasPartial ? 'partial' : 'approved',
                'verified_by' => auth()->id(),
                'verified_date' => now(),
                'verification_notes' => 'Terverifikasi otomatis saat admin memilih SKU/stok.',
            ]);

            DB::commit();

            return redirect()->route('request-orders.show', $requestOrder)
                ->with('toast_success', 'Stock assignment berhasil disimpan dan request otomatis terverifikasi.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('toast_error', $e->getMessage())->withInput();
        }
    }

    public function processView(RequestOrder $requestOrder)
    {
        // KUNCI UTAMA: Hanya izinkan masuk jika status RO benar-benar masih 'pending'
        if ($requestOrder->status !== 'pending') {
            return redirect()->route('request-orders.index')
                ->with('toast_error', 'Request Order ini tidak valid untuk diproses atau sudah selesai.');
        }

        DB::beginTransaction();
        try {
            // 1. Cek apakah draf Picking List untuk RO ini sudah ada
            $pickingList = PickingList::where('request_order_id', $requestOrder->id)->first();

            if (!$pickingList) {
                // Generate nomor urut kode picking baru (misal: PICK00001)
                $lastPicking = PickingList::latest('id')->first();
                $nextNumber  = $lastPicking ? ((int) substr($lastPicking->code, 4) + 1) : 1;
                $code        = 'PICK' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);

                // Buat induk picking list dengan status 'in_progress' sesuai ENUM database Anda
                $pickingList = PickingList::create([
                    'code'             => $code,
                    'request_order_id' => $requestOrder->id,
                    'status'           => 'in_progress', // Sesuai ENUM db Anda
                    'picker_id'        => auth()->id(),
                    'picker_name'      => auth()->user()->name,
                    'started_at'       => now(),
                ]);

                // Salin semua item RO ke tabel draf picking_list_items
                foreach ($requestOrder->items()->where('qty_requested', '>', 0)->get() as $item) {
                    PickingListItem::create([
                        'picking_list_id' => $pickingList->id,
                        'product_id'      => $item->product_id,
                        'stock_id'        => null, // Diisi nanti saat barcode di-scan
                        'qty_to_pick'     => $item->qty_requested,
                        'qty_picked'      => 0,
                        'location'        => $item->product->lokasi ?? '-',
                        'sku'             => null,
                        'is_picked'       => 0, // Default 0 (belum di-pick)
                    ]);
                }

                // CATATAN: Kode pengubah status $requestOrder->update(['status' => 'processing']) SUDAH DIHAPUS TOTAL.
                // Status RO Anda di database dijamin akan TETAP BENAR-BENAR 'pending'.
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('request-orders.index')
                ->with('toast_error', 'Gagal menginisialisasi sesi picking: ' . $e->getMessage());
        }

        // Ambil data item draf picking list beserta relasi produk dan stoknya
        $pickingList->load(['items.product', 'items.stock']);

        return view('request-orders.process', compact('requestOrder', 'pickingList'));
    }

    public function scanPick(Request $request, RequestOrder $requestOrder)
    {
        $request->validate([
            'barcode' => 'required|string',
        ]);

        $barcode = trim($request->barcode);

        // 1. Ambil picking list aktif
        $pickingList = PickingList::where('request_order_id', $requestOrder->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$pickingList) {
            return response()->json(['success' => false, 'message' => 'Sesi picking tidak ditemukan.'], 404);
        }

        // 2. Cari item yang BELUM di-pick (is_picked = 0) untuk produk dengan barcode tersebut
        $item = $pickingList->items()
            ->where('is_picked', 0)
            ->whereHas('product', fn($q) => $q->where('code', $barcode))
            ->first();

        if (!$item) {
            // Cek apakah sebenarnya sudah pernah di-scan semua
            $alreadyPicked = $pickingList->items()
                ->where('is_picked', 1)
                ->whereHas('product', fn($q) => $q->where('code', $barcode))
                ->first();

            if ($alreadyPicked) {
                return response()->json([
                    'success'      => false,
                    'already'      => true,
                    'message'      => "Produk '{$alreadyPicked->product->name}' sudah selesai di-pick semuanya.",
                    'item_id'      => $alreadyPicked->id,
                    'product_code' => $barcode,
                ], 200);
            }

            return response()->json(['success' => false, 'message' => 'Produk tidak terdaftar di RO ini.'], 404);
        }

        // 3. Ambil SEMUA stock available untuk produk ini, diurutkan berdasarkan FEFO & FIFO
        // Prioritas 1: Expired terdekat (FEFO)
        // Prioritas 2: Pembelian/Pembuatan terlama (FIFO / id asc)
        $availableStocks = Stock::where('product_id', $item->product_id)
            ->where('qty_available', '>', 0)
            ->where('status', 'available')
            ->orderByRaw('expired_at IS NULL ASC') // Expired_at yang ada nilainya didahulukan
            ->orderBy('expired_at', 'asc')         // Expired terdekat
            ->orderBy('id', 'asc')                 // Pembelian terlama (menggunakan ID atau created_at)
            ->get();

        $totalStockAvailable = $availableStocks->sum('qty_available');
        $qtyNeeded = $item->qty_to_pick; // Contoh: 60 pcs

        if ($totalStockAvailable < $qtyNeeded) {
            return response()->json([
                'success' => false,
                'message' => "Total stok gudang dari seluruh SKU ({$totalStockAvailable} pcs) tidak mencukupi kebutuhan ({$qtyNeeded} pcs)."
            ], 422);
        }

        DB::beginTransaction();
        try {
            $remainingToPick = $qtyNeeded;
            $updatedItemsData = []; // Untuk menampung response ke FE

            foreach ($availableStocks as $stock) {
                if ($remainingToPick <= 0) break;

                // Tentukan berapa jumlah yang bisa diambil dari SKU/Batch ini
                $take = min($stock->qty_available, $remainingToPick);

                if ($remainingToPick == $qtyNeeded) {
                    // JIKA INI SKU PERTAMA: Update baris draf asli yang sudah ada di tabel
                    $item->update([
                        'stock_id'   => $stock->id,
                        'sku'        => $stock->sku,
                        'qty_to_pick' => $take,
                        'qty_picked' => $take,
                        'is_picked'  => 1
                    ]);
                    $updatedItemsData[] = [
                        'id'           => $item->id,
                        'sku'          => $stock->sku,
                        'qty'          => $take,
                        'expired_at'   => $stock->expired_at ? \Carbon\Carbon::parse($stock->expired_at)->format('d/m/Y') : '-',
                        'is_child'     => false,
                        'product_id'   => $item->product_id,
                        'product_code' => $item->product->code,
                        'product_name' => $item->product->name,
                    ];
                } else {
                    // JIKA BUTUH SKU TAMBAHAN (SPLIT ROW): Buat baris baru di picking_list_items
                    $newItem = PickingListItem::create([
                        'picking_list_id' => $pickingList->id,
                        'product_id'      => $item->product_id,
                        'stock_id'        => $stock->id,
                        'sku'             => $stock->sku,
                        'qty_to_pick'     => $take,
                        'qty_picked'      => $take,
                        'location'        => $item->location,
                        'is_picked'       => 1
                    ]);
                    $updatedItemsData[] = [
                        'id'           => $newItem->id,
                        'sku'          => $stock->sku,
                        'qty'          => $take,
                        'expired_at'   => $stock->expired_at ? \Carbon\Carbon::parse($stock->expired_at)->format('d/m/Y') : '-',
                        'is_child'     => true,
                        'product_id'   => $item->product_id,
                        'product_name' => $item->product->name,
                        'product_code' => $item->product->code
                    ];
                }

                $remainingToPick -= $take;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'split'   => true,
                'items'   => $updatedItemsData,
                'message' => "Berhasil memecah alokasi menjadi " . count($updatedItemsData) . " SKU sesuai aturan FIFO/FEFO."
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Gagal memproses split SKU: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update Qty Diminta (qty_to_pick) untuk sebuah produk di picking list.
     * Selalu bisa diedit selama RO masih 'pending'; terkunci otomatis setelah completeAndShip
     * karena halaman proses hanya bisa diakses selagi status masih pending.
     *
     * Kalau produk BELUM pernah di-scan, cukup update angka qty_to_pick di baris tsb.
     * Kalau produk SUDAH pernah di-scan (punya alokasi SKU/stock_id), semua baris split
     * lama untuk produk itu dihapus & dialokasikan ULANG dari awal mengikuti aturan
     * FEFO/FIFO yang sama seperti scanPick, supaya "Stok Terpilih" selalu sinkron dengan
     * qty yang baru diinput.
     */
    public function updateQtyToPick(Request $request, RequestOrder $requestOrder)
    {
        // Guard utama: qty diminta hanya boleh diubah selagi RO masih pending / belum di-complete & ship.
        if ($requestOrder->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Request Order ini sudah tidak bisa diubah (sudah diproses/selesai).',
            ], 422);
        }

        $pickingList = PickingList::where('request_order_id', $requestOrder->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$pickingList) {
            return response()->json(['success' => false, 'message' => 'Sesi picking tidak ditemukan.'], 404);
        }

        $validated = $request->validate([
            'item_id'     => 'required|integer|exists:picking_list_items,id',
            'qty_to_pick' => 'required|integer|min:0',
        ], [
            'qty_to_pick.required' => 'Qty diminta harus diisi.',
            'qty_to_pick.integer'  => 'Qty diminta harus berupa angka.',
            'qty_to_pick.min'      => 'Qty diminta tidak boleh kurang dari 0.',
        ]);

        $item = PickingListItem::where('id', $validated['item_id'])
            ->where('picking_list_id', $pickingList->id)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item tidak ditemukan pada sesi picking ini.',
            ], 404);
        }

        $newTotalQty = (int) $validated['qty_to_pick'];

        DB::beginTransaction();
        try {
            // Ambil semua baris (termasuk hasil split SKU) untuk produk ini
            $siblingItems = PickingListItem::where('picking_list_id', $pickingList->id)
                ->where('product_id', $item->product_id)
                ->get();

            $wasPicked = $siblingItems->contains(fn($i) => $i->is_picked == 1);

            if (!$wasPicked) {
                // Belum pernah di-scan -> tinggal update angka qty_to_pick, tidak ada alokasi SKU untuk disesuaikan
                $item->update(['qty_to_pick' => $newTotalQty]);

                DB::commit();

                return response()->json([
                    'success'           => true,
                    'qty_to_pick_total' => $newTotalQty,
                    'is_picked'         => false,
                    'stock_items'       => [],
                    'message'           => 'Qty diminta berhasil diperbarui.',
                ]);
            }

            // Sudah pernah di-scan -> reset alokasi lama, lalu jalankan ulang FEFO/FIFO untuk qty baru
            // (aman: qty_available baru dikurangi saat completeAndShip, jadi stok yang tadinya
            // teralokasi otomatis "tersedia" lagi begitu baris-baris lama dihapus/direset)
            $location = $item->location;

            $siblingItems->where('id', '!=', $item->id)->each(fn($i) => $i->delete());

            $item->update([
                'qty_to_pick' => 0,
                'qty_picked'  => 0,
                'stock_id'    => null,
                'sku'         => null,
                'is_picked'   => 0,
            ]);

            if ($newTotalQty <= 0) {
                DB::commit();

                return response()->json([
                    'success'           => true,
                    'qty_to_pick_total' => 0,
                    'is_picked'         => false,
                    'stock_items'       => [],
                    'message'           => 'Qty diminta diubah menjadi 0, alokasi SKU sebelumnya dibatalkan.',
                ]);
            }

            $availableStocks = Stock::where('product_id', $item->product_id)
                ->where('qty_available', '>', 0)
                ->where('status', 'available')
                ->orderByRaw('expired_at IS NULL ASC')
                ->orderBy('expired_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $totalAvailable = $availableStocks->sum('qty_available');

            if ($totalAvailable < $newTotalQty) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => "Total stok gudang dari seluruh SKU ({$totalAvailable} pcs) tidak mencukupi kebutuhan baru ({$newTotalQty} pcs).",
                ], 422);
            }

            $remaining = $newTotalQty;
            $stockItemsData = [];
            $isFirst = true;

            foreach ($availableStocks as $stock) {
                if ($remaining <= 0) break;

                $take = min($stock->qty_available, $remaining);

                if ($isFirst) {
                    $item->update([
                        'stock_id'    => $stock->id,
                        'sku'         => $stock->sku,
                        'qty_to_pick' => $take,
                        'qty_picked'  => $take,
                        'is_picked'   => 1,
                    ]);
                    $isFirst = false;
                } else {
                    PickingListItem::create([
                        'picking_list_id' => $pickingList->id,
                        'product_id'      => $item->product_id,
                        'stock_id'        => $stock->id,
                        'sku'             => $stock->sku,
                        'qty_to_pick'     => $take,
                        'qty_picked'      => $take,
                        'location'        => $location,
                        'is_picked'       => 1,
                    ]);
                }

                $stockItemsData[] = [
                    'sku'        => $stock->sku,
                    'qty'        => $take,
                    'expired_at' => $stock->expired_at ? \Carbon\Carbon::parse($stock->expired_at)->format('d/m/Y') : '-',
                ];

                $remaining -= $take;
            }

            DB::commit();

            return response()->json([
                'success'           => true,
                'qty_to_pick_total' => $newTotalQty,
                'is_picked'         => true,
                'stock_items'       => $stockItemsData,
                'message'           => 'Qty diminta berhasil diperbarui & alokasi SKU disesuaikan ulang (FEFO/FIFO).',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal update qty: ' . $e->getMessage(),
            ], 500);
        }
    }

    // Complete & Ship: generate PL, complete, update RO
    public function completeAndShip(Request $request, RequestOrder $requestOrder)
    {
        $pickingList = PickingList::where('request_order_id', $requestOrder->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$pickingList) {
            return response()->json(['success' => false, 'message' => 'Sesi proses kirim tidak valid.'], 422);
        }

        // Hitung jumlah item yang belum sempat di-scan (hanya untuk info di pesan akhir)
        $unpickedCount = $pickingList->items()->where('is_picked', 0)->count();

        DB::beginTransaction();
        try {
            // 1. Proses SEMUA item picking list, tapi potong stok HANYA yang sudah di-pick
            foreach ($pickingList->items as $item) {

                if ($item->is_picked == 1) {
                    // Item sudah discan → potong stok riil seperti biasa
                    $stock = Stock::find($item->stock_id);
                    if ($stock) {
                        $stock->allocate($item->qty_picked);
                    }

                    $requestOrder->items()
                        ->where('product_id', $item->product_id)
                        ->update([
                            'stock_id'     => $item->stock_id,
                            'qty_approved' => $item->qty_picked,
                            'item_status'  => 'picked',
                        ]);
                } else {
                    // Item belum discan → anggap tidak ready, JANGAN potong stok
                    $requestOrder->items()
                        ->where('product_id', $item->product_id)
                        ->update([
                            'qty_approved' => 0,
                            'item_status'  => 'rejected',
                        ]);
                }
            }

            // 2. Selesaikan status Picking List menjadi 'completed'
            $pickingList->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);

            // 3. Selesaikan status RO Utama
            $requestOrder->update([
                'status'        => 'approved',
                'verified_by'   => auth()->id(),
                'verified_date' => now(),
            ]);

            DB::commit();

            $message = $unpickedCount > 0
                ? "Proses selesai. {$unpickedCount} item tidak ready dan tidak diproses, sisanya berhasil dipotong stoknya."
                : 'Seluruh item berhasil diverifikasi dan stok telah resmi dipotong!';

            return response()->json([
                'success'  => true,
                'message'  => $message,
                'redirect' => route('picking-lists.show', $pickingList->id),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan eksekusi data: ' . $e->getMessage()
            ], 500);
        }
    }
}
