# Category & Lokasi Filter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add client-side Kategori and Lokasi filter dropdowns to the Products and Stocks index tables using DataTables column search.

**Architecture:** Both pages already load all data server-side into DataTables. Each page-script destroys the master layout's auto-initialized `#example1` DataTable and re-initializes it with a `columnDefs` config that hides auxiliary data columns. Dropdowns populate from column data and call `table.column(N).search().draw()` on change.

**Tech Stack:** Laravel 9, Blade, jQuery, DataTables 1.10.x (AdminLTE 2.x)

---

## Column Index Reference

| Page | No | Barcode/SKU | Code | Product/Nama | Kategori | ... | Aksi/Action | Lokasi (hidden) |
|------|----|-------------|------|--------------|----------|-----|-------------|-----------------|
| Products | 0 | 1 | — | 2 | **3** | 4–13 | 14 | **15** |
| Stocks | 0 | 1 | 2 | 3 | 4–12 | — | 12 | **13** (kategori), **14** (lokasi) |

---

### Task 1: Eager-load product.category in StockController

**Files:**
- Modify: `app/Http/Controllers/StockController.php:17-26`

- [ ] **Step 1: Update the with() call to include product.category**

In `StockController::index()`, change the eager load from `'product'` to `'product.category'`:

```php
public function index()
{
    return view('stocks.index', [
        'stocks' => Stock::with([
            'product.category',
            'pembelian.supplier',
            'ownerStock.owner',
        ])
            ->orderBy('product_id')
            ->orderBy('expired_at')
            ->get()
    ]);
}
```

- [ ] **Step 2: Verify no N+1 queries by checking the page loads without errors**

Run: `php artisan serve` then visit `/stocks` in a browser.
Expected: page loads, no "Trying to get property of non-object" errors.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/StockController.php
git commit -m "feat: eager-load product.category in StockController index"
```

---

### Task 2: Add category/lokasi filter to Products index

**Files:**
- Modify: `resources/views/products/index.blade.php`

- [ ] **Step 1: Add the filter dropdowns to the box-header**

After the last button group (the `<hr />` and min-stock buttons block) and before the closing `</div><!-- /.box-header -->`, add:

```blade
<div class="row" style="margin-top:10px;">
    <div class="col-xs-12" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <select id="filterKategori" class="form-control input-sm" style="width:auto; min-width:160px;">
            <option value="">Semua Kategori</option>
        </select>
        <select id="filterLokasi" class="form-control input-sm" style="width:auto; min-width:160px;">
            <option value="">Semua Lokasi</option>
        </select>
    </div>
</div>
```

- [ ] **Step 2: Add the hidden Lokasi column header to `<thead>`**

After the `<td>Aksi</td>` header cell (line ~124), add:

```blade
<td style="display:none;">Lokasi</td>
```

- [ ] **Step 3: Add the hidden lokasi data cell to each tbody row**

Inside the `@foreach ($products as $value)` loop, after the closing `</td>` of the Aksi column (the form+button block), add:

```blade
<td style="display:none;">{{ $value->lokasi ?? '' }}</td>
```

- [ ] **Step 4: Replace the page-script block with the updated version that re-inits DataTable and wires up filters**

Replace the entire `@section('page-script')` block with:

```blade
@section('page-script')
    <script>
        $(document).ready(function() {
            // Destroy master-layout's auto-init so we can add hidden column config
            if ($.fn.DataTable.isDataTable('#example1')) {
                $('#example1').DataTable().destroy();
            }
            var table = $('#example1').DataTable({
                columnDefs: [{ visible: false, targets: [15] }]
            });

            // Populate Kategori dropdown (column 3) from loaded data
            table.column(3).data().unique().sort().each(function(val) {
                if (val && String(val).trim() !== '') {
                    $('#filterKategori').append(
                        '<option value="' + val + '">' + val + '</option>'
                    );
                }
            });

            // Populate Lokasi dropdown (column 15) from loaded data
            table.column(15).data().unique().sort().each(function(val) {
                if (val && String(val).trim() !== '') {
                    $('#filterLokasi').append(
                        '<option value="' + val + '">' + val + '</option>'
                    );
                }
            });

            function escReg(val) {
                return val.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, '\\$&');
            }

            $('#filterKategori').on('change', function() {
                var val = $(this).val();
                table.column(3).search(val ? '^' + escReg(val) + '$' : '', true, false).draw();
            });

            $('#filterLokasi').on('change', function() {
                var val = $(this).val();
                table.column(15).search(val ? '^' + escReg(val) + '$' : '', true, false).draw();
            });

            // Price history modal (unchanged)
            $('#priceHistoryModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var modal = $(this);
                modal.find('#priceHistoryBody').html(
                    '<tr><td colspan="3" class="text-center">Loading...</td></tr>');

                $.ajax({
                    url: '/product/' + id + '/price-history',
                    method: 'GET',
                    success: function(res) {
                        var rows = '';
                        if (res.data && res.data.length) {
                            res.data.forEach(function(item) {
                                var change = item.event === 'created' ?
                                    'Created → ' + Number(item.new).toLocaleString() :
                                    Number(item.old).toLocaleString() + ' → ' + Number(
                                        item.new).toLocaleString();
                                rows += '<tr><td>' + item.date + '</td><td>' + item
                                    .user + '</td><td>' + change + '</td></tr>';
                            });
                        } else {
                            rows =
                                '<tr><td colspan="3" class="text-center">No changes found.</td></tr>';
                        }
                        modal.find('#priceHistoryBody').html(rows);
                    },
                    error: function() {
                        modal.find('#priceHistoryBody').html(
                            '<tr><td colspan="3" class="text-center text-danger">Error loading data.</td></tr>'
                            );
                    }
                });
            });
        });
    </script>
