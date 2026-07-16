# Penerimaan Export & PO/Pembelian Revisions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** (1) Always show Excel/PDF export buttons on Penerimaan list and gracefully handle missing stocks in exports. (2) Remove the supplier→product cascading filter on PO create, and add a "Cek Barang" modal that lets users pick under-minimum-stock products before filling the repeater.

**Architecture:**
- Penerimaan: Fixes are contained in `PembelianController::penerimaanIndex()` eager-load and a null-safety guard in `PenerimaanExport::styles()`. The PDF view already handles empty stocks gracefully.
- PO Create: A new controller method + route serves all products with stock data. The existing supplier-change JS block is replaced with a page-load fetch. A Bootstrap modal wraps a client-side DataTable for product selection; on confirm it injects repeater rows using the existing `addBahanBaku()` pattern.

**Tech Stack:** Laravel 9, Blade, Bootstrap 5, Alpine.js, jQuery, Select2, jQuery Mask, Maatwebsite Excel, DomPDF.

---

## File Map

| Action | File |
|--------|------|
| Modify | `app/Http/Controllers/PembelianController.php` |
| Modify | `app/Exports/PenerimaanExport.php` |
| Modify | `routes/web.php` |
| Modify | `resources/views/pembelians/create.blade.php` |

---

## Task 1: Fix Penerimaan - Eager Load Stocks + Null-Safe Export

**Files:**
- Modify: `app/Http/Controllers/PembelianController.php:151-157`
- Modify: `app/Exports/PenerimaanExport.php` (styles method, two `Carbon::parse` calls)

### Context
`penerimaanIndex()` loads `supplier` and `pembelianProducts.product` but NOT `stocks`, causing N+1 lazy loads for every row (the view calls `$value->stocks->where(...)`). Also `PenerimaanExport::styles()` calls `Carbon::parse($this->pembelian->receipt_date)` without a null check — draft POs have `receipt_date = null`, which throws a `Carbon` exception when the export is triggered before the GR form is filled.

- [ ] **Step 1: Add `stocks` to penerimaanIndex eager load**

In `app/Http/Controllers/PembelianController.php`, replace:

```php
public function penerimaanIndex()
{
    $pembelians = Pembelian::with(['supplier', 'pembelianProducts.product'])
        ->latest()
        ->get();

    return view('pembelians.penerimaan-index', compact('pembelians'));
}
```

with:

```php
public function penerimaanIndex()
{
    $pembelians = Pembelian::with(['supplier', 'pembelianProducts.product', 'stocks'])
        ->latest()
        ->get();

    return view('pembelians.penerimaan-index', compact('pembelians'));
}
```

- [ ] **Step 2: Guard both Carbon::parse calls in PenerimaanExport::styles()**

In `app/Exports/PenerimaanExport.php`, find these two lines inside the `else` block of `styles()`:

```php
$sheet->setCellValue('D11', Carbon::parse($this->pembelian->receipt_date)->isoFormat('DD MMMM YYYY HH:mm'));
```
and
```php
$sheet->setCellValue('D15', Carbon::parse($this->pembelian->receipt_date)->isoFormat('DD MMMM YYYY'));
```

Replace both with null-safe versions:

```php
$sheet->setCellValue('D11', $this->pembelian->receipt_date
    ? Carbon::parse($this->pembelian->receipt_date)->isoFormat('DD MMMM YYYY HH:mm')
    : '-');
```
and
```php
$sheet->setCellValue('D15', $this->pembelian->receipt_date
    ? Carbon::parse($this->pembelian->receipt_date)->isoFormat('DD MMMM YYYY')
    : '-');
```

Do the same for the `if ($this->type === 'outlet')` block — `D11` and `D15` exist there too:

```php
$sheet->setCellValue('D11', $this->pembelian->receipt_date
    ? Carbon::parse($this->pembelian->receipt_date)->isoFormat('DD MMMM YYYY HH:mm')
    : '-');
```
and
```php
$sheet->setCellValue('D15', $this->pembelian->receipt_date
    ? Carbon::parse($this->pembelian->receipt_date)->isoFormat('DD MMMM YYYY')
    : '-');
```

- [ ] **Step 3: Smoke-test both exports manually**

