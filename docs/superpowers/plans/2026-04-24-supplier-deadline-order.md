# Supplier Deadline Order Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a recurring order-deadline schedule to each supplier so the dashboard notifies staff 3 days before a PO is due, and the notification auto-clears once the deadline passes.

**Architecture:** Two new nullable columns on `suppliers` (`deadline_days` JSON, `deadline_interval_weeks` tinyint, `deadline_reference_date` date) drive a pure-PHP `nextDeadlineDate()` model method. No background jobs or DB notifications table — the dashboard queries suppliers at render-time and shows only those with a next deadline ≤ today+3. Supplier create/edit forms lose the PIC field and gain a deadline UI (interval selector + day checkboxes).

**Tech Stack:** Laravel 9, Blade, Bootstrap 3 (AdminLTE), jQuery, Carbon.

---

## Codebase Context (read before touching anything)

- `app/Models/Supplier.php` — fillable: `name, kode_supplier, pic_supplier, alamat, no_telp`. SoftDeletes. BelongsToMany `products`.
- `app/Http/Controllers/SupplierController.php` — standard resource. `index()`, `create()`, `store()`, `edit()`, `update()`, `destroy()`, plus export/import helpers.
- `app/Http/Requests/SupplierRequest.php` — `pic_supplier` is `nullable|string`; `name`, `alamat`, `no_telp` are required.
- `resources/views/suppliers/create.blade.php` and `edit.blade.php` — Bootstrap 3 `.box` form. Both currently have a PIC field.
- `resources/views/suppliers/index.blade.php` — AdminLTE DataTable (`#example1`). Columns: No, Kode, PIC, Nama, Alamat, Nomor Telp, Aksi.
- `app/Http/Controllers/DashboardController.php` — `index()` passes `products, stocks, penjualans, pembelianTerkirim, totalRevenue` to `resources/views/dashboard/index.blade.php`.
- `resources/views/dashboard/index.blade.php` — row of `small-box` stat cards + Highcharts chart divs. Add the supplier-deadline widget at the top, before the stats row.
- Day numbering uses **Carbon ISO day**: `dayOfWeekIso` → 1=Monday … 7=Sunday.

---

## File Map

| Action | File |
|--------|------|
| Create | `database/migrations/2026_04_24_000001_add_deadline_fields_to_suppliers_table.php` |
| Modify | `app/Models/Supplier.php` |
| Modify | `app/Http/Requests/SupplierRequest.php` |
| Modify | `resources/views/suppliers/create.blade.php` |
| Modify | `resources/views/suppliers/edit.blade.php` |
| Modify | `resources/views/suppliers/index.blade.php` |
| Modify | `app/Http/Controllers/DashboardController.php` |
| Modify | `resources/views/dashboard/index.blade.php` |

---

## Task 1: Migration — Add Deadline Fields to Suppliers