@endsection
```

- [ ] **Step 5: Verify in browser**

Visit `/products`.
- Both dropdowns appear above the table.
- Dropdowns are populated with unique category names and lokasi values from the loaded data.
- Selecting a category shows only rows for that category.
- Selecting a lokasi shows only rows for that location.
- Selecting "Semua Kategori" / "Semua Lokasi" restores all rows.
- Both filters can be applied simultaneously.
- The DataTables search box still works alongside the dropdowns.

- [ ] **Step 6: Commit**

```bash
git add resources/views/products/index.blade.php
git commit -m "feat: add client-side category & lokasi filter dropdowns to products index"
```

---

### Task 3: Add category/lokasi filter to Stocks index

**Files:**
- Modify: `resources/views/stocks/index.blade.php`

- [ ] **Step 1: Add a box-header with filter dropdowns above the table**

The stocks page currently has no `box-header`. Add one between the opening `<div class="box">` and `<div class="box-body table-responsive">`:

```blade
<div class="box-header">
    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <select id="filterKategori" class="form-control input-sm" style="width:auto; min-width:160px;">
            <option value="">Semua Kategori</option>
        </select>
        <select id="filterLokasi" class="form-control input-sm" style="width:auto; min-width:160px;">
            <option value="">Semua Lokasi</option>
        </select>
    </div>