Navigate to `/penerimaan` in browser. Find a PO with `receipt_status = 'draft'` (no receipt date). Click the Excel GR button — should download without error. Click the PDF GR button — should render with `-` for date fields.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/PembelianController.php app/Exports/PenerimaanExport.php
git commit -m "fix: penerimaan export null-safe date and eager-load stocks"
```

---

## Task 2: Add All-Products Endpoint (Removes Supplier Filter)

**Files:**
- Modify: `app/Http/Controllers/PembelianController.php` (add `getAllProducts()` method)
- Modify: `routes/web.php` (add route before the pembelian resource)

### Context
Currently product selects in the PO form start empty and only populate after a supplier is chosen (via `getProductsBySupplier`). We need a new endpoint that returns ALL products with their current stock qty and min_stock, so products load on page load regardless of supplier selection.

The new route must be registered **before** `Route::resource('/pembelian', ...)` in `web.php`, otherwise Laravel's resource router treats `cek-stok-produk` as a `{pembelian}` ID and 404s.

- [ ] **Step 1: Add `getAllProducts()` to PembelianController**

In `app/Http/Controllers/PembelianController.php`, add this method after `getProductsBySupplier()` (around line 53):

```php
public function getAllProducts()
{
    $products = Product::select('id', 'code', 'name', 'is_serialized', 'harga_beli', 'min_stock')
        ->orderBy('name')
        ->get()
        ->map(function ($product) {
            $currentStock = $product->stocks()->sum('qty_available');
            $product->stock_count = $currentStock;
            $product->is_under_minimum = $currentStock <= $product->min_stock;
            return $product;
        });

    return response()->json($products);
}
```

- [ ] **Step 2: Register the route before the pembelian resource in web.php**

In `routes/web.php`, find the line:
```php
Route::resource('/pembelian', PembelianController::class);
```

Add this immediately **above** it:
```php
Route::get('/pembelian/cek-stok-produk', [PembelianController::class, 'getAllProducts'])->name('pembelian.all-products');
```

- [ ] **Step 3: Verify route registers correctly**

```bash
php artisan route:list --name=pembelian.all-products
```

Expected output: one row showing `GET | pembelian/cek-stok-produk`.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/PembelianController.php routes/web.php
git commit -m "feat: add all-products endpoint for PO form, independent of supplier"
```

---

## Task 3: PO Create - Load Products on Page Load, Remove Supplier Cascade

**Files:**
- Modify: `resources/views/pembelians/create.blade.php`

