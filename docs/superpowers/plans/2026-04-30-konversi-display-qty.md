# Konversi Display for All Qty Fields — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show product quantity in both unit qty and converted unit (e.g. `12 (1 Lusin)`) across all warehouse UI pages, and propagate the same plain-text format to all active Excel and PDF exports.

**Architecture:** PHP blade views call `$product->konversiDisplay($qty)` directly and wrap the result in `<small class="text-muted">($k)</small>` only when it is not `'-'`. JS-rendered views receive `konversi_qty`, `satuan_besar`, and `satuan` from the API and use a shared JS helper function. Excel exports append the konversi string as plain text (no HTML). PDF views either call the model directly or use konversi strings added to data arrays in the controller.

**Tech Stack:** Laravel 9, Blade, Alpine.js, Bootstrap 5, jQuery AJAX, Maatwebsite Excel, barryvdh/laravel-dompdf

---

## Already Done — Skip These

- `resources/views/request-orders/verify.blade.php` — konversi already shown in both STEP 1 and STEP 2
- `resources/views/exports/pdf/laporan-po.blade.php` — already renders `$row['konversi']`
- `app/Http/Controllers/LaporanController.php::pdfPO()` — already adds `'konversi'` key to rows
- `app/Exports/PembelianSingleExport.php` — already has a separate Konversi column
- `app/Exports/StockOpnameExport.php` — already calls `konversiDisplay()`
- `resources/views/exports/pdf/laporan-penerimaan.blade.php` — already uses konversiDisplay

---

## File Structure

**Blade Views (static PHP):**
- `resources/views/stocks/index.blade.php` — 3 qty cells: ownerStock qty, qty_reserved, qty_available
- `resources/views/pembelians/index.blade.php` — Items column list
- `resources/views/request-orders/index.blade.php` — Items column list
- `resources/views/picking-lists/index.blade.php` — Replace count with detail items list (+ controller change)
- `resources/views/picking-lists/pick.blade.php` — qty_to_pick column
- `resources/views/delivery-orders/_send-modal.blade.php` — qty column in send confirmation modal

**Blade Views (JS-rendered, need API/data changes):**
- `resources/views/stocks/kartu.blade.php` — JS rendering; needs konversi fields in API response
- `resources/views/pembelians/create.blade.php` — Cek Barang modal; needs konversi fields in products API
- `resources/views/request-orders/create.blade.php` — Available qty column; product data already has attrs

**Controllers:**
- `app/Http/Controllers/PickingListController.php` — Add `items.product` to index eager load
- `app/Http/Controllers/StockController.php` — Add `konversi_qty`, `satuan_besar`, `satuan` to getKartuData
- `app/Http/Controllers/PembelianController.php` — Add `konversi_qty`, `satuan_besar`, `satuan` to getAllProducts
- `app/Http/Controllers/LaporanController.php` — Add `konversi` keys to pdfPR, pdfPengiriman, pdfPicking rows

**PDF Views:**
- `resources/views/exports/pdf/laporan-pr.blade.php`
- `resources/views/exports/pdf/laporan-picking.blade.php`
- `resources/views/exports/pdf/laporan-pengiriman.blade.php`
- `resources/views/exports/pdf/laporan-opname.blade.php`
- `resources/views/exports/pdf/kartu-stok.blade.php`

**Excel Exports:**
- `app/Exports/RequestOrderSingleExport.php`
- `app/Exports/PickingListSingleExport.php`
- `app/Exports/DeliveryOrderSingleExport.php`
- `app/Exports/KartuStokExport.php`
- `app/Exports/LaporanPOExport.php`
- `app/Exports/LaporanPRExport.php`
- `app/Exports/LaporanPickingPackingExport.php`
- `app/Exports/LaporanPengirimanExport.php`

---

## Shared Patterns

### PHP Blade pattern (use throughout Tasks 1–6)
```blade
{{ $qty }}
@php $k = $product->konversiDisplay($qty); @endphp
@if($k !== '-')
    <small class="text-muted">({{ $k }})</small>
@endif
```
Where `$product` is the related Product model and `$qty` is the numeric value.

### JS konversiDisplay helper (use in Tasks 7–9)
```javascript
function konversiDisplay(qty, konversiQty, satuanBesar, satuan) {
    satuan = satuan || 'PCS';
    qty = parseInt(qty) || 0;
    if (!konversiQty || !satuanBesar) return null;
    var boxes = Math.floor(qty / konversiQty);
    var rem = qty % konversiQty;
    if (rem === 0) return boxes + ' ' + satuanBesar;
    if (boxes > 0) return boxes + ' ' + satuanBesar + ' ' + rem + ' ' + satuan;
    return '1 ' + satuanBesar; // partial box
}
// Usage: var k = konversiDisplay(qty, p.konversi_qty, p.satuan_besar, p.satuan);
// Display: qty + (k ? ' (' + k + ')' : '')
```

