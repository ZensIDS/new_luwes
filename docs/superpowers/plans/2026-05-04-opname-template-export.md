# Stock Opname Template Export + Save Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the stale `physical_qty` bug in `saveOpname` and add a blank printable template export to the Stock Opname page.

**Architecture:** Three independent tasks: (1) fix the save payload so the frontend sends `system_qty`/`physical_qty` directly instead of having the backend calculate them from current DB state; (2) create a new `StockOpnameTemplateExport` class that queries live stock and outputs a blank print-ready Excel; (3) wire up the new route/controller method and replace the export button on the opname page.

**Tech Stack:** Laravel 9, Maatwebsite Excel 3.x, PhpSpreadsheet, Blade, jQuery

**Note:** User will verify manually — no automated test steps.

---

### Task 1: Fix save payload — send system_qty & physical_qty from frontend

**Files:**
- Modify: `resources/views/stocks/opname.blade.php` (save JS, `#btnSaveOpname` handler)
- Modify: `app/Http/Controllers/StockController.php:229-295` (`saveOpname` method)
- Modify: `app/Exports/StockOpnameExport.php:65` (remove TODO comment)

- [ ] **Step 1: Update the `#btnSaveOpname` items loop to capture system_qty and physical_qty**

In `resources/views/stocks/opname.blade.php`, find the `$('#btnSaveOpname').on('click', ...)` handler. Inside the `$('#tableBody tr').each(...)` loop, replace the block that builds `items` with:

```js
$('#tableBody tr').each(function() {
    const row         = $(this);
    const productInput = row.find('.product-name');
    const selectStock  = row.find('.select-stock');

    let stockId = productInput.data('stock-id');
    if (!stockId && selectStock.length) {
        stockId = selectStock.val();
    }

    const selisih     = parseFloat(row.find('.selisih').val()) || 0;
    const keterangan  = row.find('.keterangan').val().trim();
    const systemQty   = parseFloat(row.find('.stock_dikartu').val()) || 0;
    const physicalQty = parseFloat(row.find('.stock_fisik').val()) || 0;

    if (stockId && selisih !== 0) {
        items.push({
            stock_id:     stockId,
            selisih:      selisih,
            system_qty:   systemQty,
            physical_qty: physicalQty,
            keterangan:   keterangan,
        });
    }
});
```

- [ ] **Step 2: Add system_qty and physical_qty to saveOpname() validation**

In `app/Http/Controllers/StockController.php`, inside `saveOpname()`, add two rules to the `$request->validate([...])` call:

```php
$request->validate([
    'adjustment_date'          => 'required|date',
    'items'                    => 'required|array',
    'items.*.stock_id'         => 'required|exists:stocks,id',
    'items.*.selisih'          => 'required|numeric',
    'items.*.system_qty'       => 'nullable|numeric',
    'items.*.physical_qty'     => 'nullable|numeric',
    'items.*.keterangan'       => 'nullable|string',
], [
    'adjustment_date.required' => 'Tanggal penyesuaian harus diisi.',
    'adjustment_date.date'     => 'Tanggal penyesuaian harus berupa tanggal yang valid.',
    'items.required'           => 'Item harus diisi.',
    'items.array'              => 'Item harus berupa array.',
    'items.*.stock_id.required'=> 'Stok harus dipilih.',
    'items.*.stock_id.exists'  => 'Stok yang dipilih tidak ditemukan.',
    'items.*.selisih.required' => 'Selisih harus diisi.',
    'items.*.selisih.numeric'  => 'Selisih harus berupa angka.',
    'items.*.keterangan.string'=> 'Keterangan harus berupa teks.',
]);
```

- [ ] **Step 3: Remove the TODO comment from StockOpnameExport**

In `app/Exports/StockOpnameExport.php`, line 65, replace:

```php
$physicalQty = $item->physical_qty ?? 0; //TODO after some transactions, the stock fisik doesn't changed, for example on opname.blade.php the data correctly show 829 (becuase 82 data are moving/used on transactions) but on export still 911
```

with:

```php
$physicalQty = $item->physical_qty ?? 0;
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/stocks/opname.blade.php \
        app/Http/Controllers/StockController.php \
        app/Exports/StockOpnameExport.php
git commit -m "fix: send physical_qty & system_qty from opname frontend; add validation"
```

---

### Task 2: Create StockOpnameTemplateExport class

**Files:**
- Create: `app/Exports/StockOpnameTemplateExport.php`

- [ ] **Step 1: Create the export class**

Create `app/Exports/StockOpnameTemplateExport.php` with this exact content:

