# Picking List & Request Order TODO Fixes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement 4 TODOs across two blade views: per-row barcode validation, location-filtered export, a product search modal, and category select2.

**Architecture:** Backend changes first (controller + export), then view changes. Each task is self-contained.

**Tech Stack:** Laravel 9, Blade, Bootstrap 3 modals, DataTables, Select2, Maatwebsite Excel.

---

## File Map

| File | Change |
|------|--------|
| `app/Http/Controllers/PickingListController.php` | Add val_barcode check in `updateItem()` |
| `app/Http/Controllers/LaporanController.php` | Pass `?lokasi` to `PickingListSingleExport` |
| `app/Exports/PickingListSingleExport.php` | Accept `$lokasi` constructor param, filter collection |
| `app/Http/Controllers/RequestOrderController.php` | Add `Category::get()` to `create()` view data |
| `resources/views/picking-lists/pick.blade.php` | Location filter bar + per-row JS form + export link |
| `resources/views/request-orders/create.blade.php` | Product modal + category select2 |

---

### Task 1: Barcode validation in `updateItem()`

**Files:**
- Modify: `app/Http/Controllers/PickingListController.php:114-132`

- [ ] Add `val_barcode` check before saving. Replace `updateItem()` body:

```php
public function updateItem(Request $request, PickingListItem $item)
{
    $request->validate([
        'qty_picked' => 'required|integer|min:0|max:'.$item->qty_to_pick,
    ], [
        'qty_picked.required' => 'Jumlah yang diambil harus diisi.',
        'qty_picked.integer'  => 'Jumlah yang diambil harus berupa angka.',
        'qty_picked.min'      => 'Jumlah yang diambil minimal 0.',
        'qty_picked.max'      => 'Jumlah yang diambil tidak boleh melebihi :max.',
    ]);

    if ($request->filled('val_barcode') && $request->val_barcode !== $item->product->code) {
        return back()->with('toast_error', 'Barcode tidak cocok untuk produk '.$item->product->name.' (expected: '.$item->product->code.')');
    }

    $item->update([
        'qty_picked' => $request->qty_picked,
        'is_picked'  => $request->qty_picked == $item->qty_to_pick,
    ]);

    return redirect()->back()->with('toast_success', 'Item '.$item->product->name.' berhasil diperbarui.');
}
```

- [ ] Commit: `git commit -m "feat: add barcode validation to picking updateItem"`

---

### Task 2: Lokasi-filtered export

**Files:**
- Modify: `app/Http/Controllers/LaporanController.php:72-83`
- Modify: `app/Exports/PickingListSingleExport.php:27-36`

- [ ] Update `exportPickingList()` in `LaporanController` to pass lokasi:

```php
public function exportPickingList(Request $request, $id = null)
{
    $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

    if ($id) {
        $pickinglist = PickingList::with(['requestOrder', 'items.product'])->findOrFail($id);
        $lokasi = $request->query('lokasi');

        return Excel::download(
            new PickingListSingleExport($pickinglist, $settings, $lokasi),
            'Dokumen_Picking_list-'.$pickinglist->code.'.xlsx'
        );
    }

    return abort(404);
}
```

- [ ] Update `PickingListSingleExport` constructor and `collection()`:

```php
protected $lokasi;

public function __construct(PickingList $pickingList, array $settings = [], ?string $lokasi = null)
{
    $this->pickingList = $pickingList;
    $this->settings    = $settings;
    $this->lokasi      = $lokasi;
}

public function collection()
{
    $items = $this->pickingList->items;

    if ($this->lokasi) {
        $items = $items->filter(fn($item) => ($item->location ?? $item->product?->lokasi) === $this->lokasi);
    }

    return $items->values();
}
```

- [ ] Commit: `git commit -m "feat: add lokasi filter to picking list export"`

---

### Task 3: Pick blade — filter bar + export link + per-row validation JS

**Files:**
- Modify: `resources/views/picking-lists/pick.blade.php`

- [ ] Replace the entire file with the updated version (location filter bar above table, `data-lokasi` on rows, export link, per-row validation JS button that dynamically submits a form):

