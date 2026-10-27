<?php

namespace App\Http\Controllers;

use App\Exports\OtherIncomeExport;
use App\Http\Requests\OtherIncomeRequest;
use App\Models\OtherIncome;
use App\Models\OtherIncomeCategory;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\OtherIncomeFilter;
use App\Support\OutletAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Daftar dan input manual Pendapatan Lain (pemasukan di luar penjualan kasir).
 *
 * - staff-outlet dan kasir hanya melihat/mengisi outlet sendiri (K11, K12); income umum tidak tampil bagi mereka.
 * - Baris otomatis (Listing) tidak bisa diubah atau dihapus: tombol nonaktif di UI dan ditolak di server (D3).
 */
class OtherIncomeController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request)
    {
        // Filter dan hak akses dipakai bersama Export Excel (OtherIncomeFilter).
        $filter = OtherIncomeFilter::fromRequest($request);
        $base = $filter->query();

        // Total seluruh hasil filter (bukan hanya halaman ini), satu query SUM.
        $total = (float) (clone $base)->sum('jumlah');

        $incomes = $base
            ->with(['category', 'outlet', 'supplier', 'product'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Kategori untuk filter: termasuk yang sudah dihapus supaya income lama tetap bisa difilter.
        $categories = OtherIncomeCategory::withTrashed()
            ->when($filter->scoped, fn ($q) => $q->manual())
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return view('other-incomes.index', [
            'incomes' => $incomes,
            'total' => $total,
            'categories' => $categories,
            'outlets' => OutletAccess::outlets(),
            'scoped' => $filter->scoped,
            'filters' => $filter->toArray(),
        ]);
    }

    /** E1: Export Excel dengan filter yang sedang aktif di halaman daftar. */
    public function export(Request $request)
    {
        $export = new OtherIncomeExport($request);

        // fileName() memvalidasi filter lebih dulu: filter tidak valid kembali ke halaman dengan pesan error.
        return Excel::download($export, $export->fileName());
    }

    public function create(Request $request)
    {
        $income = new OtherIncome();
        $categories = OtherIncomeCategory::manual()->orderBy('name')->get();

        return view('other-incomes.form', [
            'income' => $income,
            'categories' => $categories,
            'sewaCategoryIds' => $this->sewaCategoryIds($categories),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'deleted_at']),
            'selectedProduct' => $this->selectedProduct($request, $income),
            'outlets' => OutletAccess::outlets(),
            'scoped' => $this->isOutletScoped($request->user()),
            'method' => 'POST',
            'action' => route('other-income.store'),
        ]);
    }

    public function store(OtherIncomeRequest $request): RedirectResponse
    {
        // Tanggal tidak diinput: otomatis = waktu input (sama dengan created_at).
        OtherIncome::create($this->payload($request->validated()) + [
            'tanggal' => now()->toDateString(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('other-income.index')->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function edit(Request $request, OtherIncome $otherIncome)
    {
        if ($blocked = $this->guard($otherIncome)) {
            return $blocked;
        }

        // Kategori pilihan: yang aktif, ditambah kategori income ini sendiri walau sudah dihapus.
        $categories = OtherIncomeCategory::withTrashed()
            ->manual()
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('id', $otherIncome->other_income_category_id))
            ->orderBy('name')
            ->get();

        // Supplier pilihan: yang aktif, ditambah supplier income ini sendiri walau sudah dihapus.
        $suppliers = Supplier::withTrashed()
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhere('id', $otherIncome->supplier_id))
            ->orderBy('name')
            ->get(['id', 'name', 'deleted_at']);

        return view('other-incomes.form', [
            'income' => $otherIncome,
            'categories' => $categories,
            'sewaCategoryIds' => $this->sewaCategoryIds($categories),
            'suppliers' => $suppliers,
            'selectedProduct' => $this->selectedProduct($request, $otherIncome),
            'outlets' => OutletAccess::outlets(),
            'scoped' => $this->isOutletScoped($request->user()),
            'method' => 'PUT',
            'action' => route('other-income.update', $otherIncome),
        ]);
    }

    public function update(OtherIncomeRequest $request, OtherIncome $otherIncome): RedirectResponse
    {
        if ($blocked = $this->guard($otherIncome)) {
            return $blocked;
        }

        // Tanggal tidak ikut diubah: tetap waktu input awal.
        $otherIncome->update($this->payload($request->validated()));

        return redirect()->route('other-income.index')->with('toast_success', 'Berhasil Menyimpan Data!');
    }

    public function destroy(OtherIncome $otherIncome): RedirectResponse
    {
        if ($blocked = $this->guard($otherIncome)) {
            return $blocked;
        }

        $otherIncome->delete();

        return redirect()->route('other-income.index')->with('toast_success', 'Berhasil Menghapus Data!');
    }

    /**
     * Select2 AJAX produk untuk kategori Sewa: hanya produk milik supplier terpilih,
     * minimal 2 karakter, maksimal 20 hasil.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim((string) ($data['q'] ?? ''));

        if (mb_strlen($search) < 2) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        $like = addcslashes($search, '\\%_');

        $rows = Product::query()
            ->whereIn('id', fn ($q) => $q
                ->select('product_id')
                ->from('product_supplier')
                ->where('supplier_id', (int) $data['supplier_id']))
            ->where(function ($q) use ($like) {
                $q->where('name', 'like', "%{$like}%")
                    ->orWhere('code', 'like', "{$like}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'code', 'name']);

        return response()->json([
            'results' => $rows->map(fn ($product) => [
                'id' => $product->id,
                'text' => "{$product->code} — {$product->name}",
            ])->values(),
            'pagination' => ['more' => false],
        ]);
    }

    /** ID kategori Sewa di antara kategori yang ditampilkan (dipakai form untuk memunculkan isian sewa). */
    private function sewaCategoryIds($categories): array
    {
        return $categories->filter(fn ($category) => $category->isSewa())->pluck('id')->values()->all();
    }

    /** Produk terpilih (edit / old input) untuk mengisi Select2 ajax; bukan seluruh katalog. */
    private function selectedProduct(Request $request, OtherIncome $income): ?Product
    {
        $id = $request->old('product_id', $income->product_id);

        return $id ? Product::withTrashed()->select(['id', 'code', 'name'])->find($id) : null;
    }

    /** Snapshot nama produk sewa disimpan supaya tetap tampil walau produk dihapus; kosong untuk kategori lain. */
    private function payload(array $data): array
    {
        $data['product_name_snapshot'] = ! empty($data['product_id'])
            ? Product::withTrashed()->whereKey($data['product_id'])->value('name')
            : null;

        return $data;
    }

    /**
     * Penjaga ubah/hapus: 404 bila income di luar hak akses outlet user; ditolak bila income otomatis (Listing).
     */
    private function guard(OtherIncome $income): ?RedirectResponse
    {
        abort_unless(
            OtherIncome::query()->visibleTo(auth()->user())->whereKey($income->getKey())->exists(),
            404
        );

        if ($income->is_auto) {
            return redirect()
                ->route('other-income.index')
                ->with('toast_error', 'Income otomatis (Listing) tidak bisa diubah atau dihapus.');
        }

        return null;
    }

    private function isOutletScoped($user): bool
    {
        return $user && in_array($user->role, OtherIncome::OUTLET_SCOPED_ROLES, true);
    }
}