### Excel export pattern (use in Tasks 10–11)
```php
// Helper closure — define once at top of map() or collection():
$kd = fn($product, $qty) => ($k = $product?->konversiDisplay($qty)) && $k !== '-' ? " ({$k})" : '';

// In data row:
$item->qty . $kd($item->product, $item->qty),
```

---

## Task 1: stocks/index.blade.php — 3 qty cells

**Files:**
- Modify: `resources/views/stocks/index.blade.php:45-47`

- [ ] **Step 1: Update the 3 qty cells**

Replace lines 45–47:
```blade
<td>{{ $stock->ownerStock?->qty ?? 0 }}</td>
<td>{{ $stock->qty_reserved ?? 0 }}</td>
<td>{{ $stock->qty_available ?? 0 }}</td>
```
With:
```blade
<td>
    @php $v = $stock->ownerStock?->qty ?? 0; $k = $stock->product->konversiDisplay($v); @endphp
    {{ $v }}@if($k !== '-') <small class="text-muted">({{ $k }})</small>@endif
</td>
<td>
    @php $v = $stock->qty_reserved ?? 0; $k = $stock->product->konversiDisplay($v); @endphp
    {{ $v }}@if($k !== '-') <small class="text-muted">({{ $k }})</small>@endif
</td>
<td>
    @php $v = $stock->qty_available ?? 0; $k = $stock->product->konversiDisplay($v); @endphp
    {{ $v }}@if($k !== '-') <small class="text-muted">({{ $k }})</small>@endif
</td>
```

- [ ] **Step 2: Verify visually**

Navigate to `/stock`. Products with konversi configured should show e.g. `24 (2 Lusin)`. Products without konversi show plain number.

- [ ] **Step 3: Commit**
```bash
git add resources/views/stocks/index.blade.php
git commit -m "feat: show konversi display on stock index qty cells"
```

---

## Task 2: pembelians/index.blade.php + request-orders/index.blade.php — Items columns

**Files:**
- Modify: `resources/views/pembelians/index.blade.php:52-54`
- Modify: `resources/views/request-orders/index.blade.php:55-59`

- [ ] **Step 1: Update pembelians/index Items column**

Replace the `<li>` inside the foreach at ~line 53:
```blade
<li><small>{{ $item->product?->name }} × {{ $item->qty }}</small></li>
```
With:
```blade
<li>
    <small>
        {{ $item->product?->name }} × {{ $item->qty }}
        @php $k = $item->product?->konversiDisplay($item->qty); @endphp
        @if($k && $k !== '-')
            <span class="text-muted">({{ $k }})</span>
        @endif
    </small>
</li>
```

- [ ] **Step 2: Update request-orders/index Items column**

Replace the `<li>` inside the items foreach (~line 57):
```blade
<li>
    {{ $item->product->name ?? 'Produk' }}: {{ $item->qty_requested }} pcs
    @if (!empty($item->notes))
        <small>({{ $item->notes }})</small>
    @endif
</li>
```
With:
```blade
<li>
    {{ $item->product->name ?? 'Produk' }}: {{ $item->qty_requested }}
    @php $k = $item->product?->konversiDisplay($item->qty_requested); @endphp
    @if($k && $k !== '-')
        <span class="text-muted">({{ $k }})</span>
    @endif
    @if (!empty($item->notes))
        <small class="text-muted">– {{ $item->notes }}</small>
    @endif
</li>
```

- [ ] **Step 3: Verify visually**

Navigate to `/pembelian` and `/request-orders`. Items with konversi should show `Produk A × 24 (2 Lusin)`.

- [ ] **Step 4: Commit**
```bash
git add resources/views/pembelians/index.blade.php resources/views/request-orders/index.blade.php
git commit -m "feat: show konversi in items columns on pembelian and request-order index"
```

---

## Task 3: picking-lists/index.blade.php — Restructure Items column + controller eager load

**Files:**
- Modify: `app/Http/Controllers/PickingListController.php:16`
- Modify: `resources/views/picking-lists/index.blade.php:41`

- [ ] **Step 1: Add items.product to index eager load in PickingListController**

Read `app/Http/Controllers/PickingListController.php` line ~16 and find the index method. The current with() call is:
```php
$pickingLists = PickingList::with(['requestOrder.owner', 'picker'])
```
Change it to:
```php
$pickingLists = PickingList::with(['requestOrder.owner', 'picker', 'items.product'])
```

