<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\Product;
use App\Models\RefundPembelian;
use App\Models\RequestOrder;
use App\Models\RequestOrderItem;
use App\Models\Stock;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if ($user->role === 'staff-outlet') {
            $requestOrdersBase = RequestOrder::where('owner_id', $user->outlet_id);

            return view('dashboard.index', [
                'isStaffOutletDashboard' => true,
                'outletRequestTotal' => (clone $requestOrdersBase)->count(),
                'outletRequestPending' => (clone $requestOrdersBase)->where('status', 'pending')->count(),
            ]);
        }

        $urgentSuppliers = Supplier::whereNotNull('deadline_days')
            ->whereNotNull('deadline_interval_weeks')
            ->with(['pembelians' => fn ($q) => $q->where('created_at', '>=', now()->subWeeks(4))])
            ->get()
            ->filter(function ($s) {
                $next = $s->nextDeadlineDate();
                if (! $next) {
                    return false;
                }
                if (\Carbon\Carbon::today()->diffInDays($next, false) > 3) {
                    return false;
                }
                if ($s->hasPembelianInCurrentInterval($next)) {
                    return false;
                }
                $s->next_deadline = $next;

                return true;
            })
            ->sortBy('next_deadline')
            ->values();

        $nearExpiryStocks = Stock::with('product:id,name,code')
            ->where('qty_available', '>', 0)
            ->whereNotNull('expired_at')
            ->whereDate('expired_at', '>=', now()->toDateString())
            ->whereDate('expired_at', '<=', now()->addDays(60)->toDateString())
            ->orderBy('expired_at')
            ->get(['id', 'product_id', 'qty_available', 'expired_at', 'batch_number', 'sku']);

        $activeAdjustments = \App\Models\ProductMinimumAdjustment::query()
            ->activeOn()
            ->orderByDesc('active_from')
            ->orderByDesc('id')
            ->get()
            ->groupBy('product_id');

        $lowVelocityProducts = Product::select('id', 'code', 'name', 'min_stock')
            ->withSum('stocks', 'qty_available')
            ->where('min_stock', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(function ($product) use ($activeAdjustments) {
                $adj          = $activeAdjustments->get($product->id)?->first();
                $effectiveMin = $adj
                    ? (int) ceil($product->min_stock * (1 + $adj->adjustment_percentage / 100))
                    : (int) $product->min_stock;
                $currentStock = (int) ($product->stocks_sum_qty_available ?? 0);

                $product->effective_min         = $effectiveMin;
                $product->current_stock         = $currentStock;
                $product->adjustment_percentage = $adj?->adjustment_percentage ?? 0;
                $product->deficit               = max(0, $effectiveMin - $currentStock);

                return $product;
            })
            ->filter(fn ($p) => $p->current_stock <= $p->effective_min)
            ->sortByDesc('deficit')
            ->values();

        // Stat cards
        $totalStock        = (int) Stock::sum('qty_available');
        $pendingOrdersCount = RequestOrder::where('status', 'pending')->count();
        $deliveredCount    = DeliveryOrder::where('status', 'delivered')->count();
        $refundCount       = RefundPembelian::count();
        $pendingOwnerApprovals = Pembelian::with(['supplier'])
            ->where('owner_approval_status', 'pending')
            ->latest()
            ->limit(5)
            ->get();

        // Top 5 products by available stock (inventory chart)
        $inventoryChart = Stock::selectRaw('product_id, SUM(qty_available) as total_qty')
            ->with('product:id,name,code')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // Top 5 most requested products (status order donut chart)
        $statusOrderChart = RequestOrderItem::selectRaw('product_id, SUM(qty_requested) as total_qty')
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // Top 5 products most delivered to outlets
        $topProducts = DeliveryOrderItem::selectRaw('product_id, SUM(qty_sent) as total_qty')
            ->with('product:id,name,code')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // 5 most recent request orders
        $recentOrders = RequestOrder::with(['owner:id,name'])
            ->latest()
            ->limit(5)
            ->get();

        // Slow moving: products with stock but not delivered in last 90 days
        $recentlyDeliveredIds = DeliveryOrderItem::where('created_at', '>=', now()->subDays(90))
            ->distinct()
            ->pluck('product_id');

        $slowMovingProducts = Product::select('id', 'code', 'name')
            ->withSum('stocks', 'qty_available')
            ->whereNotIn('id', $recentlyDeliveredIds)
            ->orderByDesc('stocks_sum_qty_available')
            ->limit(5)
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'bestBuyProducts'  => [],
                'bestBuySuppliers' => [],
                'salesGraph'       => [],
                'productGraph'     => [],
                'monthlyRevenue'   => [],
            ]);
        }

        $adjustmentProducts = Product::select('id', 'code', 'name', 'min_stock')
            ->withSum('stocks', 'qty_available')
            ->orderBy('name')
            ->get()
            ->map(function ($p) use ($activeAdjustments) {
                $adj = $activeAdjustments->get($p->id)?->first();
                $p->active_from   = $adj?->active_from;
                $p->active_until  = $adj?->active_until;
                $p->current_stock = (int) ($p->stocks_sum_qty_available ?? 0);
                $p->effective_min = $adj
                    ? (int) ceil($p->min_stock * (1 + $adj->adjustment_percentage / 100))
                    : (int) $p->min_stock;

                return $p;
            });

        return view('dashboard.index', [
            'isStaffOutletDashboard' => false,
            'products'           => Product::count(),
            'stocks'             => Stock::sum('qty'),
            'penjualans'         => Penjualan::count(),
            'pembelianTerkirim'  => Pembelian::where('is_published', true)->count(),
            'totalRevenue'       => 0,
            // Stat cards
            'totalStock'         => $totalStock,
            'pendingOrdersCount' => $pendingOrdersCount,
            'deliveredCount'     => $deliveredCount,
            'refundCount'        => $refundCount,
            'lowStockCount'      => $lowVelocityProducts->count(),
            'pendingOwnerApprovalCount' => $pendingOwnerApprovals->count(),
            // Charts
            'inventoryChart'     => $inventoryChart,
            'statusOrderChart'   => $statusOrderChart,
            'topProducts'        => $topProducts,
            // Tables
            'recentOrders'       => $recentOrders,
            'slowMovingProducts' => $slowMovingProducts,
            // Existing widgets
            'urgentSuppliers'    => $urgentSuppliers,
            'nearExpiryStocks'   => $nearExpiryStocks,
            'lowVelocityProducts' => $lowVelocityProducts,
            'adjustmentProducts' => $adjustmentProducts,
            'pendingOwnerApprovals' => $pendingOwnerApprovals,
        ]);
    }

    public function setting()
    {
        $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

        return view('dashboard.setting', [
            'name'    => $settings['name'] ?? '',
            'email'   => $settings['email'] ?? '',
            'telp'    => $settings['telp'] ?? '',
            'address' => $settings['address'] ?? '',
            'website' => $settings['website'] ?? '',
            'logo'    => $settings['logo'] ?? '',
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name'    => 'required',
            'email'   => 'required|email',
            'telp'    => 'required',
            'address' => 'required',
            'website' => 'nullable|url',
            'logo'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'logo.image' => 'File yang diunggah harus berupa gambar.',
            'logo.mimes' => 'Logo harus bertipe: jpeg, png, jpg, atau gif.',
            'logo.max'   => 'Ukuran logo maksimal 2 MB.',
        ]);

        $data = [
            'name'    => $request->name,
            'email'   => $request->email,
            'telp'    => $request->telp,
            'address' => $request->address,
            'website' => $request->website,
        ];

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $data['logo'] = $path;
        }

        Storage::disk('public')->put('settings.json', json_encode($data));

        return redirect(route('setting'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }
}
