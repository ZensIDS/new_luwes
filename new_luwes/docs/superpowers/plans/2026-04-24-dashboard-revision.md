# Dashboard Revision Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two new dashboard widgets: (1) a near-expiry stock table showing items expiring within 30 days, and (2) an auto-calculated below-minimum-stock table derived from average daily sales velocity (7-day safety stock).

**Architecture:** Both widgets are server-rendered via `DashboardController::index()` passing two new variables to the view. Near-expiry queries `stocks` on `expired_at`. Sales-velocity minimum queries `penjualan_items` for the last 30 days, computes avg daily qty per product, and flags products where current `qty_available` is below `avg_daily * 7`. No new tables or migrations needed — all data already exists.

**Tech Stack:** Laravel 9, Blade, Bootstrap 3 (AdminLTE), Carbon.

**Dependency note:** This plan is independent of the Supplier Deadline plan and the StockMinimumAdjustment plan. If those are already implemented, their widgets will coexist in the same `dashboard/index.blade.php` via the `<!-- WIDGET: X -->` comment markers that each plan uses. Apply this plan's changes around those existing markers without removing them.

---

## Codebase Context (read before touching anything)

- `app/Http/Controllers/DashboardController.php` — `index()` currently passes: `products, stocks, penjualans, pembelianTerkirim, totalRevenue`. Needs two more: `nearExpiryStocks`, `lowVelocityProducts`.
- `resources/views/dashboard/index.blade.php` — stats row + Highcharts chart placeholders. Add the two new widget rows **between** the stats row and the chart row (or below the `<!-- WIDGET: SUPPLIER-DEADLINE -->` block if that plan was already executed).
- `app/Models/Stock.php` — has `expired_at` (datetime, nullable), `qty_available` (stored computed column), `product_id` FK. Relationship: `belongsTo Product`.
- `app/Models/PenjualanItem.php` — has `product_id`, `qty`, `penjualan_id`. BelongsTo `Penjualan` and `Product`.
- `app/Models/Product.php` — has `effective_min_stock` accessor (added by the StockMinimumAdjustment plan). If that plan has NOT been run yet, fall back to `min_stock` column directly in queries.
- **Stock `expired_at`** is stored in `stocks` table (the global warehouse pool). Items with `qty_available > 0` and `expired_at` between now and now+30 days are "near expiry".
- **Average daily sales** = SUM(penjualan_items.qty) for the product in the last 30 days ÷ 30. "Safety stock" = avg_daily × 7. If current `qty_available < safety_stock`, product is flagged.

---

## File Map

| Action | File |
|--------|------|
| Modify | `app/Http/Controllers/DashboardController.php` |
| Modify | `resources/views/dashboard/index.blade.php` |

---

## Task 1: DashboardController — Near-Expiry Stock Query

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`

- [ ] **Step 1: Add the near-expiry query to `index()`**

Read `DashboardController.php` first to see the current state of `index()`.

Add these imports at the top of the file if not already present:
```php
use App\Models\Stock;
use App\Models\PenjualanItem;
use Illuminate\Support\Facades\DB;
```

Inside `index()`, before the `if ($request->wantsJson())` block, add:

```php
$nearExpiryStocks = Stock::with('product:id,name,code')
    ->where('qty_available', '>', 0)
    ->whereNotNull('expired_at')
    ->whereDate('expired_at', '>=', now()->toDateString())
    ->whereDate('expired_at', '<=', now()->addDays(30)->toDateString())
    ->orderBy('expired_at')
    ->get(['id', 'product_id', 'qty_available', 'expired_at', 'batch_number', 'sku']);
```

- [ ] **Step 2: Pass `nearExpiryStocks` to the view**

In the `return view(...)` call at the end of `index()`, add `'nearExpiryStocks' => $nearExpiryStocks` to the array.

The final `return view(...)` should look like:

```php
return view('dashboard.index', [
    'products'          => Product::count(),
    'stocks'            => Stock::sum('qty'),
    'penjualans'        => Penjualan::count(),
    'pembelianTerkirim' => Pembelian::where('is_published', true)->count(),
    'totalRevenue'      => 0,
    'urgentSuppliers'   => $urgentSuppliers ?? collect(), // from supplier-deadline plan, or empty
    'nearExpiryStocks'  => $nearExpiryStocks,
    'lowVelocityProducts' => collect(), // placeholder — filled in Task 2
]);
```

- [ ] **Step 3: Verify syntax**

```bash
php -l app/Http/Controllers/DashboardController.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/DashboardController.php
git commit -m "feat: dashboard — query near-expiry stocks (within 30 days)"
```

---

## Task 2: DashboardController — Sales-Velocity Low-Stock Query

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`

