# Product Stock Minimum Adjustment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admins temporarily raise the minimum-stock threshold for selected products by a percentage over a date range, so the "Cek Barang" modal and low-stock alerts reflect seasonal or promotional demand without permanently altering `min_stock`.

**Architecture:** A new `product_minimum_adjustments` table stores per-product percentage adjustments with an active date range. The `Product` model gains an `effective_min_stock` accessor that returns the adjusted threshold when an active record exists (otherwise falls back to bare `min_stock`). A new `ProductMinimumAdjustmentController` handles AJAX save. The UI is a Bootstrap 3 modal on the dashboard (trigger button) + DataTable product checklist. `PembelianController::getAllProducts()` and `Product::isLowStock()` are updated to use `effective_min_stock` so downstream features stay correct.

**Tech Stack:** Laravel 9, Blade, Bootstrap 3 (AdminLTE), jQuery, DataTables.

---

## Codebase Context (read before touching anything)

- `app/Models/Product.php` — has `min_stock` (int, default 0). `isLowStock()` currently uses `total_available_stock <= min_stock`. `stocks()` hasMany → `Stock`. `$fillable` does NOT include `min_stock` from code; it's in the DB via migration `2026_02_14_064719_add_warehouse_fields_to_products.php`.
- `app/Http/Controllers/PembelianController.php` — `getAllProducts()` sets `$product->is_under_minimum = $currentStock <= ($product->min_stock ?? 0)`. This must be updated to use `effective_min_stock`.
- `resources/views/dashboard/index.blade.php` — add a "Pengaturan Min Stok" button and modal. The modal trigger button goes in the box-header area at the top.
- Routes file: `routes/web.php` — new route must be registered. Follows the pattern `Route::post('/product/minimum-adjustment', ...)`.
- `Product::isLowStock()` is used in `getAllProducts()` indirectly (the `is_under_minimum` flag). After this task, the `effective_min_stock` accessor replaces the inline `$product->min_stock` comparison in `getAllProducts()`.
- **Important:** Never mutate `min_stock` on the product — store adjustments separately so the original threshold is preserved.

---

## File Map

| Action | File |
|--------|------|
| Create | `database/migrations/2026_04_24_000002_create_product_minimum_adjustments_table.php` |
| Create | `app/Models/ProductMinimumAdjustment.php` |
| Modify | `app/Models/Product.php` |
| Create | `app/Http/Controllers/ProductMinimumAdjustmentController.php` |
| Modify | `routes/web.php` |
| Modify | `app/Http/Controllers/PembelianController.php` |
| Modify | `resources/views/dashboard/index.blade.php` |

---

## Task 1: Migration — product_minimum_adjustments Table

**Files:**
- Create: `database/migrations/2026_04_24_000002_create_product_minimum_adjustments_table.php`

- [ ] **Step 1: Create the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('product_minimum_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('adjustment_percentage'); // e.g. 20 = +20%
            $table->date('active_from');
            $table->date('active_until')->nullable(); // null = open-ended
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'active_from', 'active_until']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_minimum_adjustments');
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `Migrated: 2026_04_24_000002_create_product_minimum_adjustments_table`.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_24_000002_create_product_minimum_adjustments_table.php
git commit -m "feat: create product_minimum_adjustments table"
```

---

## Task 2: ProductMinimumAdjustment Model

**Files:**
- Create: `app/Models/ProductMinimumAdjustment.php`

- [ ] **Step 1: Create the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMinimumAdjustment extends Model
{
    protected $fillable = [
        'product_id',
        'adjustment_percentage',
        'active_from',
        'active_until',
        'created_by',
    ];

    protected $casts = [
        'active_from'  => 'date',
        'active_until' => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /** Scope: adjustments active on a given date (defaults to today). */
    public function scopeActiveOn($query, $date = null)
    {
        $date = $date ?? now()->toDateString();
        return $query
            ->where('active_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('active_until')->orWhere('active_until', '>=', $date);
            });
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l app/Models/ProductMinimumAdjustment.php
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/ProductMinimumAdjustment.php
git commit -m "feat: ProductMinimumAdjustment model with activeOn scope"
```