```blade
@extends('layouts.master')
@section('title', 'Picking Interface')
@section('container')
    <section class="content-header">
        <h1>Picking: {{ $pickingList->code }}</h1>
    </section>
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-warning">
                    <div class="box-header">
                        <h3 class="box-title">Scan & Pick Items</h3>
                    </div>
                    <div class="box-body">

                        {{-- Filter bar --}}
                        @php
                            $lokasiList = $pickingList->items
                                ->map(fn($i) => $i->location ?? $i->product?->lokasi)
                                ->unique()->filter()->sort()->values();
                        @endphp
                        <div class="row" style="margin-bottom:12px;">
                            <div class="col-sm-4">
                                <label>Filter Lokasi</label>
                                <select id="filter-lokasi" class="form-control">
                                    <option value="">Semua Lokasi</option>
                                    @foreach ($lokasiList as $lok)
                                        <option value="{{ $lok }}">{{ $lok }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-2" style="padding-top:25px;">
                                <a id="btn-export"
                                   href="{{ route('laporan.pickinglist', $pickingList->id) }}"
                                   class="btn btn-default">
                                    <i class="fa fa-file-excel-o"></i> Export
                                </a>
                            </div>
                        </div>

                        <form action="{{ route('picking-lists.bulk-update', $pickingList->id) }}" method="POST">
                            @csrf
                            <div class="table-responsive text-nowrap">
                                <table class="table table-bordered table-striped" id="pick-table">
                                    <thead>
                                        <tr>
                                            <th>NO</th>
                                            <th>Barcode</th>
                                            <th>Product</th>
                                            <th>Location</th>
                                            <th>SKU</th>
                                            <th>Qty to Pick</th>
                                            <th>Qty Picked</th>
                                            <th>Val Barcode</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($pickingList->items as $item)
                                            @php $lokasi = $item->location ?? $item->product?->lokasi; @endphp
                                            <tr data-lokasi="{{ $lokasi }}">
                                                <td>{{ $loop->iteration }}</td>
                                                <td>{{ $item->product->code }}</td>
                                                <td>{{ $item->product->name }}</td>
                                                <td>{{ $lokasi }}</td>
                                                <td>{{ $item->sku ?? '-' }}</td>
                                                <td>{{ $item->qty_to_pick }}</td>
                                                <td>
                                                    <input type="number" name="items[{{ $item->id }}][qty_picked]"
                                                        class="form-control qty-input" value="{{ $item->qty_picked }}" min="0"
                                                        max="{{ $item->qty_to_pick }}" style="width: 80px;">
                                                </td>
                                                <td>
                                                    <div style="display:flex;gap:4px;align-items:center;">
                                                        <input type="text" class="form-control val-barcode-input" placeholder="Scan barcode" style="width: 120px;">
                                                        <button type="button"
                                                            class="btn btn-warning btn-validasi"
                                                            data-item-id="{{ $item->id }}"
                                                            data-update-url="{{ route('picking-list-items.update', $item->id) }}">
                                                            Validasi
                                                        </button>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if ($item->is_picked)
                                                        <span class="label label-success">✓ PICKED</span>
                                                    @else
                                                        <span class="label label-warning">PENDING</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                        <tr>
                                            <td colspan="8"></td>
                                            <td>
                                                <button type="submit" class="btn btn-primary">Bulk Update</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                    <div class="box-footer text-center">
                        <form action="{{ route('picking-lists.complete', $pickingList->id) }}" method="post">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-check"></i> Complete Picking
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('page-script')
<script>
    // Location filter
    $('#filter-lokasi').on('change', function () {
        var selected = $(this).val();
        $('#pick-table tbody tr[data-lokasi]').each(function () {
            if (!selected || $(this).data('lokasi') == selected) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });

        var exportUrl = '{{ route('laporan.pickinglist', $pickingList->id) }}';
        if (selected) {
            exportUrl += '?lokasi=' + encodeURIComponent(selected);
        }
        $('#btn-export').attr('href', exportUrl);
    });

    // Per-row Validasi button — builds and submits a temporary form
    $(document).on('click', '.btn-validasi', function () {
        var $btn      = $(this);
        var $row      = $btn.closest('tr');
        var itemId    = $btn.data('item-id');
        var updateUrl = $btn.data('update-url');
        var qtyPicked = $row.find('.qty-input').val();
        var valBarcode = $row.find('.val-barcode-input').val();

        var $form = $('<form>', { method: 'POST', action: updateUrl }).hide();
        $form.append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }));
        $form.append($('<input>', { type: 'hidden', name: '_method', value: 'PATCH' }));
        $form.append($('<input>', { type: 'hidden', name: 'qty_picked', value: qtyPicked }));
        $form.append($('<input>', { type: 'hidden', name: 'val_barcode', value: valBarcode }));
        $('body').append($form);
        $form.submit();
    });
</script>
@endsection
```

- [ ] Commit: `git commit -m "feat: pick blade — location filter, filtered export link, per-row barcode validation"`

---

### Task 4: Categories in RequestOrderController + select2 in Sample rows

**Files:**
- Modify: `app/Http/Controllers/RequestOrderController.php:26-43`
- Modify: `resources/views/request-orders/create.blade.php:186-200`

- [ ] Add `Category` import and `categories` to `create()` method:

```php
use App\Models\Category;

public function create()
{
    return view('request-orders.create', [
        'outlets'    => Outlet::get(),
        'categories' => Category::orderBy('name')->get(),
        'products'   => Product::with(['stocks' => function ($q) {
            $q->where('qty_available', '>', 0)->where('status', 'available');
        }])->whereHas('stocks', function ($q) {
            $q->where('qty_available', '>', 0)->where('status', 'available');
        })->get()->map(function ($product) {
            $product->total_available = (int) $product->stocks->sum('qty_available');
            return $product;
        }),
    ]);
}
```