**Files:**
- Create: `database/migrations/2026_04_24_000001_add_deadline_fields_to_suppliers_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->json('deadline_days')->nullable()->after('no_telp');
            $table->tinyInteger('deadline_interval_weeks')->unsigned()->nullable()->after('deadline_days');
            $table->date('deadline_reference_date')->nullable()->after('deadline_interval_weeks');
        });
    }

    public function down()
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['deadline_days', 'deadline_interval_weeks', 'deadline_reference_date']);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_04_24_000001_add_deadline_fields_to_suppliers_table` then `Migrated`.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_04_24_000001_add_deadline_fields_to_suppliers_table.php
git commit -m "feat: add deadline schedule fields to suppliers table"
```

---

## Task 2: Supplier Model — Fillable, Casts, and Deadline Logic

**Files:**
- Modify: `app/Models/Supplier.php`

- [ ] **Step 1: Replace the Supplier model with the updated version**

```php
<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'kode_supplier',
        'alamat',
        'no_telp',
        'deadline_days',
        'deadline_interval_weeks',
        'deadline_reference_date',
    ];

    protected $casts = [
        'deadline_days'           => 'array',
        'deadline_reference_date' => 'date',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_supplier');
    }

    /**
     * Returns the next deadline date >= today, or null if no deadline configured.
     *
     * deadline_days: ISO weekday array e.g. [1] = Monday, [1,4] = Mon+Thu (1=Mon,7=Sun)
     * deadline_interval_weeks: 1/2/3
     * deadline_reference_date: anchor week (any past Monday works)
     */
    public function nextDeadlineDate(): ?Carbon
    {
        if (empty($this->deadline_days) || !$this->deadline_interval_weeks) {
            return null;
        }

        $days     = array_map('intval', (array) $this->deadline_days);
        $interval = (int) $this->deadline_interval_weeks;
        $ref      = Carbon::parse($this->deadline_reference_date ?? $this->created_at)->startOfWeek();
        $today    = Carbon::today();
        $limit    = $today->copy()->addDays($interval * 7 + 14);

        for ($d = $today->copy(); $d->lte($limit); $d->addDay()) {
            if (!in_array($d->dayOfWeekIso, $days)) {
                continue;
            }
            $weeksSinceRef = (int) abs($ref->diffInWeeks($d->copy()->startOfWeek()));
            if ($weeksSinceRef % $interval === 0) {
                return $d->copy();
            }
        }

        return null;
    }

    /** True if next deadline is within 3 days (but not past). */
    public function isDeadlineUrgent(): bool
    {
        $next = $this->nextDeadlineDate();
        if (!$next) {
            return false;
        }
        $daysUntil = Carbon::today()->diffInDays($next, false);
        return $daysUntil >= 0 && $daysUntil <= 3;
    }
}
```

Note: `pic_supplier` is removed from `$fillable`. It remains in the DB column so no data is lost — it just won't be updatable going forward.

- [ ] **Step 2: Verify PHP syntax**

```bash
php -l app/Models/Supplier.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```bash
git add app/Models/Supplier.php
git commit -m "feat: add nextDeadlineDate() and isDeadlineUrgent() to Supplier model"
```

---

## Task 3: SupplierRequest — Add Deadline Validation, Remove PIC

**Files:**
- Modify: `app/Http/Requests/SupplierRequest.php`

- [ ] **Step 1: Replace the rules() method**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'kode_supplier'            => 'nullable|string',
            'name'                     => 'required|string',
            'alamat'                   => 'required|string',
            'no_telp'                  => 'required|string',
            'deadline_days'            => 'nullable|array',
            'deadline_days.*'          => 'integer|between:1,7',
            'deadline_interval_weeks'  => 'nullable|integer|in:1,2,3',
            'deadline_reference_date'  => 'nullable|date',
        ];
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l app/Http/Requests/SupplierRequest.php
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/SupplierRequest.php
git commit -m "feat: add deadline validation to SupplierRequest, remove pic_supplier"
```

---

## Task 4: Supplier Create & Edit Views — Remove PIC, Add Deadline UI

**Files:**
- Modify: `resources/views/suppliers/create.blade.php`
- Modify: `resources/views/suppliers/edit.blade.php`

The deadline UI has two parts:
1. **Interval selector** — a `<select>` for 1/2/3 weeks.
2. **Day checkboxes** — Mon through Sun (ISO 1–7), allow multiple.
3. **Reference date** — a hidden date input auto-set to the nearest past Monday via JS, or the user can override.

- [ ] **Step 1: Update `resources/views/suppliers/create.blade.php`**

Replace the entire `<div class="box-body">` contents with:

```html
<div class="box-body">
    <div class="form-group">
        <label>Kode</label>
        <input type="text" class="form-control" name="kode_supplier"
            value="{{ old('kode_supplier') }}" placeholder="Masukkan Kode Supplier">
        @error('kode_supplier')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label>Nama Supplier <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="name" value="{{ old('name') }}"
            placeholder="Masukkan Nama Supplier">
        @error('name')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label>Alamat <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="alamat" value="{{ old('alamat') }}"
            placeholder="Masukkan Alamat">
        @error('alamat')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label>Nomor Telp <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="no_telp" value="{{ old('no_telp') }}"
            placeholder="Masukkan Nomor Telp">
        @error('no_telp')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
    </div>

    {{-- Deadline Order --}}
    <div class="form-group">
        <label>Jadwal Deadline Order</label>
        <div class="row">
            <div class="col-sm-4">
                <label class="control-label" style="font-weight:normal">Jangka Waktu</label>
                <select class="form-control" name="deadline_interval_weeks">
                    <option value="">— Tidak ada deadline —</option>
                    <option value="1" {{ old('deadline_interval_weeks') == 1 ? 'selected' : '' }}>1 Minggu Sekali</option>
                    <option value="2" {{ old('deadline_interval_weeks') == 2 ? 'selected' : '' }}>2 Minggu Sekali</option>
                    <option value="3" {{ old('deadline_interval_weeks') == 3 ? 'selected' : '' }}>3 Minggu Sekali</option>
                </select>
            </div>
            <div class="col-sm-8">
                <label class="control-label" style="font-weight:normal">Hari Deadline</label>
                <div class="deadline-days-checkboxes">
                    @php
                        $dayLabels = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];
                        $oldDays   = (array) old('deadline_days', []);
                    @endphp
                    @foreach($dayLabels as $num => $label)
                        <label class="checkbox-inline">
                            <input type="checkbox" name="deadline_days[]" value="{{ $num }}"
                                {{ in_array($num, $oldDays) ? 'checked' : '' }}>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <input type="hidden" name="deadline_reference_date" id="deadlineReferenceDate"
            value="{{ old('deadline_reference_date') }}">
        <p class="help-block text-muted" style="margin-top:6px">
            <i class="fa fa-info-circle"></i>
            Notifikasi akan muncul H-3 sebelum deadline di dashboard.
        </p>
    </div>