- [ ] **Step 2: Replace "Total Items" cell in picking-lists/index.blade.php**

Find line ~41: `<td>{{ $value->items->count() }} items</td>`

Replace with:
```blade
<td>
    <ul class="list-unstyled" style="margin:0">
        @foreach ($value->items as $item)
            <li>
                <small>
                    {{ $item->product?->name }} × {{ $item->qty_to_pick }}
                    @php $k = $item->product?->konversiDisplay($item->qty_to_pick); @endphp
                    @if($k && $k !== '-')
                        <span class="text-muted">({{ $k }})</span>
                    @endif
                </small>
            </li>
        @endforeach
    </ul>
</td>
```

- [ ] **Step 3: Update the column header from "Total Items" to "Items"**

Find the `<td>Total Items</td>` header and change it to `<td>Items</td>`.

- [ ] **Step 4: Verify visually**

Navigate to `/picking-lists`. The Items column should now show a bulleted list like:
```
Produk A × 12 (1 Lusin)
Produk B × 5
```

- [ ] **Step 5: Commit**
```bash
git add app/Http/Controllers/PickingListController.php resources/views/picking-lists/index.blade.php
git commit -m "feat: show item detail list with konversi on picking-list index"
```

---

## Task 4: picking-lists/pick.blade.php + delivery-orders/_send-modal.blade.php

**Files:**
- Modify: `resources/views/picking-lists/pick.blade.php:66`
- Modify: `resources/views/delivery-orders/_send-modal.blade.php:24-28`

- [ ] **Step 1: Update picking-lists/pick.blade.php Qty to Pick column**

Find line ~66: `<td>{{ $item->qty_to_pick }}</td>`

Replace with:
```blade
<td>
    {{ $item->qty_to_pick }}
    @php $k = $item->product->konversiDisplay($item->qty_to_pick); @endphp
    @if($k !== '-')
        <small class="text-muted">({{ $k }})</small>
    @endif
</td>
```

- [ ] **Step 2: Update delivery-orders/_send-modal.blade.php Qty Pick column**

Find line ~27: `<td class="text-center">{{ $item->qty }}</td>`

Replace with:
```blade
<td class="text-center">
    {{ $item->qty }}
    @php $k = $item->product->konversiDisplay($item->qty); @endphp
    @if($k !== '-')
        <br><small class="text-muted">({{ $k }})</small>
    @endif
</td>
```

- [ ] **Step 3: Verify visually**

Navigate to `/picking-lists/{id}/pick` for an in-progress list and open the send modal on a delivery order.

- [ ] **Step 4: Commit**
```bash
git add resources/views/picking-lists/pick.blade.php resources/views/delivery-orders/_send-modal.blade.php
git commit -m "feat: show konversi on picking pick qty and DO send modal qty"
```

---

## Task 5: StockController + stocks/kartu.blade.php — JS-rendered konversi

**Files:**
- Modify: `app/Http/Controllers/StockController.php:170-179` (getKartuData response)
- Modify: `resources/views/stocks/kartu.blade.php` (JS section)

- [ ] **Step 1: Add konversi fields to getKartuData JSON response**

In `app/Http/Controllers/StockController.php`, find the return statement at ~line 170:
```php
return response()->json([
    'stock' => [
        'id' => $stock->id,
        'sku' => $stock->sku,
        'product_name' => $stock->product->name,
        'product_code' => $stock->product->code,
        'supplier' => $stock->pembelian->supplier->name ?? '-',
    ],
    'transactions' => $result
]);
```
Change to:
```php
return response()->json([
    'stock' => [
        'id'           => $stock->id,
        'sku'          => $stock->sku,
        'product_name' => $stock->product->name,
        'product_code' => $stock->product->code,
        'supplier'     => $stock->pembelian->supplier->name ?? '-',
        'konversi_qty' => $stock->product->konversi_qty,
        'satuan_besar' => $stock->product->satuan_besar,
        'satuan'       => $stock->product->satuan,
    ],
    'transactions' => $result
]);
```

- [ ] **Step 2: Add JS helper and update renderKartuTable in kartu.blade.php**

In `resources/views/stocks/kartu.blade.php`, inside the `<script>` block, add the helper function before `renderKartuTable`:
```javascript
function konversiDisplay(qty, konversiQty, satuanBesar, satuan) {
    satuan = satuan || 'PCS';
    qty = parseInt(qty) || 0;
    if (!konversiQty || !satuanBesar) return null;
    var boxes = Math.floor(qty / konversiQty);
    var rem = qty % konversiQty;
    if (rem === 0) return boxes + ' ' + satuanBesar;
    if (boxes > 0) return boxes + ' ' + satuanBesar + ' ' + rem + ' ' + satuan;
    return '1 ' + satuanBesar;
}
```

