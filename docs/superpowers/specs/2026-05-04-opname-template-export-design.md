# Stock Opname — Template Export + Save Fix

**Date:** 2026-05-04
**Scope:** `app/Exports/StockOpnameTemplateExport.php` (new), `app/Http/Controllers/StockController.php`, `resources/views/stocks/opname.blade.php`, `app/Exports/StockOpnameExport.php`, `routes/web.php`

## Problems

**TODO 1 (save bug):** `saveOpname()` calculates `physical_qty` server-side as `$stock->qty + selisih`. Because `$stock->qty` is fetched at save time (not page-load time), any transactions that occurred between page load and save corrupt the stored value. Example: page loaded at qty=911, user counts 829 (selisih=-82), 0 transactions during save → physical_qty=829 ✓. But if transactions moved stock to 829 before save, physical_qty = 829+(-82) = 747 ✗.

**TODO 2 (missing export):** The Export Excel button on the opname page points to `laporan.stock-opname`, which queries historical `StockAdjustment` records. It does not show the current live stock, and has no "Stok Fisik" column for hand-writing counts. Users need a printable blank template showing current system stock.

## Solution

### Part 1 — New blank template export

**New route:** `GET /stock-opname/export-template` → `StockController::exportOpnameTemplate()`  
Accepts optional `?lokasi=` query param (matches the page's lokasi filter dropdown).

**New class:** `app/Exports/StockOpnameTemplateExport.php`

Data source: `Stock::with('product')->where('qty', '>', 0)->whereNotNull('sku')->orderBy('product_id')->orderBy('sku')`, filtered by `product.lokasi` when lokasi param is present. Same query as `getOpnameData()`.

Excel columns:
| # | Column | Source |
|---|--------|--------|
| 1 | No | row counter |
| 2 | Kode Barang | `stock->product->code` |
| 3 | Nama Barang | `stock->product->name` |
| 4 | Batch/SKU | `stock->sku` |
| 5 | Expired | `stock->expired_at` formatted `d/m/Y`, else `-` |
| 6 | Satuan | `stock->product->satuan` |
| 7 | Stok Sistem | `stock->qty` (with konversiDisplay annotation) |
| 8 | Stok Fisik | **blank** |
| 9 | Selisih | **blank** |
| 10 | Keterangan | **blank** |

Styling: matches `StockOpnameExport` — company header block (from `settings.json`), logo, thick top border, title "TEMPLATE STOCK OPNAME", document date, table header with blue fill, borders on all data rows, column widths identical to `StockOpnameExport`. No summary section (nothing to summarise on a blank template).

**opname.blade.php change:** Replace the `<form method="GET" action="{{ route('laporan.stock-opname') }}">` block with a plain anchor link:
```html
<a id="btnExportTemplate" href="{{ route('stock.opname.export-template') }}"
   class="btn btn-success">
    <i class="fa fa-file-excel-o"></i> Export Template
</a>
```
A small JS snippet updates the `href` whenever the lokasi dropdown changes:
```js
$('#filterLokasi').on('change', function() {
    var lok = $(this).val();
    var base = '{{ route('stock.opname.export-template') }}';
    $('#btnExportTemplate').attr('href', lok ? base + '?lokasi=' + encodeURIComponent(lok) : base);
});
```

### Part 2 — Fix physical_qty / system_qty on save

**Frontend (`opname.blade.php`):** Extend the items payload in `#btnSaveOpname` to include `system_qty` and `physical_qty`:
```js
items.push({
    stock_id:     stockId,
    selisih:      selisih,
    system_qty:   parseFloat(row.find('.stock_dikartu').val()) || 0,
    physical_qty: parseFloat(row.find('.stock_fisik').val()) || 0,
    keterangan:   keterangan,
});
```

**Backend (`saveOpname`):** Add `system_qty` and `physical_qty` to the validation rules (both `nullable|numeric`). The `??` fallback in the `StockAdjustment::create()` call is kept as a safety net but will no longer be triggered.

**`StockOpnameExport.php`:** Remove the TODO comment on line 65. No logic change needed — the stored `physical_qty` is now always accurate.

## Out of scope

- Replacing or modifying `laporan.stock-opname` (historical adjustment report)
- Adding `physical_qty`/`system_qty` to the `Tambah Baris` (manually-added rows) path — those rows don't have `stock_dikartu` filled until a stock is selected; the existing fallback handles them
- Excel formula columns (Selisih as `=H-G`) — template is for pen-and-paper use