### Context
The current JS in `create.blade.php` hooks into `$('select[name="supplier_id"]').on('change', ...)` to populate product selects. We want products to be available immediately on page load. The supplier dropdown stays (it's saved on the PO) but product options no longer depend on it.

We keep `currentProducts` as the JS variable so that `addBahanBaku()` (which calls `populateProductSelects(currentProducts, ...)`) keeps working for dynamically added rows.

- [ ] **Step 1: Replace the supplier change handler with a page-load fetch**

In `resources/views/pembelians/create.blade.php`, find and replace the entire supplier change block:

```javascript
$('select[name="supplier_id"]').on('change', function() {
    let supplierId = $(this).val();
    if (!supplierId) {
        // If no supplier selected, clear all product selects
        $('.product').empty().append('<option value="" disabled selected>Pilih Produk</option>').trigger(
            'change.select2');
        currentProducts = null;
        return;
    }

    // Fetch products for this supplier
    $.get('/supplier/' + supplierId + '/products', function(products) {
        currentProducts = products;
        populateProductSelects(products);
    });
});
```

with:

```javascript
// Load all products on page load (no supplier filter)
$.get('{{ route("pembelian.all-products") }}', function(products) {
    currentProducts = products;
    populateProductSelects(products);
});
```

- [ ] **Step 2: Verify the repeater works without supplier selection**

Run `php artisan serve` and visit `/pembelian/create`. Without selecting a supplier, the first product dropdown should be populated with all products. Clicking "Add" should add a new row with the same product list populated.

- [ ] **Step 3: Commit**

```bash
git add resources/views/pembelians/create.blade.php
git commit -m "feat: PO form loads all products on page load, removes supplier cascade"
```

---

## Task 4: PO Create - Cek Barang Modal

**Files:**
- Modify: `resources/views/pembelians/create.blade.php`

### Context
Add a "Cek Barang" button that opens a Bootstrap modal. The modal contains a client-side DataTable fed from the `currentProducts` JS variable (already loaded in Task 3). Products are sorted with under-minimum stock first. Each row has a checkbox + a qty input pre-filled with `max(1, min_stock - current_stock)`. On "Tambahkan ke PO" confirm, the checked products are pushed into the repeater.

The modal uses the same `addBahanBaku()` function (which appends a row), then the new code finds that row and sets product_id + qty + triggers the product change event to auto-fill `harga_beli`.

- [ ] **Step 1: Add "Cek Barang" button above the repeater table**

In `create.blade.php`, find:
```html
{{-- //TODO add Product pop-up (nanti bisa lanjut di select²) --}}
<button class="btn btn-sm btn-primary" onclick="addBahanBaku()" type="button">Add</button>
```

Replace with:
```html
<div class="d-flex gap-2 mb-2">
    <button class="btn btn-sm btn-warning" type="button" data-toggle="modal" data-target="#modalCekBarang">
        <i class="fa fa-search"></i> Cek Barang
    </button>
    <button class="btn btn-sm btn-primary" onclick="addBahanBaku()" type="button">
        <i class="fa fa-plus"></i> Add Row
    </button>
</div>
```

- [ ] **Step 2: Add the modal HTML at the bottom of the form, before `</form>`**

After the closing `</div><!-- /.box-body -->` and before `</form>`, insert:

```html
<!-- Modal Cek Barang -->
<div class="modal fade" id="modalCekBarang" tabindex="-1" role="dialog" aria-labelledby="modalCekBarangLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modalCekBarangLabel">
                    <i class="fa fa-search"></i> Pilih Produk untuk PO
                    <small class="text-warning">— diurutkan dari stok paling kritis</small>
                </h4>
            </div>
            <div class="modal-body">
                <table id="tableCekBarang" class="table table-bordered table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="checkAll"></th>
                            <th>Kode</th>
                            <th>Nama Produk</th>
                            <th>Stok Saat Ini</th>
                            <th>Min Stok</th>
                            <th>Status</th>
                            <th width="90">Qty Order</th>
                        </tr>
                    </thead>
                    <tbody id="cekBarangBody"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnTambahkanPO">
                    <i class="fa fa-check"></i> Tambahkan ke PO
                </button>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 3: Add modal JS to the `@section('page-script')` block**

At the end of the existing `<script>` block (before the closing `</script>`), add:

```javascript
// ---- Cek Barang Modal ----
let cekBarangTable = null;

$('#modalCekBarang').on('show.bs.modal', function () {
    if (!currentProducts || currentProducts.length === 0) {
        alert('Data produk belum dimuat. Coba muat ulang halaman.');
        return false;
    }

    const sorted = [...currentProducts].sort((a, b) => {
        // Under-minimum first (ascending by stock_count), then by name
        const aUnder = a.is_under_minimum ? 0 : 1;
        const bUnder = b.is_under_minimum ? 0 : 1;
        if (aUnder !== bUnder) return aUnder - bUnder;
        return a.stock_count - b.stock_count;
    });

    const tbody = $('#cekBarangBody');
    tbody.empty();

    sorted.forEach(function (p) {
        const isUnder = p.is_under_minimum;
        const suggestedQty = Math.max(1, (p.min_stock || 0) - (p.stock_count || 0));
        const statusBadge = isUnder
            ? '<span class="label label-danger">OUT OF STOCK</span>'
            : '<span class="label label-success">Normal</span>';

        tbody.append(`
            <tr class="${isUnder ? 'danger' : ''}">
                <td class="text-center">
                    <input type="checkbox" class="cek-product-check" value="${p.id}"
                        data-name="${p.name}" data-harga="${p.harga_beli || 0}">
                </td>
                <td>${p.code}</td>
                <td>${p.name}</td>
                <td class="text-center">${p.stock_count || 0}</td>
                <td class="text-center">${p.min_stock || 0}</td>
                <td class="text-center">${statusBadge}</td>
                <td>
                    <input type="number" class="form-control input-sm cek-qty"
                        value="${isUnder ? suggestedQty : 1}" min="1" style="width:70px">
                </td>
            </tr>
        `);
    });

    // Destroy and reinit DataTable
    if (cekBarangTable) {
        cekBarangTable.destroy();
    }
    cekBarangTable = $('#tableCekBarang').DataTable({
        retrieve: false,
        destroy: true,
        pageLength: 10,
        order: [],
        columnDefs: [
            { orderable: false, targets: [0, 6] }
        ],
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ baris",
            info: "Menampilkan _START_-_END_ dari _TOTAL_ produk",
            paginate: { previous: "Prev", next: "Next" },
            zeroRecords: "Tidak ada produk ditemukan"
        }
    });
});