Then update `renderKartuTable` to accept and use stock konversi data. Change the function call inside the success callback:
```javascript
// Find where currentData is set and stock info updated, add:
var stockMeta = response.stock; // has konversi_qty, satuan_besar, satuan

// ...

renderKartuTable(response.transactions, stockMeta);
```

Update the `renderKartuTable` function signature and qty cells:
```javascript
function renderKartuTable(transactions, stockMeta) {
    // ... existing empty check ...

    transactions.forEach((item, index) => {
        latestNilai = item.nilai;

        function fmtQty(qty) {
            var k = konversiDisplay(qty, stockMeta.konversi_qty, stockMeta.satuan_besar, stockMeta.satuan);
            return qty + (k ? ' <small class="text-muted">(' + k + ')</small>' : '');
        }

        tbody.append(`
            <tr>
                <td>${index + 1}</td>
                <td>${item.tanggal}</td>
                <td class="text-right">${fmtQty(item.stok_awal)}</td>
                <td class="text-right">${fmtQty(item.masuk)}</td>
                <td class="text-right">${fmtQty(item.keluar)}</td>
                <td class="text-right"><strong>${fmtQty(item.stok_akhir)}</strong></td>
                <td class="text-right">${formatRupiah(item.harga)}</td>
                <td class="text-right"><strong>${formatRupiah(item.nilai)}</strong></td>
                <td><small>${item.keterangan}</small></td>
            </tr>
        `);
    });
    // ...
}
```

- [ ] **Step 3: Verify**

Open `/stock-kartu`, select a SKU for a product with konversi configured, click Tampilkan Kartu. Qty cells should show e.g. `24 (2 Lusin)`.

- [ ] **Step 4: Commit**
```bash
git add app/Http/Controllers/StockController.php resources/views/stocks/kartu.blade.php
git commit -m "feat: include konversi in kartu stok API response and JS rendering"
```

---

## Task 6: PembelianController + pembelians/create.blade.php — Cek Barang modal konversi

**Files:**
- Modify: `app/Http/Controllers/PembelianController.php:57` (getAllProducts select)
- Modify: `resources/views/pembelians/create.blade.php` (Cek Barang modal JS section)

- [ ] **Step 1: Add konversi fields to getAllProducts select**

In `app/Http/Controllers/PembelianController.php`, find the select at ~line 57:
```php
$products = Product::select('id', 'code', 'name', 'is_serialized', 'harga_beli', 'min_stock')
```
Change to:
```php
$products = Product::select('id', 'code', 'name', 'is_serialized', 'harga_beli', 'min_stock', 'konversi_qty', 'satuan_besar', 'satuan')
```

- [ ] **Step 2: Add JS konversiDisplay helper to pembelians/create.blade.php**

Inside the `<script>` section of `resources/views/pembelians/create.blade.php`, add the helper near the top (after `let currentProducts = null;`):
```javascript
function konversiDisplay(qty, konversiQty, satuanBesar, satuan) {
    satuan = satuan || 'PCS';
    qty = parseInt(qty) || 0;
    if (!konversiQty || !satuanBesar) return null;
    var boxes = Math.floor(qty / konversiQty);
    var rem = qty % konversiQty;
    if (rem === 0) return boxes + ' ' + satuanBesar;
    if (boxes > 0) return boxes + ' ' + satuanBesar + ' ' + rem + ' ' + satuan;
    return '1 ' + satuanBesar;
}
function fmtQtyK(qty, p) {
    if (!p) return qty;
    var k = konversiDisplay(qty, p.konversi_qty, p.satuan_besar, p.satuan);
    return qty + (k ? ' (' + k + ')' : '');
}
```

- [ ] **Step 3: Update the Cek Barang modal table rendering**

Find the section in `$('#modalCekBarang').on('show.bs.modal', ...)` where `$('<td>').addClass('text-center').text(p.stock_count || 0)` and `$('<td>').addClass('text-center').text(p.effective_min || p.min_stock || 0)` are created (~line 370).

Change:
```javascript
$('<td>').addClass('text-center').text(p.stock_count || 0),
$('<td>').addClass('text-center').text(p.effective_min || p.min_stock || 0),
```
To:
```javascript
$('<td>').addClass('text-center').html(fmtQtyK(p.stock_count || 0, p)),
$('<td>').addClass('text-center').html(fmtQtyK(p.effective_min || p.min_stock || 0, p)),
```

- [ ] **Step 4: Verify**

Open `/pembelian/create`, click "Cek Barang". The "Stok Saat Ini" and "Min Stok" columns should show konversi for products that have it configured.

