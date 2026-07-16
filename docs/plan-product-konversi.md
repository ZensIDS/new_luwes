# Plan: Product Unit Conversion (Konversi)

## Source TODO — `app/Models/Product.php`

```php
//TODO add Konversi (contoh pcs karton box dll)
//Product A satuan pcs konversi 15 pcs = 1 karton
//Product B satuan pcs konversi 25 pcs = 1 karton
//Product C satuan pcs konversi 35 pcs = 1 box
```

---

## Goal

Keep product creation simple while still supporting product unit conversion.

User still creates product as usual:

```php
name = "abc"
satuan = "pcs"
```

Then optionally sets:

```php
satuan_besar = "karton"
konversi_qty = 12
```

Meaning:

```text
1 karton = 12 pcs
```

The original `satuan` field on `Product` remains the base unit.

Konversi is display/reference only.

---

## Example

| Product | Satuan | Satuan Besar | Konversi |
|---|---|---|---|
| A | pcs | karton | 1 karton = 15 pcs |
| B | pcs | karton | 1 karton = 25 pcs |
| C | pcs | box | 1 box = 35 pcs |
| D | pcs | lusin | 1 lusin = 12 pcs |

---

## Why This Approach

Instead of repeater + multiple conversions:

- simpler for users
- no confusing repeater UI
- no extra table needed
- easier implementation
- easier export support
- safer null handling
- enough for most real business usage

Most users only need 1 larger unit.

If multi-conversion is needed later, it can be v2.

---

## Data Model (v1)

## Add new nullable columns to `products` table

```php
satuan_besar   nullable string
konversi_qty   nullable decimal(10,2)
```

Example:

```php
satuan = "pcs"
satuan_besar = "karton"
konversi_qty = 12
```

Meaning:

```text
1 karton = 12 pcs
```

---

## Product Model

## Add accessor

```php
public function getKonversiStringAttribute(): string
{
    if (!$this->satuan_besar || !$this->konversi_qty) {
        return '-';
    }

    return "1 {$this->satuan_besar} = {$this->konversi_qty} {$this->satuan}";
}
```

Exact display format:

```text
1 karton = 15 pcs
```

If no conversion exists:

```text
-
```

---

## Files to Change

---

## 1. Migration

Add nullable columns to `products` table:

```php
$table->string('satuan_besar')->nullable();
$table->decimal('konversi_qty', 10, 2)->nullable();
```

---

## 2. Product Model

Update:

```php
app/Models/Product.php
```

Add:

- `satuan_besar`
- `konversi_qty`
- `getKonversiStringAttribute()`

No separate `ProductConversion` model in v1.

---

## 3. ProductController

## store()

Save:

```php
$product->satuan_besar = $request->satuan_besar;
$product->konversi_qty = $request->konversi_qty;
```

---

## update()

Same as store.

---

## validation

Add:

```php
'satuan_besar' => 'nullable|string|max:255',
'konversi_qty' => 'nullable|numeric|min:0.01',
```

Rule:

If one is filled, the other should also be filled.

---

## 4. Create + Edit Product Blade

Add below `satuan` field:

```html
<div class="form-group">
    <label>Satuan Besar (Opsional)</label>
    <input type="text"
           name="satuan_besar"
           class="form-control"
           placeholder="karton / box / lusin">
</div>

<div class="form-group">
    <label>Isi Konversi</label>
    <input type="number"
           name="konversi_qty"
           class="form-control"
           placeholder="12"
           min="0.01"
           step="0.01">

    <small>Contoh: 1 karton = 12 pcs</small>
</div>
```

No repeater needed.

Simple UX.

---

## 5. Where "Konversi" Column Is Displayed (add, not replace)

The new column shows:

```php
$product->konversi_string
```

or calculated stock conversion when needed.

---

### Example Table Display

```blade
<td>{{ $product->stocks ?? 0 }} {{ $product->satuan }}</td>

<td>
    @if($product->satuan_besar && $product->konversi_qty && $product->stocks)
        {{ number_format($product->stocks / $product->konversi_qty, 2) }}
        {{ $product->satuan_besar }}
    @else
        -
    @endif
</td>
```

Must include null safety.

---

## Locations to Update

| Location | Change |
|---|---|
| `resources/views/products/index.blade.php` | Add **Konversi** column after Satuan |
| `resources/views/stocks/index.blade.php` | Same |
| `resources/views/pembelians/penerimaan.blade.php` | Add Konversi column near Satuan |
| `resources/views/pembelians/penerimaan-index.blade.php` | Same |
| Excel exports: `ProductsExport`, `StockExport`, `PembelianSingleExport`, `PenerimaanExport`, `StockOpnameExport` | Add **Konversi** heading + mapping |
| PDF exports: `laporan-stok`, `laporan-penerimaan`, `laporan-po`, `laporan-barang-masuk` | Add `<th>Konversi</th>` + `<td>` |

---

## Exact String Format

Use:

```text
1 karton = 15 pcs
```

If empty:

```text
-
```

For Excel:

- use newline (`\n`) if needed
- wrap text enabled

For Blade / PDF:

- standard text or `<br>` if formatting needed

---

## 6. ProductsExport (Import Template)

The import template should include:

- **Konversi** note column
- or explanation sheet

But:

### conversions should NOT be importable in v1

Only show:

```php
$product->konversi_string
```

as read-only info.

No import parsing.

---

## Scope Boundaries (v1)

### Included

- display/reference only
- optional larger unit conversion
- export support
- null-safe display

### Not Included

- conversion-aware stock arithmetic
  example: receive 2 karton → auto convert to 24 pcs

- PO/DO/Picking conversion unit picker

- multiple conversions per product

- import support for conversions

- automatic stock mutation logic

Stock always remains in base `satuan`.

---

## Implementation Order

1. Migration (add columns to products)
2. Product model accessor
3. ProductController store/update validation
4. Create + Edit blades
5. `products/index.blade.php` Konversi column
6. Excel exports (headings + mapping)
7. PDF reports
8. Other views (stocks, penerimaan)

---

## Final Result Example

| Nama | Stock | Konversi |
|---|---|---|
| ABC | 120 pcs | 10 karton |

because:

```text
1 karton = 12 pcs
120 pcs / 12 = 10 karton
```

Simple, clear, user-friendly, and much safer than repeater-based multi conversion.