```php
<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockOpnameTemplateExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithDrawings, WithCustomStartCell, WithProperties
{
    use Exportable;

    protected Collection $stocks;
    protected string $date;
    protected array $settings;

    public function __construct(Collection $stocks, string $date, array $settings = [])
    {
        $this->stocks   = $stocks;
        $this->date     = $date;
        $this->settings = $settings;
    }

    public function collection(): Collection
    {
        return $this->stocks;
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Barang',
            'Nama Barang',
            'Batch/SKU',
            'Expired',
            'Satuan',
            'Stok Sistem',
            'Stok Fisik',
            'Selisih',
            'Keterangan',
        ];
    }

    public function map($stock): array
    {
        static $no = 0;
        $no++;

        $systemQty   = $stock->qty ?? 0;
        $konvDisplay = $stock->product->konversiDisplay($systemQty);

        return [
            $no,
            $stock->product->code ?? '-',
            $stock->product->name ?? '-',
            $stock->sku ?? '-',
            $stock->expired_at
                ? Carbon::parse($stock->expired_at)->format('d/m/Y')
                : '-',
            $stock->product->satuan ?? 'PCS',
            $systemQty.($konvDisplay && $konvDisplay !== '-' ? " ({$konvDisplay})" : ''),
            '',  // Stok Fisik — blank for hand-counting
            '',  // Selisih — blank
            '',  // Keterangan — blank
        ];
    }

    public function startCell(): string
    {
        return 'B16';
    }

    public function styles(Worksheet $sheet): void
    {
        $companyName = $this->settings['name'] ?? 'NAMA PERUSAHAAN';
        $address     = $this->settings['address'] ?? 'ALAMAT';
        $phone       = $this->settings['telp'] ?? '';
        $email       = $this->settings['email'] ?? '';
        $website     = $this->settings['website'] ?? '';
        $contactInfo = trim("$phone | $email | $website", ' |');

        $sheet->getRowDimension(1)->setRowHeight(50);

        $sheet->setCellValue('D2', $companyName);
        $sheet->mergeCells('D2:O2');
        $sheet->getStyle('D2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('D3', $address);
        $sheet->mergeCells('D3:O3');
        $sheet->setCellValue('D4', $contactInfo);
        $sheet->mergeCells('D4:O4');
        $sheet->getStyle('D3:D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension(5)->setRowHeight(20);

        $sheet->mergeCells('B6:O6');
        $sheet->getStyle('B6:O6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);

        $sheet->setCellValue('B8', 'TEMPLATE STOCK OPNAME');
        $sheet->mergeCells('B8:O8');
        $sheet->getStyle('B8')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('B10', 'Tanggal :');
        $sheet->setCellValue('D10', Carbon::parse($this->date)->isoFormat('DD MMMM YYYY'));
        $sheet->getStyle('B10')->getFont()->setBold(true);

        $sheet->setCellValue('B11', 'Nama :');
        $sheet->setCellValue('D11', ' ');
        $sheet->getStyle('B11')->getFont()->setBold(true);

        $sheet->setCellValue('B13', 'Detail Stok');
        $sheet->getStyle('B13')->getFont()->setBold(true);

        // TABLE HEADER — 10 columns: B:K
        $sheet->getStyle('B16:K16')->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);
        $sheet->getRowDimension(16)->setRowHeight(28);

        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 16) {
            $sheet->getStyle('B17:K'.$highestRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(24);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(7);
        $sheet->getColumnDimension('H')->setWidth(12);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(9);
        $sheet->getColumnDimension('K')->setWidth(16);

        foreach (['G', 'H', 'I', 'J'] as $col) {
            $sheet->getStyle($col)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    public function drawings(): array
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');

        $logoPath = $this->settings['logo'] ?? null;
        if ($logoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
            $drawing->setPath(\Illuminate\Support\Facades\Storage::disk('public')->path($logoPath));
        } else {
            $drawing->setPath(public_path('img/logo.jpeg'));
        }

        $drawing->setHeight(80);
        $drawing->setCoordinates('B2');

        return [$drawing];
    }

    public function properties(): array
    {
        return [
            'creator'     => config('app.name'),
            'title'       => 'Template Stock Opname',
            'description' => 'Template Stock Opname '.$this->date,
        ];
    }
}
```

- [ ] **Step 2: Run pint**

```bash
./vendor/bin/pint app/Exports/StockOpnameTemplateExport.php
```

- [ ] **Step 3: Commit**

```bash
git add app/Exports/StockOpnameTemplateExport.php
git commit -m "feat: add StockOpnameTemplateExport — blank print-ready stock count template"
```

---

### Task 3: Add route, controller method, and update opname page button