- [ ] **Step 5: Commit**
```bash
git add app/Http/Controllers/PembelianController.php resources/views/pembelians/create.blade.php
git commit -m "feat: show konversi in cek barang modal stock counts"
```

---

## Task 7: request-orders/create.blade.php — Available Qty JS update

**Files:**
- Modify: `resources/views/request-orders/create.blade.php` (JS section only)

Note: `$products` is passed as `@json($products)` from the controller using a full Product collection. `konversi_qty`, `satuan_besar`, and `satuan` are already included as model attributes — no controller change needed.

- [ ] **Step 1: Add JS helper to request-orders/create.blade.php**

Inside the `<script>` block, add near the top after `let products = @json($products);`:
```javascript
function konversiDisplay(qty, konversiQty, satuanBesar, satuan) {
    satuan = satuan || 'PCS';
    qty = parseInt(qty) || 0;
    if (!konversiQty || !satuanBesar) return null;
    var boxes = Math.floor(qty / konversiQty);
    var rem = qty % konversiQty;
    if (rem === 0) return boxes + ' ' + satuanBesar;
    if (boxes > 0) return boxes + ' ' + satuanBesar + ' ' + rem + ' ' + satuan;
    return '1 ' + satuanBesar;
}
```

- [ ] **Step 2: Update the product-select change handler**

Find `$(document).on('change', '.product-select', function() {` (~line 182).

The current code sets available qty text as:
```javascript
$row.find('.available-qty').text(available);
```
Change to:
```javascript
var product = products.find(function(p) { return p.id == $(this).val(); }.bind(this));
var k = product ? konversiDisplay(available, product.konversi_qty, product.satuan_besar, product.satuan) : null;
$row.find('.available-qty').text(available + (k ? ' (' + k + ')' : ''));
```

Also update `populateProductSelect` to show konversi in the option text and in the added modal rows when products are added from the modal. Find in `btnTambahkanProduk` click handler where `available` is written to `.available-qty` TD:
```javascript
+ '<td class="available-qty">' + p.available + '</td>'
```
Change to:
```javascript
+ '<td class="available-qty">' + (function() {
    var prod = products.find(function(pr) { return pr.id == p.id; });
    var k = prod ? konversiDisplay(p.available, prod.konversi_qty, prod.satuan_besar, prod.satuan) : null;
    return p.available + (k ? ' (' + k + ')' : '');
})() + '</td>'
```

- [ ] **Step 3: Verify**

Open `/request-orders/create`, select a product — the "Available Qty" cell should show `24 (2 Lusin)` for configured products.

- [ ] **Step 4: Commit**
```bash
git add resources/views/request-orders/create.blade.php
git commit -m "feat: show konversi in request-order create available qty column"
```

---

## Task 8: LaporanController — Add konversi to PDF data arrays

**Files:**
- Modify: `app/Http/Controllers/LaporanController.php` (pdfPR ~line 310, pdfPengiriman ~line 489, pdfPicking ~line 523)

- [ ] **Step 1: Add konversi to pdfPR rows**

In `pdfPR()` at ~line 310, find the rows array item and add the konversi key:
```php
$rows[] = [
    // ... existing keys ...
    'qty' => $item->qty_requested,
    'satuan' => $item->product?->satuan ?? 'PCS',
    'konversi' => $item->product?->konversiDisplay($item->qty_requested) ?? '-',
    // ... rest of existing keys ...
];
```

- [ ] **Step 2: Add konversi to pdfPengiriman rows**

In `pdfPengiriman()` at ~line 489, find:
```php
'qty_kirim' => $item->qty,
'satuan' => $item->product?->satuan ?? 'PCS',
```
Add after satuan:
```php
'konversi_kirim' => $item->product?->konversiDisplay($item->qty) ?? '-',
```

- [ ] **Step 3: Add konversi to pdfPicking rows**

In `pdfPicking()` at ~line 523, find:
```php
'qty_order' => $item->qty_to_pick,
'qty_pick' => $item->qty_picked,
'qty_pack' => $item->qty_picked,
```
Add after these:
```php
'konversi_order' => $item->product?->konversiDisplay($item->qty_to_pick) ?? '-',
'konversi_pick'  => $item->product?->konversiDisplay($item->qty_picked) ?? '-',
```

- [ ] **Step 4: Commit (controller only — blade views in next task)**
```bash
git add app/Http/Controllers/LaporanController.php
git commit -m "feat: add konversi strings to PDF data arrays in LaporanController"
```

---

## Task 9: PDF blade views — Add konversi display

