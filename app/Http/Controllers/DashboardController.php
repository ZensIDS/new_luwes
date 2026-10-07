<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Pembelian;
use App\Models\Product;
use App\Models\RefundPembelian;
use App\Models\RequestOrder;
use App\Models\RequestOrderItem;
use App\Models\Stock;
use App\Models\Supplier;
use App\Services\LowStockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        if (in_array($user->role, ['staff-outlet', 'kasir'], true)) {
            $requestOrdersBase = RequestOrder::where('owner_id', $user->outlet_id);

            return view('dashboard.index', [
                'isStaffOutletDashboard' => true,
                'outletRequestTotal' => (clone $requestOrdersBase)->count(),
                'outletRequestPending' => (clone $requestOrdersBase)->where('status', 'pending')->count(),
            ]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'bestBuyProducts'  => [],
                'bestBuySuppliers' => [],
                'salesGraph'       => [],
                'productGraph'     => [],
                'monthlyRevenue'   => [],
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
                if (Carbon::today()->diffInDays($next, false) > 3) {
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

        // Semua angka stok di dashboard memakai SUM(stocks.qty) = stok fisik gudang
        // (sama dengan menu Stok, Produk, dan Laporan).
        // whereDate() membungkus kolom dengan DATE() dan mematikan index -> pakai rentang waktu.
        $nearExpiryStocks = Stock::with('product:id,name,code')
            ->where('qty', '>', 0)
            ->whereNotNull('expired_at')
            ->where('expired_at', '>=', now()->startOfDay())
            ->where('expired_at', '<=', now()->addDays(60)->endOfDay())
            ->orderBy('expired_at')
            ->get(['id', 'product_id', 'qty', 'expired_at', 'batch_number', 'sku']);

        // Dihitung di SQL (realtime) lewat service yang sama dengan lonceng notifikasi.
        $lowVelocityProducts = app(LowStockService::class)->dashboardList();

        // Stat cards
        $totalStock        = (int) Stock::sum('qty'); // dipakai juga untuk kartu 'stocks' (tidak dihitung dua kali)
        $pendingOrdersCount = RequestOrder::where('status', 'pending')->count();
        $deliveredCount    = DeliveryOrder::where('status', 'delivered')->count();
        $refundCount       = RefundPembelian::count();
        $pendingOwnerApprovals = Pembelian::with(['supplier'])
            ->where('owner_approval_status', 'pending')
            ->latest()
            ->limit(5)
            ->get();

        // Top 5 products by stok gudang (inventory chart)
        $inventoryChart = Stock::selectRaw('product_id, SUM(qty) as total_qty')
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

        // Slow moving: produk yang tidak ada pengiriman dalam 90 hari terakhir.
        // NOT EXISTS agar MySQL yang menyaring (tidak membuat daftar ID besar di PHP).
        $deliveredSince = now()->subDays(90);

        $slowMovingProducts = Product::select('id', 'code', 'name')
            ->withSum('stocks', 'qty')
            ->whereNotExists(function ($query) use ($deliveredSince) {
                $query->select(DB::raw(1))
                    ->from('delivery_order_items as doi')
                    ->whereColumn('doi.product_id', 'products.id')
                    ->where('doi.created_at', '>=', $deliveredSince);
            })
            ->orderByDesc('stocks_sum_qty')
            ->limit(5)
            ->get();

        return view('dashboard.index', [
            'isStaffOutletDashboard' => false,
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
            'returnPinConfigured' => filled($settings['return_pin_hash'] ?? null),
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'name'    => 'required',
            'email'   => 'required|email',
            'telp'    => 'required',
            'address' => 'required',
            'website' => 'nullable|url',
            'logo'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
        if (auth()->user()?->role === 'superadmin') {
            $rules['return_pin'] = 'nullable|digits_between:4,8';
        }

        $this->validate($request, $rules, [
            'logo.image' => 'File yang diunggah harus berupa gambar.',
            'logo.mimes' => 'Logo harus bertipe: jpeg, png, jpg, atau gif.',
            'logo.max'   => 'Ukuran logo maksimal 2 MB.',
        ]);

        $settings = json_decode(Storage::disk('public')->get('settings.json') ?? '{}', true) ?? [];
        $data = array_merge($settings, [
            'name'    => $request->name,
            'email'   => $request->email,
            'telp'    => $request->telp,
            'address' => $request->address,
            'website' => $request->website,
        ]);

        if (auth()->user()?->role === 'superadmin' && $request->filled('return_pin')) {
            $data['return_pin_hash'] = Hash::make((string) $request->input('return_pin'));
        }
        unset($data['return_pin']);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $data['logo'] = $path;
        }

        Storage::disk('public')->put('settings.json', json_encode($data));

        return redirect(route('setting'))->with('toast_success', 'Berhasil Menyimpan Data!');
    }
}