---

## Task 3: Product Model — effective_min_stock Accessor

**Files:**
- Modify: `app/Models/Product.php`

- [ ] **Step 1: Add import and accessor to `app/Models/Product.php`**

At the top of the `Product` class, after the existing `use` statements, add the import (if not already present — check the file):

`use App\Models\ProductMinimumAdjustment;` — or use the fully qualified name inline.

Add these two methods to the `Product` class, after the `isLowStock()` method:

```php
/**
 * Returns min_stock raised by the active adjustment percentage, if any.
 * Falls back to bare min_stock when no active adjustment exists.
 */
public function getEffectiveMinStockAttribute(): int
{
    $adjustment = ProductMinimumAdjustment::where('product_id', $this->id)
        ->activeOn()
        ->orderByDesc('active_from')
        ->first();

    if (!$adjustment) {
        return (int) $this->min_stock;
    }

    return (int) ceil($this->min_stock * (1 + $adjustment->adjustment_percentage / 100));
}

public function isLowStock(): bool
{
    return $this->total_available_stock <= $this->effective_min_stock;
}
```

Note: `isLowStock()` now delegates to `effective_min_stock` — this replaces the previous `<= $this->min_stock` comparison. No other callers need to change.

- [ ] **Step 2: Verify syntax**

```bash
php -l app/Models/Product.php
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/Product.php
git commit -m "feat: Product::effective_min_stock accessor, update isLowStock() to use it"
```

---

## Task 4: ProductMinimumAdjustmentController + Route

**Files:**
- Create: `app/Http/Controllers/ProductMinimumAdjustmentController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductMinimumAdjustment;
use Illuminate\Http\Request;

class ProductMinimumAdjustmentController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_ids'           => 'required|array|min:1',
            'product_ids.*'         => 'integer|exists:products,id',
            'adjustment_percentage' => 'required|integer|min:1|max:500',
            'active_from'           => 'required|date',
            'active_until'          => 'nullable|date|after_or_equal:active_from',
        ]);

        $saved = 0;
        foreach ($data['product_ids'] as $productId) {
            ProductMinimumAdjustment::create([
                'product_id'            => $productId,
                'adjustment_percentage' => $data['adjustment_percentage'],
                'active_from'           => $data['active_from'],
                'active_until'          => $data['active_until'] ?? null,
                'created_by'            => auth()->id(),
            ]);
            $saved++;
        }

        return response()->json([
            'success' => true,
            'message' => "Adjustment disimpan untuk {$saved} produk.",
        ]);
    }
}
```

- [ ] **Step 2: Register the route in `routes/web.php`**

Find the product resource line:
```php
Route::resource('/product', ProductController::class);
```

Add immediately **above** it:
```php
Route::post('/product/minimum-adjustment', [App\Http\Controllers\ProductMinimumAdjustmentController::class, 'store'])
    ->name('product.minimum-adjustment.store');
```

- [ ] **Step 3: Verify route is registered**

```bash
php artisan route:list --name=product.minimum-adjustment.store
```