</div>
```

- [ ] **Step 2: Add two hidden column headers to `<thead>`**

After the `<td>Action</td>` header cell, add:

```blade
<td style="display:none;">Kategori</td>
<td style="display:none;">Lokasi</td>
```

- [ ] **Step 3: Add two hidden data cells to each tbody row**

Inside the `@foreach ($stocks as $stock)` loop, after the closing `</td>` of the Action column, add:

```blade
<td style="display:none;">{{ $stock->product->category?->name ?? '' }}</td>
<td style="display:none;">{{ $stock->product->lokasi ?? '' }}</td>
```

- [ ] **Step 4: Update the page-script to re-init #example1 with hidden columns and wire up filters**

Replace the entire `@section('page-script')` block with:

```blade
@section('page-script')
    <script>
        $(document).ready(function() {
            // Destroy pre-initialised DataTables (from master) to avoid column mismatch
            if ($.fn.DataTable.isDataTable('#example1')) {
                $('#example1').DataTable().destroy();
            }
            if ($.fn.DataTable.isDataTable('#example2')) {
                $('#example2').DataTable().destroy();
            }
            if ($.fn.DataTable.isDataTable('#example3')) {
                $('#example3').DataTable().destroy();
            }

            var table = $('#example1').DataTable({
                columnDefs: [{ visible: false, targets: [13, 14] }]
            });

            // Populate Kategori dropdown (column 13)
            table.column(13).data().unique().sort().each(function(val) {
                if (val && String(val).trim() !== '') {
                    $('#filterKategori').append(
                        '<option value="' + val + '">' + val + '</option>'
                    );
                }
            });

            // Populate Lokasi dropdown (column 14)
            table.column(14).data().unique().sort().each(function(val) {
                if (val && String(val).trim() !== '') {
                    $('#filterLokasi').append(
                        '<option value="' + val + '">' + val + '</option>'
                    );
                }
            });

            function escReg(val) {
                return val.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, '\\$&');
            }

            $('#filterKategori').on('change', function() {
                var val = $(this).val();
                table.column(13).search(val ? '^' + escReg(val) + '$' : '', true, false).draw();
            });

            $('#filterLokasi').on('change', function() {
                var val = $(this).val();
                table.column(14).search(val ? '^' + escReg(val) + '$' : '', true, false).draw();
            });

            $('#priceHistoryModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');
                var modal = $(this);
                modal.find('#priceHistoryBody').html(
                    '<tr><td colspan="3" class="text-center">Loading...</td></tr>');

                $.ajax({
                    url: '/product/' + id + '/price-history',
                    method: 'GET',
                    success: function(res) {
                        var rows = '';
                        if (res.data && res.data.length) {
                            res.data.forEach(function(item) {
                                var change = item.event === 'created' ?
                                    'Created → ' + Number(item.new).toLocaleString() :
                                    Number(item.old).toLocaleString() + ' → ' + Number(
                                        item.new).toLocaleString();
                                rows += '<tr><td>' + item.date + '</td><td>' + item
                                    .user + '</td><td>' + change + '</td></tr>';
                            });
                        } else {
                            rows =
                                '<tr><td colspan="3" class="text-center">No changes found.</td></tr>';
                        }
                        modal.find('#priceHistoryBody').html(rows);
                    },
                    error: function() {
                        modal.find('#priceHistoryBody').html(
                            '<tr><td colspan="3" class="text-center text-danger">Error loading data.</td></tr>'
                        );
                    }
                });
            });

            $('#stockHistoryModal').on('show.bs.modal', function (event) {
                var button = $(event.relatedTarget);
                var id = button.data('id');

                $('#activityBody').html('<tr><td colspan="4" class="text-center">Loading...</td></tr>');
                $('#movementBody').html('<tr><td colspan="7" class="text-center">Loading...</td></tr>');

                if ($.fn.DataTable.isDataTable('#example2')) {
                    $('#example2').DataTable().destroy();
                }
                if ($.fn.DataTable.isDataTable('#example3')) {
                    $('#example3').DataTable().destroy();
                }

                $.get('/stock/' + id + '/history', function (res) {
                    var aRows = '';
                    if (res.activities.length) {
                        res.activities.forEach(function (item) {
                            var changes = '';
                            if (item.event === 'created') {
                                changes = 'Stock created';
                            } else {
                                var old = item.properties.old || {};
                                var attr = item.properties.attributes || {};
                                changes = Object.keys(attr).map(function (k) {
                                    return k + ': ' + (old[k] ?? '?') + ' → ' + attr[k];
                                }).join('<br>');
                            }
                            aRows += '<tr><td>' + item.date + '</td><td>' + item.user +
                                '</td><td>' + item.event + '</td><td>' + changes + '</td></tr>';
                        });
                    } else {
                        aRows = '<tr><td colspan="4" class="text-center">No activity found.</td></tr>';
                    }
                    $('#activityBody').html(aRows);

                    var mRows = '';
                    if (res.movements.length) {
                        res.movements.forEach(function (item) {
                            mRows += '<tr><td>' + item.date + '</td><td>' + item.user +
                                '</td><td>' + item.type + '</td><td>' + (item.qty_in ?? 0) +
                                '</td><td>' + (item.qty_out ?? 0) + '</td><td>' + (item.balance ?? 0) +
                                '</td><td>' + (item.notes ?? '-') + '</td></tr>';
                        });
                    } else {
                        mRows = '<tr><td colspan="7" class="text-center">No movements found.</td></tr>';
                    }
                    $('#movementBody').html(mRows);

                    $('#example2').DataTable();
                    $('#example3').DataTable();

                }).fail(function () {
                    $('#activityBody').html('<tr><td colspan="4" class="text-center text-danger">Error loading data.</td></tr>');
                    $('#movementBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading data.</td></tr>');
                });
            });
        });
    </script>
@endsection
```

- [ ] **Step 5: Verify in browser**

Visit `/stocks`.
- Both dropdowns appear above the table.
- Dropdowns are populated with unique values.
- Selecting a category or lokasi filters rows instantly.
- "History" button on a row still opens the stock history modal correctly.
- The price history modal still works.

- [ ] **Step 6: Commit**

```bash
git add resources/views/stocks/index.blade.php
git commit -m "feat: add client-side category & lokasi filter dropdowns to stocks index"
```

---

## Self-Review Checklist

- [x] **Spec coverage:** Controller eager-load (Task 1) ✓, Products filter (Task 2) ✓, Stocks filter (Task 3) ✓, empty/null handling (empty string fallback + blank-value exclusion in dropdown population) ✓
- [x] **Placeholder scan:** No TBDs. All code blocks are complete and runnable.
- [x] **Type consistency:** `table` variable, `escReg()` function, and column indices are consistent within each task. `filterKategori`/`filterLokasi` IDs used consistently between HTML and JS.
- [x] **Hidden column rendering:** `style="display:none;"` on `<td>`/`<th>` cells is only needed to prevent a flash before DataTables hides them via `columnDefs`. DataTables takes over visibility management after init.