**Files:**
- Modify: `resources/views/exports/pdf/laporan-pr.blade.php`
- Modify: `resources/views/exports/pdf/laporan-picking.blade.php`
- Modify: `resources/views/exports/pdf/laporan-pengiriman.blade.php`
- Modify: `resources/views/exports/pdf/laporan-opname.blade.php`
- Modify: `resources/views/exports/pdf/kartu-stok.blade.php`

Read each file before editing to locate the exact line.

### laporan-pr.blade.php

- [ ] **Step 1: Read the file to find the qty cell**
```bash
grep -n "qty\|konversi" resources/views/exports/pdf/laporan-pr.blade.php
```

- [ ] **Step 2: Update qty display (row ~line 38)**

Find `<td class="tc">{{ $row['qty'] }}</td>` and change to:
```blade
<td class="tc">
    {{ $row['qty'] }}
    @if(isset($row['konversi']) && $row['konversi'] !== '-')
        <br><small>({{ $row['konversi'] }})</small>
    @endif
</td>
```

### laporan-pengiriman.blade.php

- [ ] **Step 3: Read the file to find the qty_kirim cell**
```bash
grep -n "qty_kirim\|konversi" resources/views/exports/pdf/laporan-pengiriman.blade.php
```

- [ ] **Step 4: Update qty_kirim display (~line 41)**

Find `<td class="tc">{{ $row['qty_kirim'] }}</td>` and change to:
```blade
<td class="tc">
    {{ $row['qty_kirim'] }}
    @if(isset($row['konversi_kirim']) && $row['konversi_kirim'] !== '-')
        <br><small>({{ $row['konversi_kirim'] }})</small>
    @endif
</td>
```

### laporan-picking.blade.php

- [ ] **Step 5: Read the file to find the qty columns**
```bash
grep -n "qty_order\|qty_pick\|konversi" resources/views/exports/pdf/laporan-picking.blade.php
```

- [ ] **Step 6: Update qty_order, qty_pick, qty_pack cells (~lines 44-46)**

Find the 3 qty cells and update each:
```blade
<td class="tc">
    {{ $row['qty_order'] }}
    @if(isset($row['konversi_order']) && $row['konversi_order'] !== '-')
        <br><small>({{ $row['konversi_order'] }})</small>
    @endif
</td>
<td class="tc">
    {{ $row['qty_pick'] }}
    @if(isset($row['konversi_pick']) && $row['konversi_pick'] !== '-')
        <br><small>({{ $row['konversi_pick'] }})</small>
    @endif
</td>
<td class="tc">
    {{ $row['qty_pack'] }}
    @if(isset($row['konversi_pick']) && $row['konversi_pick'] !== '-')
        <br><small>({{ $row['konversi_pick'] }})</small>
    @endif
</td>
```

### laporan-opname.blade.php

- [ ] **Step 7: Read the file to find the qty cells**
```bash
grep -n "system_qty\|physical_qty\|qty_adjustment\|konversi" resources/views/exports/pdf/laporan-opname.blade.php
```

- [ ] **Step 8: Update system_qty, physical_qty, qty_adjustment cells (~lines 45-48)**

`$adjustments` is a collection of StockAdjustment models with product eager-loaded. Update each qty cell using the model:
```blade
<td class="tc">
    {{ $adj->system_qty }}
    @php $k = $adj->product?->konversiDisplay($adj->system_qty); @endphp
    @if($k && $k !== '-') <br><small>({{ $k }})</small> @endif
</td>
<td class="tc">
    {{ $adj->physical_qty }}
    @php $k = $adj->product?->konversiDisplay($adj->physical_qty); @endphp
    @if($k && $k !== '-') <br><small>({{ $k }})</small> @endif
</td>
{{-- qty_adjustment can be negative — show abs value with sign --}}
<td class="tc">
    {{ $adj->qty_adjustment }}
    @php $k = $adj->product?->konversiDisplay(abs($adj->qty_adjustment)); @endphp
    @if($k && $k !== '-') <br><small>({{ $k }})</small> @endif
</td>
```

### kartu-stok.blade.php

- [ ] **Step 9: Read the file to find the qty cells**
```bash
grep -n "stok_awal\|masuk\|keluar\|stok_akhir\|konversi" resources/views/exports/pdf/kartu-stok.blade.php
```

- [ ] **Step 10: Update all 4 qty cells**