**Files:**
- Modify: `routes/web.php:175` (add route after the existing opname routes)
- Modify: `app/Http/Controllers/StockController.php` (add import + `exportOpnameTemplate()`)
- Modify: `resources/views/stocks/opname.blade.php` (replace export form with link, update lokasi change handler)

- [ ] **Step 1: Add the new route to routes/web.php**

In `routes/web.php`, after line 175 (`Route::post('/stock-opname/save', ...)`), add:

```php
Route::get('/stock-opname/export-template', [App\Http\Controllers\StockController::class, 'exportOpnameTemplate'])->name('stock.opname.export-template');
```

- [ ] **Step 2: Add imports to StockController**

In `app/Http/Controllers/StockController.php`, add these two use statements after the existing ones:

```php
use App\Exports\StockOpnameTemplateExport;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
```

- [ ] **Step 3: Add exportOpnameTemplate() to StockController**

In `app/Http/Controllers/StockController.php`, add this method before the closing `}` of the class (after `saveOpname()`):

```php
public function exportOpnameTemplate(Request $request)
{
    $settings = json_decode(Storage::disk('public')->get('settings.json'), true) ?? [];

    $query = Stock::with('product')
        ->where('qty', '>', 0)
        ->whereNotNull('sku')
        ->orderBy('product_id')
        ->orderBy('sku');

    if ($lokasi = $request->input('lokasi')) {
        $query->whereHas('product', fn ($q) => $q->where('lokasi', $lokasi));
    }

    $stocks = $query->get();
    $date   = date('Y-m-d');

    return Excel::download(
        new StockOpnameTemplateExport($stocks, $date, $settings),
        'Template_Stock_Opname-'.$date.'.xlsx'
    );
}
```

- [ ] **Step 4: Replace the export form in opname.blade.php with a plain link**

In `resources/views/stocks/opname.blade.php`, find and replace the entire `<form method="GET" ...>` export block (lines ~62–69):

```html
<form method="GET" action="{{ route('laporan.stock-opname') }}" style="display:inline;">
    <input type="hidden" name="tanggal" id="exportTanggal" value="{{ date('Y-m-d') }}" />
    <input type="hidden" name="lokasi" id="exportLokasi" value="" />
    <button type="submit" class="btn btn-success">
        <i class="fa fa-file-excel-o"></i> Export Excel
    </button>
    {{-- //TODO Create new Export Excel to print the current shown data from this page, but the stock fisik are always blank (the format etc pretty similar like laporan.stock-opname/StockOpnameExport just the data are different). --}}
</form>
```

Replace with:

```html
<a id="btnExportTemplate" href="{{ route('stock.opname.export-template') }}"
   class="btn btn-success">
    <i class="fa fa-file-excel-o"></i> Export Template
</a>
```

- [ ] **Step 5: Update the #filterLokasi change handler in opname.blade.php**

In `resources/views/stocks/opname.blade.php`, find the existing lokasi change handler:

```js
$('#filterLokasi').on('change', function() {
    $('#exportLokasi').val($(this).val());
    loadStockData();
});
```

Replace with:

```js
$('#filterLokasi').on('change', function() {
    var lok  = $(this).val();
    var base = '{{ route('stock.opname.export-template') }}';
    $('#btnExportTemplate').attr('href', lok ? base + '?lokasi=' + encodeURIComponent(lok) : base);
    loadStockData();
});
```

- [ ] **Step 6: Run pint**

```bash
./vendor/bin/pint app/Http/Controllers/StockController.php routes/web.php
```

- [ ] **Step 7: Commit**

```bash
git add routes/web.php \
        app/Http/Controllers/StockController.php \
        resources/views/stocks/opname.blade.php
git commit -m "feat: add opname template export route, controller method, and update page button"
```

---

## Self-Review

- [x] **Spec coverage:** Save fix (system_qty/physical_qty in frontend payload + validation) ✓ · TODO comment removal ✓ · New export class with blank Stok Fisik/Selisih/Keterangan ✓ · Route + controller ✓ · Button replaced + lokasi filter wired ✓
- [x] **Placeholder scan:** No TBDs. All code blocks are complete.
- [x] **Type consistency:** `StockOpnameTemplateExport` constructor accepts `Collection $stocks` — `exportOpnameTemplate()` passes `$query->get()` which returns a Collection. Route name `stock.opname.export-template` used consistently in routes/web.php, Blade `route()` calls, and JS `base` variable. `system_qty`/`physical_qty` field names match between the JS payload (Task 1 Step 1) and the PHP validation (Task 1 Step 2).
- [x] **`static $no` reset:** The `map()` method in `StockOpnameTemplateExport` uses `static $no = 0` — same pattern as `StockOpnameExport`. This is a known Maatwebsite pattern; it works correctly for a single export per request.