- [ ] **Step 1: Add the sales-velocity query**

In `index()`, after the `$nearExpiryStocks` query, add:

```php
$salesVelocity = PenjualanItem::select(
        'product_id',
        DB::raw('COALESCE(SUM(qty), 0) as total_sold_30d')
    )
    ->where('created_at', '>=', now()->subDays(30))
    ->groupBy('product_id')
    ->get()
    ->keyBy('product_id');

$lowVelocityProducts = Product::select('id', 'code', 'name', 'min_stock')
    ->withSum('stocks', 'qty_available')
    ->orderBy('name')
    ->get()
    ->map(function ($product) use ($salesVelocity) {
        $totalSold30d  = (int) ($salesVelocity->get($product->id)?->total_sold_30d ?? 0);
        $avgDailySales = $totalSold30d / 30;
        $safetyStock   = (int) ceil($avgDailySales * 7);
        $currentStock  = (int) ($product->stocks_sum_qty_available ?? 0);

        $product->avg_daily_sales = round($avgDailySales, 2);
        $product->safety_stock    = $safetyStock;
        $product->current_stock   = $currentStock;
        $product->deficit         = max(0, $safetyStock - $currentStock);

        return $product;
    })
    ->filter(fn ($p) => $p->safety_stock > 0 && $p->current_stock < $p->safety_stock)
    ->sortByDesc('deficit')
    ->values();
```

- [ ] **Step 2: Replace the `lowVelocityProducts` placeholder in the view call**

Update the `return view(...)` call:

```php
return view('dashboard.index', [
    'products'            => Product::count(),
    'stocks'              => Stock::sum('qty'),
    'penjualans'          => Penjualan::count(),
    'pembelianTerkirim'   => Pembelian::where('is_published', true)->count(),
    'totalRevenue'        => 0,
    'urgentSuppliers'     => $urgentSuppliers ?? collect(),
    'nearExpiryStocks'    => $nearExpiryStocks,
    'lowVelocityProducts' => $lowVelocityProducts,
]);
```

- [ ] **Step 3: Verify syntax**

```bash
php -l app/Http/Controllers/DashboardController.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/DashboardController.php
git commit -m "feat: dashboard — sales-velocity safety-stock query (avg_daily × 7)"
```

---

## Task 3: Dashboard View — Near-Expiry Widget

**Files:**
- Modify: `resources/views/dashboard/index.blade.php`

- [ ] **Step 1: Add the near-expiry widget**

In `resources/views/dashboard/index.blade.php`, find the stats row (the first `<div class="row">` containing the `small-box` divs). Insert the near-expiry widget **after** that stats row and **before** the chart row.

If the `<!-- WIDGET: SUPPLIER-DEADLINE -->` block is already present (from the supplier-deadline plan), insert this block **after** it. If not, insert it right after the first stats row.

```html
<!-- WIDGET: NEAR-EXPIRY -->
@if($nearExpiryStocks->isNotEmpty())
<div class="row">
    <div class="col-xs-12">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-clock-o"></i>
                    Stok Mendekati Expired
                    <span class="badge bg-red">{{ $nearExpiryStocks->count() }}</span>
                </h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse">
                        <i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="box-body table-responsive" style="max-height:300px;overflow-y:auto">
                <table class="table table-bordered table-condensed table-hover">
                    <thead>
                        <tr>
                            <th>Produk</th>
                            <th>Kode</th>
                            <th>Batch / SKU</th>
                            <th>Qty Tersedia</th>
                            <th>Tanggal Expired</th>
                            <th>Sisa Hari</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($nearExpiryStocks as $stock)
                            @php
                                $daysLeft = (int) \Carbon\Carbon::today()->diffInDays($stock->expired_at, false);
                            @endphp
                            <tr class="{{ $daysLeft <= 7 ? 'danger' : ($daysLeft <= 14 ? 'warning' : '') }}">
                                <td>{{ $stock->product?->name ?? '—' }}</td>
                                <td>{{ $stock->product?->code ?? '—' }}</td>
                                <td>{{ $stock->batch_number ?? $stock->sku ?? '—' }}</td>
                                <td class="text-center">{{ $stock->qty_available }}</td>
                                <td>{{ \Carbon\Carbon::parse($stock->expired_at)->format('d M Y') }}</td>
                                <td class="text-center">
                                    @if($daysLeft <= 7)
                                        <span class="label label-danger">{{ $daysLeft }} hari</span>
                                    @elseif($daysLeft <= 14)
                                        <span class="label label-warning">{{ $daysLeft }} hari</span>
                                    @else
                                        <span class="label label-default">{{ $daysLeft }} hari</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif
<!-- END WIDGET: NEAR-EXPIRY -->
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/dashboard/index.blade.php
git commit -m "feat: dashboard near-expiry stock widget (≤30 days)"
```