`$stock` is the Stock model with `product` loaded. Use `$stock->product->konversiDisplay()` for all cells:
```blade
{{-- stok_awal --}}
<td class="tr">
    {{ $t['stok_awal'] }}
    @php $k = $stock->product->konversiDisplay($t['stok_awal']); @endphp
    @if($k !== '-') <br><small>({{ $k }})</small> @endif
</td>
{{-- masuk --}}
<td class="tr">
    {{ $t['masuk'] }}
    @php $k = $stock->product->konversiDisplay($t['masuk']); @endphp
    @if($k !== '-') <br><small>({{ $k }})</small> @endif
</td>
{{-- keluar --}}
<td class="tr">
    {{ $t['keluar'] }}
    @php $k = $stock->product->konversiDisplay($t['keluar']); @endphp
    @if($k !== '-') <br><small>({{ $k }})</small> @endif
</td>
{{-- stok_akhir --}}
<td class="tr">
    <strong>{{ $t['stok_akhir'] }}</strong>
    @php $k = $stock->product->konversiDisplay($t['stok_akhir']); @endphp
    @if($k !== '-') <br><small>({{ $k }})</small> @endif
</td>
```

- [ ] **Step 11: Verify PDF exports**

From `/laporan`, open each modal, click Preview PDF for PR, Pengiriman, Picking, Opname. Download kartu stok PDF from `/stock-kartu`. All qty cells should show konversi where configured.

- [ ] **Step 12: Commit**
```bash
git add resources/views/exports/pdf/laporan-pr.blade.php \
        resources/views/exports/pdf/laporan-picking.blade.php \
        resources/views/exports/pdf/laporan-pengiriman.blade.php \
        resources/views/exports/pdf/laporan-opname.blade.php \
        resources/views/exports/pdf/kartu-stok.blade.php
git commit -m "feat: show konversi in all laporan PDF views"
```

---

## Task 10: Single Excel Exports — RequestOrder, PickingList, DeliveryOrder, KartuStok

**Files:**
- Modify: `app/Exports/RequestOrderSingleExport.php`
- Modify: `app/Exports/PickingListSingleExport.php`
- Modify: `app/Exports/DeliveryOrderSingleExport.php`
- Modify: `app/Exports/KartuStokExport.php`

Read each file in full before editing.

### RequestOrderSingleExport.php

- [ ] **Step 1: Read the file**

```bash
cat -n app/Exports/RequestOrderSingleExport.php
```

- [ ] **Step 2: Update map() to append konversi to qty**

Find the `map($item)` method. The qty row should be built as:
```php
public function map($item): array
{
    static $no = 0;
    $no++;
    $k = $item->product?->konversiDisplay($item->qty_requested);
    $qty = $item->qty_requested . ($k && $k !== '-' ? " ({$k})" : '');

    return [
        $no,
        $item->product?->code ?? '',
        $item->product?->name ?? '',
        $qty,
        $item->product?->satuan ?? 'PCS',
        // ... any other existing columns ...
    ];
}
```

### PickingListSingleExport.php

- [ ] **Step 3: Read and update PickingListSingleExport**

Find the map() method. For each qty cell (`qty_to_pick`, `qty_picked`):
```php
$k = $item->product?->konversiDisplay($item->qty_to_pick);
$qtyPick = $item->qty_to_pick . ($k && $k !== '-' ? " ({$k})" : '');

$k2 = $item->product?->konversiDisplay($item->qty_picked);
$qtyPicked = $item->qty_picked . ($k2 && $k2 !== '-' ? " ({$k2})" : '');
```
Use `$qtyPick` and `$qtyPicked` in the return array.

### DeliveryOrderSingleExport.php

- [ ] **Step 4: Read and update DeliveryOrderSingleExport**

Find the map() method. For the qty cell (`$item->qty`):
```php
$k = $item->product?->konversiDisplay($item->qty);
$qty = $item->qty . ($k && $k !== '-' ? " ({$k})" : '');
```
Use `$qty` in the return array.

### KartuStokExport.php

- [ ] **Step 5: Read and update KartuStokExport**

The kartu stok export works with the stock model and transactions. Find the map() or collection() method.

Each transaction has qty fields (masuk, keluar, total). The product is accessible via `$this->stock->product` (check if a `$stock` property exists on the class).

For each qty field, append konversi:
```php
$product = $this->stock->product; // or however the product is accessed
$kd = function ($qty) use ($product) {
    $k = $product?->konversiDisplay($qty);
    return $qty . ($k && $k !== '-' ? " ({$k})" : '');
};

// In map/row:
$kd($transaction['masuk']),
$kd($transaction['keluar']),
$kd($transaction['total']),
```

- [ ] **Step 6: Verify**

Export a request order, picking list, delivery order, and kartu stok as Excel. Check that qty cells contain konversi strings like `"24 (2 Lusin)"` for products with konversi.

