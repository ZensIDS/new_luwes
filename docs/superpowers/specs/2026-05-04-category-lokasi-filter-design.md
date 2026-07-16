# Category & Lokasi Filter — Products & Stocks Index

**Date:** 2026-05-04  
**Scope:** `resources/views/products/index.blade.php`, `resources/views/stocks/index.blade.php`, `app/Http/Controllers/StockController.php`

## Problem

The Products and Stocks index tables have no way to narrow rows by category or physical warehouse location (lokasi). Users must scroll through the full list to find items by group.

## Solution

Add two client-side dropdown filters — **Kategori** and **Lokasi** — above each table. Selections use the DataTables column-search API to instantly show/hide rows with no page reload. Dropdown options are derived from the table's own column data (no extra server queries).

## Approach

DataTables column search (`table.column(N).search(val).draw()`). Each page's `page-script` destroys the master layout's auto-initialized `#example1` DataTable and re-initialises it with a `columnDefs` config that hides the auxiliary columns.

## Changes

### `StockController::index()`

Add `product.category` to the `with()` eager load so the view can access `$stock->product->category->name`.

```php
Stock::with(['product.category', 'pembelian.supplier', 'ownerStock.owner'])
```

### `products/index.blade.php`

| What | Detail |
|------|--------|
| New hidden column | `lokasi` appended as column **15** in `<thead>` and each `<tbody>` row |
| Filter bar | Two `<select>` dropdowns in `box-header` — Kategori (column 3) + Lokasi (column 15) |
| DataTable init | Re-init `#example1` with `columnDefs: [{visible: false, targets: [15]}]` |
| Dropdown population | `table.column(N).data().unique().sort()` — runs after init |
| Filter binding | Each `<select>` change → `table.column(N).search(val).draw()` |

### `stocks/index.blade.php`

| What | Detail |
|------|--------|
| New hidden columns | `category_name` (col **13**) and `lokasi` (col **14**) appended to `<thead>` and each row |
| Filter bar | Same two dropdowns — Kategori (column 13) + Lokasi (column 14) |
| DataTable init | Re-init `#example1` with `columnDefs: [{visible: false, targets: [13, 14]}]` |
| Dropdown population | Same pattern as products |
| Filter binding | Same pattern as products |

## Column Index Reference

| Page | Category col idx | Lokasi col idx |
|------|-----------------|----------------|
| Products | 3 (visible) | 15 (hidden) |
| Stocks | 13 (hidden) | 14 (hidden) |

## Empty / null handling

- Rows where lokasi is null or empty render the cell as an empty string.
- The dropdown excludes blank entries so users don't see an empty option mid-list.

## Out of scope

- Server-side filtering
- Persisting filter selections across page reloads
- Adding lokasi as a visible column to the stocks table