Expected: one row, `POST | product/minimum-adjustment`.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ProductMinimumAdjustmentController.php routes/web.php
git commit -m "feat: ProductMinimumAdjustmentController store + route"
```

---

## Task 5: Update getAllProducts() to Use effective_min_stock

**Files:**
- Modify: `app/Http/Controllers/PembelianController.php`

The current `getAllProducts()` inline comparison `$currentStock <= ($product->min_stock ?? 0)` bypasses the accessor. Replace it with the accessor.

- [ ] **Step 1: Find and update `getAllProducts()` in `PembelianController.php`**

Current code (around the `getAllProducts` method):
```php
$product->is_under_minimum = $currentStock <= ($product->min_stock ?? 0);
```

Replace with:
```php
$product->is_under_minimum = $currentStock <= $product->effective_min_stock;
```

Note: `effective_min_stock` fires one extra query per product (to check `product_minimum_adjustments`). Since `getAllProducts()` already uses `withSum` (2 queries total for all products), this adds N queries for the adjustment check. If N is large, optimize later with an eager-loaded subquery. For now, correctness over performance.

- [ ] **Step 2: Verify syntax**

```bash
php -l app/Http/Controllers/PembelianController.php
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/PembelianController.php
git commit -m "feat: getAllProducts uses effective_min_stock for is_under_minimum flag"
```

---

## Task 6: Dashboard Modal — Product Min Stock Adjustment UI

**Files:**
- Modify: `resources/views/dashboard/index.blade.php`

The modal has:
1. A trigger button in the dashboard header area.
2. A Bootstrap 3 modal with a DataTable of all products (code, name, current stock, min stock, effective min stock).
3. Checkboxes for product selection, percentage input, date range inputs.
4. AJAX POST to `product.minimum-adjustment.store`.

- [ ] **Step 1: Add the trigger button**

In `resources/views/dashboard/index.blade.php`, find the `<section class="content-header">` block:
```html
<section class="content-header">
    <h1>Dashboard</h1>
</section>
```

Replace with:
```html
<section class="content-header">
    <h1>
        Dashboard
        <small>
            <button type="button" class="btn btn-xs btn-warning" data-toggle="modal" data-target="#modalMinStockAdj">
                <i class="fa fa-sliders"></i> Pengaturan Min Stok Produk
            </button>
        </small>
    </h1>