- [ ] **Step 7: Commit**
```bash
git add app/Exports/RequestOrderSingleExport.php \
        app/Exports/PickingListSingleExport.php \
        app/Exports/DeliveryOrderSingleExport.php \
        app/Exports/KartuStokExport.php
git commit -m "feat: append konversi string to qty in single Excel exports"
```

---

## Task 11: Bulk Laporan Excel Exports

**Files:**
- Modify: `app/Exports/LaporanPOExport.php`
- Modify: `app/Exports/LaporanPRExport.php`
- Modify: `app/Exports/LaporanPickingPackingExport.php`
- Modify: `app/Exports/LaporanPengirimanExport.php`

Read each file before editing.

### LaporanPOExport.php

- [ ] **Step 1: Read the file**
```bash
cat -n app/Exports/LaporanPOExport.php
```

- [ ] **Step 2: Update qty and qty_diterima cells**

In the `collection()` method, find where `$pp->qty` and `$pp->qty_received` are used in the row. Update:
```php
$k = $pp->product?->konversiDisplay($pp->qty);
$qty = $pp->qty . ($k && $k !== '-' ? " ({$k})" : '');

$k2 = $pp->product?->konversiDisplay($pp->qty_received ?? $pp->qty);
$qtyDiterima = ($pp->qty_received ?? $pp->qty) . ($k2 && $k2 !== '-' ? " ({$k2})" : '');
```

### LaporanPRExport.php

- [ ] **Step 3: Read and update LaporanPRExport**

Find where `$item->qty_requested` is used in the row:
```php
$k = $item->product?->konversiDisplay($item->qty_requested);
$qty = $item->qty_requested . ($k && $k !== '-' ? " ({$k})" : '');
```

### LaporanPickingPackingExport.php

- [ ] **Step 4: Read and update LaporanPickingPackingExport**

Find where `$item->qty_to_pick` and `$item->qty_picked` are used:
```php
$k = $item->product?->konversiDisplay($item->qty_to_pick);
$qtyOrder = $item->qty_to_pick . ($k && $k !== '-' ? " ({$k})" : '');

$k2 = $item->product?->konversiDisplay($item->qty_picked);
$qtyPick = $item->qty_picked . ($k2 && $k2 !== '-' ? " ({$k2})" : '');
```

### LaporanPengirimanExport.php

- [ ] **Step 5: Read and update LaporanPengirimanExport**

Find where `$item->qty` is used:
```php
$k = $item->product?->konversiDisplay($item->qty);
$qty = $item->qty . ($k && $k !== '-' ? " ({$k})" : '');
```

- [ ] **Step 6: Verify**

From `/laporan`, export Excel for PO, PR, Picking & Packing, and Pengiriman. Open the downloaded files and confirm qty cells show konversi strings.

- [ ] **Step 7: Commit**
```bash
git add app/Exports/LaporanPOExport.php \
        app/Exports/LaporanPRExport.php \
        app/Exports/LaporanPickingPackingExport.php \
        app/Exports/LaporanPengirimanExport.php
git commit -m "feat: append konversi string to qty in bulk laporan Excel exports"
```

---

## Self-Review Checklist

**Spec coverage:**
- ✅ `/stock` index — ownerStock qty, qty_reserved, qty_available (Task 1)
- ✅ `/stock-kartu` — stok_awal, masuk, keluar, stok_akhir (Task 5)
- ✅ `/pembelian/create` — Cek Barang modal stock counts (Task 6)
- ✅ `/pembelian` index — Items column (Task 2)
- ✅ `/request-orders` index — Items column (Task 2)
- ✅ `/request-orders/create` — Available Qty (Task 7)
- ✅ `/request-orders/verify` — already done, skipped
- ✅ `/picking-lists` index — Items detail list (Task 3)
- ✅ `/picking-lists/pick` — Qty to Pick (Task 4)
- ✅ `/delivery-orders` send modal — Qty Pick (Task 4)
- ✅ All active Excel exports (Tasks 10–11)
- ✅ All active PDF exports (Tasks 8–9)

**Exports not in scope (user said "check index blades for unused"):**
- `LaporanBarangMasukExport`, `LaporanBarangKeluarExport`, `LaporanAktifitasExport`, `LaporanPenerimaanBarangExport`, `LaporanPergerakanExport` — not linked from any of the specified pages, not updated
- `ReturOutletExport`, `ReturSupplierExport` — not mentioned by user, not updated

**Edge cases:**
- `konversiDisplay('-')` never happens — the method returns `'-'` only when konversi data is missing. Guards `$k !== '-'` handle this throughout.
- `qty = 0` — konversiDisplay(0) will return `0 {satuanBesar}` (0 boxes). Consider whether to suppress konversi for 0 values. Current plan: show it, as it's consistent.