// Check-all toggle
$('#checkAll').on('change', function () {
    $('.cek-product-check').prop('checked', $(this).prop('checked'));
});

// Tambahkan ke PO
$('#btnTambahkanPO').on('click', function () {
    const selected = [];
    $('#cekBarangBody .cek-product-check:checked').each(function () {
        const $row = $(this).closest('tr');
        selected.push({
            product_id: $(this).val(),
            name: $(this).data('name'),
            harga: $(this).data('harga'),
            qty: parseInt($row.find('.cek-qty').val()) || 1
        });
    });

    if (selected.length === 0) {
        alert('Pilih minimal satu produk.');
        return;
    }

    // Remove the first empty row if it has no product selected
    const $firstRow = $('#product-repeater tr:first');
    if ($firstRow.find('.product').val() === null || $firstRow.find('.product').val() === '') {
        $firstRow.remove();
        productIndex = -1;
    }

    selected.forEach(function (item) {
        addBahanBaku(); // appends a new row and increments productIndex

        const $newRow = $('#product-repeater tr:last');

        // Set product_id in select and trigger change to fill harga_beli
        $newRow.find('.product').val(item.product_id).trigger('change');

        // Set qty after a short delay so harga_beli fetch completes
        setTimeout(function () {
            $newRow.find('.qty').val(item.qty).trigger('change');
            updateSubtotalAndTotal();
        }, 300);
    });

    $('#modalCekBarang').modal('hide');
});
```

- [ ] **Step 4: Smoke-test the modal**

Visit `/pembelian/create` in the browser:
1. Click "Cek Barang" — modal opens, shows product table sorted by under-stock first
2. Check 2 products, adjust qty in one row
3. Click "Tambahkan ke PO" — modal closes, repeater gains the selected rows with product and qty filled
4. Harga beli should auto-populate after the AJAX call per product

- [ ] **Step 5: Commit**

```bash
git add resources/views/pembelians/create.blade.php
git commit -m "feat: add Cek Barang modal to PO create for under-minimum stock product selection"
```

---

## Self-Review Checklist

### Spec Coverage

| Requirement | Task |
|-------------|------|
| Export buttons always visible on penerimaan list | Already uncommented in blade — no change needed |
| Export handles missing stocks without error | Task 1 (null date guard in export, eager load) |
| jika stok kosong tampil list nama produk pembelian (in list) | Already implemented in existing blade — list shows `pembelianProducts` |
| PO: hilangkan validasi/koneksi select2 product (supplier filter) | Task 2 + Task 3 |
| PO: Cek Barang pop-up — produk kurang dari minimum, checkbox, qty | Task 4 |
| PO: sort by stok kurang dari minimum | Task 4 (sorted array before DataTable init) |
| PO: tampil "out of stock" jika stok kurang dari min | Task 4 (status badge in modal) |
| PO: qty berdasarkan min_stok - stok saat ini | Task 4 (suggestedQty calculation) |
| PO: baru muncul form repeater setelah pilih produk dari modal | Task 4 (empty first row removed on confirm) |

### Placeholder Scan
No TBD, TODO, or "similar to" references remain in the plan.

### Type Consistency
- `currentProducts` → JS array of product objects with `id, code, name, is_serialized, harga_beli, min_stock, stock_count, is_under_minimum`
- `getAllProducts()` sets `stock_count` and `is_under_minimum` on each product — matches modal JS references
- `addBahanBaku()` uses `productIndex` (already in scope) — no rename
- `populateProductSelects(currentProducts)` called the same way as before — no signature change