</section>
```

- [ ] **Step 2: Add the modal HTML**

Add this at the very end of `@section('container')`, after the closing `</section><!-- /.content -->`:

```html
<!-- WIDGET: STOCK-ADJUSTMENT-MODAL -->
<div class="modal fade" id="modalMinStockAdj" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <i class="fa fa-sliders"></i> Pengaturan Perubahan Minimal Stok Produk
                </h4>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Persentase Kenaikan (%)</label>
                            <input type="number" class="form-control" id="adjPercentage"
                                min="1" max="500" placeholder="Contoh: 20 untuk +20%">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Aktif Dari</label>
                            <input type="date" class="form-control" id="adjActiveFrom"
                                value="{{ now()->toDateString() }}">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Aktif Sampai <small class="text-muted">(kosongkan = selamanya)</small></label>
                            <input type="date" class="form-control" id="adjActiveUntil">
                        </div>
                    </div>
                </div>
                <table id="tableAdjProducts" class="table table-bordered table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="adjCheckAll"></th>
                            <th>Kode</th>
                            <th>Nama Produk</th>
                            <th>Stok Saat Ini</th>
                            <th>Min Stok</th>
                            <th>Min Efektif (sekarang)</th>
                        </tr>
                    </thead>
                    <tbody id="adjProductBody">
                        @foreach(\App\Models\Product::select('id','code','name','min_stock')
                            ->orderBy('name')->get() as $p)
                            @php
                                $currentStock   = $p->stocks()->sum('qty_available');
                                $effectiveMin   = $p->effective_min_stock;
                            @endphp
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" class="adj-product-check" value="{{ $p->id }}">
                                </td>
                                <td>{{ $p->code }}</td>
                                <td>{{ $p->name }}</td>
                                <td class="text-center">{{ $currentStock }}</td>
                                <td class="text-center">{{ $p->min_stock }}</td>
                                <td class="text-center">
                                    {{ $effectiveMin }}
                                    @if($effectiveMin > $p->min_stock)
                                        <span class="label label-info">+{{ $effectiveMin - $p->min_stock }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSimpanAdj">
                    <i class="fa fa-save"></i> Simpan Adjustment
                </button>
            </div>
        </div>
    </div>
</div>
<!-- END WIDGET: STOCK-ADJUSTMENT-MODAL -->
```

- [ ] **Step 3: Add the modal JS to `@section('page-script')`**

Add inside the existing `<script>` block in `@section('page-script')`:

```javascript
// ---- Min Stock Adjustment Modal ----
let adjTable = null;

$('#modalMinStockAdj').on('shown.bs.modal', function () {
    if (!adjTable) {
        adjTable = $('#tableAdjProducts').DataTable({
            pageLength: 10,
            order: [[2, 'asc']],
            columnDefs: [{ orderable: false, targets: [0] }],
            language: {
                search: "Cari:",
                lengthMenu: "Tampilkan _MENU_ baris",
                info: "Menampilkan _START_-_END_ dari _TOTAL_ produk",
                paginate: { previous: "Prev", next: "Next" },
                zeroRecords: "Tidak ada produk"
            }
        });
    }
});

$(document).on('change', '#adjCheckAll', function () {
    const checked = $(this).prop('checked');
    if (adjTable) {
        adjTable.rows().nodes().each(function (node) {
            $(node).find('.adj-product-check').prop('checked', checked);
        });
    }
});

$('#btnSimpanAdj').on('click', function () {
    const productIds = [];
    if (adjTable) {
        adjTable.rows().nodes().each(function (node) {
            const $cb = $(node).find('.adj-product-check:checked');
            if ($cb.length) productIds.push($cb.val());
        });
    }

    const pct       = parseInt($('#adjPercentage').val()) || 0;
    const activeFrom = $('#adjActiveFrom').val();
    const activeUntil = $('#adjActiveUntil').val();

    if (productIds.length === 0) { alert('Pilih minimal satu produk.'); return; }
    if (pct < 1)                  { alert('Persentase kenaikan minimal 1%.'); return; }
    if (!activeFrom)              { alert('Isi tanggal aktif dari.'); return; }

    $('#btnSimpanAdj').prop('disabled', true).text('Menyimpan...');

    $.ajax({
        url: '{{ route("product.minimum-adjustment.store") }}',
        method: 'POST',
        data: {
            _token:                 '{{ csrf_token() }}',
            product_ids:            productIds,
            adjustment_percentage:  pct,
            active_from:            activeFrom,
            active_until:           activeUntil || null,
        },
        success: function (res) {
            alert(res.message);
            $('#modalMinStockAdj').modal('hide');
            location.reload();
        },
        error: function (xhr) {
            const msg = xhr.responseJSON?.message || 'Terjadi kesalahan.';
            alert('Gagal: ' + msg);
        },
        complete: function () {
            $('#btnSimpanAdj').prop('disabled', false).text('Simpan Adjustment');
        }
    });
});
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/dashboard/index.blade.php
git commit -m "feat: dashboard modal for product min-stock percentage adjustment"
```

---

## Self-Review

### Spec Coverage

| Requirement | Task |
|-------------|------|
| Modal pop-up setting perubahan minimal stok produk | Task 6 |
| Pilihan Produk (checklist) DataTable | Task 6 |
| Input angka (persentase kenaikan) | Task 6 |
| Input tanggal aktif (range: dari – sampai) | Task 6 |
| Backend save per-product adjustment with date range | Task 4 |
| Effective min stock used in Cek Barang modal | Task 5 |
| Effective min stock used in isLowStock() | Task 3 |
| Never mutate original min_stock | Tasks 1–5 (adjustments stored separately) |

### Placeholder Scan
None.

### Type Consistency
- `effective_min_stock` returns `int` → used in `<= $product->effective_min_stock` comparison (int) — consistent.
- `ProductMinimumAdjustment::scopeActiveOn` used in accessor — method name matches.
- `adjTable.rows().nodes()` pattern is same as in `create.blade.php` Cek Barang modal — consistent cross-page behavior.
- `product_ids` sent as array in AJAX → validated as `array` → `foreach` iterates it — consistent.

### N+1 Warning
`getEffectiveMinStockAttribute()` fires one query per product. In `getAllProducts()` (used by Cek Barang), this adds N queries. If the catalog is large (>200 products), optimize by adding a `withExists` or left-join subquery in a follow-up task. For now, correctness first.