- [ ] In the blade, add `let categories = @json($categories);` after the `let products` line, and replace `addNoteRow()` function:

```js
let categories = @json($categories);

function addNoteRow() {
    var options = categories.map(function(c) {
        return '<option value="' + c.name + '">' + c.name + '</option>';
    }).join('');

    const row = `
        <tr class="note-row">
            <td>
                <select name="extra_notes[${noteIndex}][kategori]" class="form-control kategori-select" required style="width:100%;">
                    <option value="">Pilih Kategori</option>
                    ${options}
                </select>
            </td>
            <td><input type="number" name="extra_notes[${noteIndex}][qty]" class="form-control" min="0" value="0" required></td>
            <td><input type="text" name="extra_notes[${noteIndex}][nama_pj]" class="form-control" placeholder="Nama PJ"></td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm remove-note-row"><i class="fa fa-trash"></i></button>
            </td>
        </tr>`;
    $('#notes-tbody').append(row);
    $('#notes-tbody tr:last .kategori-select').select2({ width: '100%' });
    noteIndex++;
}
```

- [ ] Commit: `git commit -m "feat: categories select2 for sample rows in request-order create"`

---

### Task 5: Product search modal in request-orders/create.blade.php

**Files:**
- Modify: `resources/views/request-orders/create.blade.php`

- [ ] Add the "Cek Produk" button next to "Add Product" (line 80):

```html
<button type="button" class="btn btn-success" id="add-row"><i class="fa fa-plus"></i> Add Product</button>
<button type="button" class="btn btn-warning" data-toggle="modal" data-target="#modalCekProduk">
    <i class="fa fa-search"></i> Cek Produk
</button>
```

- [ ] Add the modal HTML before `@endsection` (before the closing `</section>`):

```html
{{-- Modal Cek Produk --}}
<div class="modal fade" id="modalCekProduk" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-search"></i> Pilih Produk</h4>
            </div>
            <div class="modal-body">
                <table id="tableCekProduk" class="table table-bordered table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="checkAllProduk"></th>
                            <th>Kode</th>
                            <th>Nama Produk</th>
                            <th>Tersedia</th>
                        </tr>
                    </thead>
                    <tbody id="cekProdukBody"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" id="btnTambahkanProduk">
                    <i class="fa fa-check"></i> Tambahkan ke Request
                </button>
            </div>
        </div>
    </div>
</div>
```

- [ ] Add modal JS in the `page-script` section after the existing `$(document).on('click', '.remove-row' ...` block:

```js
// ---- Modal Cek Produk ----
var cekProdukTable = null;

$('#modalCekProduk').on('show.bs.modal', function () {
    if (cekProdukTable) {
        cekProdukTable.destroy();
        $('#cekProdukBody').empty();
    }

    var rows = products.map(function (p) {
        var available = p.total_available || 0;
        return '<tr data-product-id="' + p.id + '" data-available="' + available + '">'
            + '<td class="text-center"><input type="checkbox" class="chk-produk"></td>'
            + '<td>' + p.code + '</td>'
            + '<td>' + p.name + '</td>'
            + '<td>' + available + '</td>'
            + '</tr>';
    });
    $('#cekProdukBody').html(rows.join(''));

    cekProdukTable = $('#tableCekProduk').DataTable({
        order: [[2, 'asc']],
        columnDefs: [{ orderable: false, targets: 0 }],
        pageLength: 10,
        language: { search: 'Cari:' }
    });
});

$('#checkAllProduk').on('change', function () {
    $('.chk-produk').prop('checked', this.checked);
});

$('#btnTambahkanProduk').on('click', function () {
    var checked = [];
    $('#tableCekProduk tbody .chk-produk:checked').each(function () {
        var $row = $(this).closest('tr');
        checked.push({
            id: $row.data('product-id'),
            available: $row.data('available')
        });
    });

    if (checked.length === 0) {
        alert('Pilih minimal satu produk.');
        return;
    }

    checked.forEach(function (p) {
        // Build new row HTML
        var newRow = '<tr class="item-row">'
            + '<td><select name="items[' + rowIndex + '][product_id]" class="form-control product-select" required style="width:100%;"></select></td>'
            + '<td class="available-qty">' + p.available + '</td>'
            + '<td><input type="number" name="items[' + rowIndex + '][qty_requested]" class="form-control" min="1" max="' + p.available + '" required></td>'
            + '<td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button></td>'
            + '</tr>';
        $('#items-table tbody').append(newRow);

        var $newSelect = $('#items-table tbody tr:last .product-select');
        populateProductSelect($newSelect, p.id);
        $newSelect.select2({ width: '100%' });
        rowIndex++;
    });

    $('#modalCekProduk').modal('hide');
    $('#checkAllProduk').prop('checked', false);
});
```

- [ ] Commit: `git commit -m "feat: product search modal with multi-select in request-order create"`