</div>
```

Add this JS before `@endsection` at the bottom:

```html
@section('page-script')
<script>
// Auto-set reference date to nearest past Monday on page load
(function() {
    var ref = document.getElementById('deadlineReferenceDate');
    if (ref && !ref.value) {
        var d = new Date();
        var day = d.getDay(); // 0=Sun, 1=Mon...
        var diff = day === 0 ? -6 : 1 - day; // offset to Monday
        d.setDate(d.getDate() + diff);
        ref.value = d.toISOString().slice(0, 10);
    }
})();
</script>
@endsection
```

- [ ] **Step 2: Update `resources/views/suppliers/edit.blade.php`**

Same structure as create, but bind existing values. Replace the `<div class="box-body">` contents with:

```html
<div class="box-body">
    <div class="form-group">
        <label>Kode</label>
        <input type="text" class="form-control" name="kode_supplier"
            value="{{ old('kode_supplier', $supplier->kode_supplier) }}" placeholder="Masukkan Kode Supplier">
        @error('kode_supplier')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label>Nama Supplier <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="name"
            value="{{ old('name', $supplier->name) }}" placeholder="Masukkan Nama Supplier">
        @error('name')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label>Alamat <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="alamat"
            value="{{ old('alamat', $supplier->alamat) }}" placeholder="Masukkan Alamat">
        @error('alamat')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
    </div>
    <div class="form-group">
        <label>Nomor Telp <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="no_telp"
            value="{{ old('no_telp', $supplier->no_telp) }}" placeholder="Masukkan Nomor Telp">
        @error('no_telp')<div class="invalid-feedback text-danger">{{ $message }}</div>@enderror
    </div>

    {{-- Deadline Order --}}
    <div class="form-group">
        <label>Jadwal Deadline Order</label>
        <div class="row">
            <div class="col-sm-4">
                <label class="control-label" style="font-weight:normal">Jangka Waktu</label>
                <select class="form-control" name="deadline_interval_weeks">
                    <option value="">— Tidak ada deadline —</option>
                    @foreach([1=>'1 Minggu Sekali',2=>'2 Minggu Sekali',3=>'3 Minggu Sekali'] as $val => $label)
                        <option value="{{ $val }}"
                            {{ old('deadline_interval_weeks', $supplier->deadline_interval_weeks) == $val ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-8">
                <label class="control-label" style="font-weight:normal">Hari Deadline</label>
                <div class="deadline-days-checkboxes">
                    @php
                        $dayLabels   = [1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'];
                        $savedDays   = (array) old('deadline_days', $supplier->deadline_days ?? []);
                    @endphp
                    @foreach($dayLabels as $num => $label)
                        <label class="checkbox-inline">
                            <input type="checkbox" name="deadline_days[]" value="{{ $num }}"
                                {{ in_array($num, $savedDays) ? 'checked' : '' }}>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
        <input type="hidden" name="deadline_reference_date" id="deadlineReferenceDate"
            value="{{ old('deadline_reference_date', $supplier->deadline_reference_date?->format('Y-m-d')) }}">
        <p class="help-block text-muted" style="margin-top:6px">
            <i class="fa fa-info-circle"></i>
            Notifikasi akan muncul H-3 sebelum deadline di dashboard.
        </p>
    </div>
</div>
```

Same JS block at the bottom (only sets reference date if empty):

```html
@section('page-script')
<script>
(function() {
    var ref = document.getElementById('deadlineReferenceDate');
    if (ref && !ref.value) {
        var d = new Date();
        var day = d.getDay();
        var diff = day === 0 ? -6 : 1 - day;
        d.setDate(d.getDate() + diff);
        ref.value = d.toISOString().slice(0, 10);
    }
})();
</script>
@endsection
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/suppliers/create.blade.php resources/views/suppliers/edit.blade.php
git commit -m "feat: supplier form — remove PIC field, add deadline schedule UI"
```

---

## Task 5: Supplier Index — Add Next Deadline Column + Urgency Badge

**Files:**
- Modify: `resources/views/suppliers/index.blade.php`
- Modify: `app/Http/Controllers/SupplierController.php`

The controller currently does `Supplier::get()`. To use `nextDeadlineDate()` (pure PHP, no extra query), no query change is needed. The method runs on each model instance in the loop.

- [ ] **Step 1: Update `SupplierController::index()`**

Replace:
```php
public function index()
{
    return view('suppliers.index', [
        'suppliers' => Supplier::get(),
    ]);
}
```

With:
```php
public function index()
{
    return view('suppliers.index', [
        'suppliers' => Supplier::orderBy('name')->get(),
    ]);
}
```

- [ ] **Step 2: Update the index table in `resources/views/suppliers/index.blade.php`**

Replace the `<thead>` row:
```html
<tr>
    <td>No</td>
    <td>Kode</td>
    <td>PIC</td>
    <td>Nama</td>
    <td>Alamat</td>
    <td>Nomor Telp</td>
    <td>Aksi</td>
</tr>
```

With (PIC removed, Deadline added):
```html
<tr>
    <th>No</th>
    <th>Kode</th>
    <th>Nama</th>
    <th>Alamat</th>
    <th>Nomor Telp</th>
    <th>Deadline Order</th>
    <th>Aksi</th>
</tr>
```

Replace the `@foreach` body:
```html
@foreach ($suppliers as $value)
    @php
        $nextDeadline = $value->nextDeadlineDate();
        $daysUntil    = $nextDeadline ? \Carbon\Carbon::today()->diffInDays($nextDeadline, false) : null;
    @endphp
    <tr>
        <td>{{ $loop->iteration }}</td>
        <td>{{ $value->kode_supplier }}</td>
        <td>{{ $value->name }}</td>
        <td>{{ $value->alamat }}</td>
        <td>{{ $value->no_telp }}</td>
        <td>
            @if($nextDeadline)
                {{ $nextDeadline->isoFormat('DD MMM YYYY') }}
                @if($daysUntil !== null && $daysUntil <= 3 && $daysUntil >= 0)
                    <span class="label label-danger">H-{{ $daysUntil }}</span>
                @elseif($daysUntil !== null && $daysUntil <= 7)
                    <span class="label label-warning">{{ $daysUntil }} hari lagi</span>
                @endif
            @else
                <span class="text-muted">—</span>
            @endif
        </td>
        <td>
            <a class="btn btn-warning btn-xs" href="{{ route('supplier.edit', $value->id) }}">Edit</a>
            <form action="{{ route('supplier.destroy', $value->id) }}" method="post" style="display:inline">
                @method('delete')
                @csrf
                <button class="btn btn-danger btn-xs" onclick="return confirm('Hapus supplier ini?')">Hapus</button>
            </form>
        </td>
    </tr>
@endforeach
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/suppliers/index.blade.php app/Http/Controllers/SupplierController.php
git commit -m "feat: supplier index — add next deadline column with H-3 urgency badge"
```

---

## Task 6: Dashboard — Urgent Supplier Deadline Widget

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard/index.blade.php`

The widget shows suppliers whose `nextDeadlineDate()` is between today and today+3 (inclusive). It hides automatically once the deadline passes (the method only returns future dates).

- [ ] **Step 1: Update `DashboardController::index()`**

Add the supplier query. Replace the current `index()` method:

```php
public function index(Request $request)
{
    $urgentSuppliers = Supplier::whereNotNull('deadline_days')
        ->whereNotNull('deadline_interval_weeks')
        ->get()
        ->filter(fn ($s) => $s->isDeadlineUrgent())
        ->map(function ($s) {
            $s->next_deadline = $s->nextDeadlineDate();
            return $s;
        })
        ->sortBy('next_deadline')
        ->values();

    $bestBuyProducts = [];
    $bestBuySuppliers = [];
    $salesGraph = [];
    $productGraph = [];
    $monthlyRevenue = [];

    if ($request->wantsJson()) {
        return response()->json([
            'bestBuyProducts'  => $bestBuyProducts,
            'bestBuySuppliers' => $bestBuySuppliers,
            'salesGraph'       => $salesGraph,
            'productGraph'     => $productGraph,
            'monthlyRevenue'   => $monthlyRevenue,
        ]);
    }

    return view('dashboard.index', [
        'products'          => Product::count(),
        'stocks'            => Stock::sum('qty'),
        'penjualans'        => Penjualan::count(),
        'pembelianTerkirim' => Pembelian::where('is_published', true)->count(),
        'totalRevenue'      => 0,
        'urgentSuppliers'   => $urgentSuppliers,
    ]);
}
```

Add `use App\Models\Supplier;` to the imports at the top of `DashboardController.php`.

- [ ] **Step 2: Add the widget to `resources/views/dashboard/index.blade.php`**

Insert this block **at the very top of** `<section class="content">`, before the stats `.row`:

```html
<!-- WIDGET: SUPPLIER-DEADLINE -->
@if($urgentSuppliers->isNotEmpty())
<div class="row">
    <div class="col-xs-12">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-bell"></i>
                    Deadline PO Supplier Mendekat
                </h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-condensed">
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>Deadline</th>
                            <th>Sisa Hari</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($urgentSuppliers as $s)
                            @php $days = \Carbon\Carbon::today()->diffInDays($s->next_deadline, false); @endphp
                            <tr class="{{ $days === 0 ? 'danger' : ($days <= 1 ? 'warning' : '') }}">
                                <td><strong>{{ $s->name }}</strong></td>
                                <td>{{ $s->next_deadline->isoFormat('dddd, DD MMM YYYY') }}</td>
                                <td>
                                    @if($days === 0)
                                        <span class="label label-danger">HARI INI</span>
                                    @else
                                        <span class="label label-warning">H-{{ $days }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('pembelian.create') }}" class="btn btn-xs btn-primary">
                                        <i class="fa fa-plus"></i> Buat PO
                                    </a>
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
<!-- END WIDGET: SUPPLIER-DEADLINE -->
```

- [ ] **Step 3: Verify PHP syntax**

```bash
php -l app/Http/Controllers/DashboardController.php
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/DashboardController.php resources/views/dashboard/index.blade.php
git commit -m "feat: dashboard urgent supplier deadline widget (H-3 alert)"
```

---

## Self-Review

### Spec Coverage

| Requirement | Task |
|-------------|------|
| Remove PIC field from create & edit | Task 4 |
| Add deadline: select jangka waktu (1/2/3 weeks) | Task 4 |
| Add deadline: select hari (Senin–Minggu, multiple) | Task 4 |
| Notif H-3 sebelum deadline | Tasks 5 + 6 |
| Dashboard table: "Silahkan segera PO di supplier ini" | Task 6 |
| Data tidak perlu tampil jika sudah lewat | Task 2 (`nextDeadlineDate()` only returns future dates) |
| Contoh: tiap Senin 1/2/3 minggu sekali, tiap Senin+Kamis 1/2 minggu sekali | Task 2 (multi-day JSON array + interval) |

### Placeholder Scan
None.

### Type Consistency
- `deadline_days` is cast to `array` on the model → Blade `in_array($num, $savedDays)` works.
- `nextDeadlineDate()` returns `?Carbon` → blade `->isoFormat()` works, controller `->sortBy('next_deadline')` works after `$s->next_deadline = $s->nextDeadlineDate()`.
- `isDeadlineUrgent()` used in dashboard filter; `nextDeadlineDate()` used in index column — same method, consistent.