---

## Task 4: Dashboard View — Sales-Velocity Low-Stock Widget

**Files:**
- Modify: `resources/views/dashboard/index.blade.php`

- [ ] **Step 1: Add the low-velocity widget**

Insert this block immediately **after** the `<!-- END WIDGET: NEAR-EXPIRY -->` marker added in Task 3:

```html
<!-- WIDGET: LOW-VELOCITY-STOCK -->
@if($lowVelocityProducts->isNotEmpty())
<div class="row">
    <div class="col-xs-12">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-bar-chart"></i>
                    Produk Stok di Bawah Kebutuhan (Berdasarkan Rata-rata Penjualan)
                    <span class="badge bg-blue">{{ $lowVelocityProducts->count() }}</span>
                </h3>
                <div class="box-tools pull-right">
                    <small class="text-muted">Safety stock = rata-rata penjualan harian × 7 hari</small>
                    <button type="button" class="btn btn-box-tool" data-widget="collapse">
                        <i class="fa fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="box-body table-responsive" style="max-height:350px;overflow-y:auto">
                <table class="table table-bordered table-condensed table-hover">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Produk</th>
                            <th>Stok Saat Ini</th>
                            <th>Safety Stock (7 hari)</th>
                            <th>Rata² Jual/Hari (30 hari)</th>
                            <th>Kekurangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lowVelocityProducts as $p)
                            <tr class="{{ $p->current_stock === 0 ? 'danger' : 'warning' }}">
                                <td>{{ $p->code }}</td>
                                <td>{{ $p->name }}</td>
                                <td class="text-center">{{ $p->current_stock }}</td>
                                <td class="text-center">{{ $p->safety_stock }}</td>
                                <td class="text-center">{{ $p->avg_daily_sales }}</td>
                                <td class="text-center">
                                    <span class="label label-danger">-{{ $p->deficit }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer text-muted">
                <small>
                    <i class="fa fa-info-circle"></i>
                    Hanya produk dengan penjualan dalam 30 hari terakhir yang ditampilkan.
                    Gunakan <strong>Pengaturan Min Stok Produk</strong> untuk menyesuaikan threshold secara manual.
                </small>
            </div>
        </div>
    </div>
</div>
@endif
<!-- END WIDGET: LOW-VELOCITY-STOCK -->
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/dashboard/index.blade.php
git commit -m "feat: dashboard sales-velocity low-stock widget (avg daily × 7)"
```

---

## Self-Review

### Spec Coverage

| Requirement | Task |
|-------------|------|
| Tabel & Pagination barang hampir expired 1 bulan | Task 1 + Task 3 (scrollable table, ≤30 days) |
| Tabel produk minimal stok dari rata-rata penjualan (otomatis) | Task 2 + Task 4 |
| Kolom: produk, kode, batch/SKU, qty, tanggal expired, sisa hari | Task 3 |
| Warna kritis (≤7 hari = red, ≤14 hari = yellow) | Task 3 |
| Rata² penjualan harian × 7 hari sebagai safety stock | Task 2 (avg_daily × 7 = safety_stock) |
| Sort by deficit (kekurangan terbesar di atas) | Task 2 (sortByDesc('deficit')) |
| Only products with actual sales activity shown | Task 2 (filter: safety_stock > 0) |

### Placeholder Scan
None.

### Type Consistency
- `$nearExpiryStocks` — Eloquent Collection of `Stock` objects with `product` relation. Blade accesses `$stock->product->name` via optional chaining `?->` — null-safe. ✓
- `$lowVelocityProducts` — Eloquent Collection of `Product` objects with extra attributes set via `map()`. `avg_daily_sales`, `safety_stock`, `current_stock`, `deficit` — all set in the same map, all used in view. ✓
- `stocks_sum_qty_available` — attribute name from `withSum('stocks', 'qty_available')`. Same pattern used in `PembelianController::getAllProducts()` (already proven working). ✓
- `$urgentSuppliers ?? collect()` — the `??` guard means this view works even if the Supplier Deadline plan hasn't been run yet (the variable won't exist in that case). ✓

### Performance Note
The `$lowVelocityProducts` map calls `withSum` (2 queries for all products) and a pre-keyed `$salesVelocity` collection (1 aggregation query) — total 3 queries, no N+1. The `$nearExpiryStocks` eager-loads `product` with a `select` (2 queries). Dashboard load adds 5 queries total — acceptable.
