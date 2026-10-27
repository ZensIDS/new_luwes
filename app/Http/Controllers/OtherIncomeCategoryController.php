<?php

namespace App\Http\Controllers;

use App\Models\OtherIncomeCategory;
use App\Support\IndonesianNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Kategori Pendapatan Lain. Hanya superadmin dan owner (route middleware, K11).
 *
 * - Kategori manual: tambah, ubah nama, hapus (soft delete; income lama tetap menampilkan nama kategorinya).
 *   Kecuali kategori Sewa: terkunci (nama dan hapus) karena memicu isian khusus di form.
 * - Kategori sistem "Listing Product": nama, hapus, dan nonaktif terkunci. Hanya Nominal Listing yang bisa diatur.
 */
class OtherIncomeCategoryController extends Controller
{
    public function index()
    {
        return view('other-income-categories.index', [
            'listing' => OtherIncomeCategory::listing(),
            'categories' => OtherIncomeCategory::manual()->withCount('incomes')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validateWithBag('create', [
            'name' => ['required', 'string', 'max:100', $this->uniqueName()],
        ], $this->nameMessages());

        OtherIncomeCategory::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'is_system' => false,
            'nominal' => null,
        ]);

        return redirect()->route('other-income.categories.index')->with('toast_success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, OtherIncomeCategory $otherIncomeCategory): RedirectResponse
    {
        if ($blocked = $this->guardSystem($otherIncomeCategory, 'Nama kategori sistem tidak bisa diubah.')) {
            return $blocked;
        }

        if ($blocked = $this->guardSewa($otherIncomeCategory, 'Nama kategori Sewa tidak bisa diubah.')) {
            return $blocked;
        }

        $request->merge(['name' => trim((string) $request->input('name'))]);

        $data = $request->validateWithBag('rename' . $otherIncomeCategory->id, [
            'name' => ['required', 'string', 'max:100', $this->uniqueName($otherIncomeCategory->id)],
        ], $this->nameMessages());

        $otherIncomeCategory->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
        ]);

        return redirect()->route('other-income.categories.index')->with('toast_success', 'Nama kategori berhasil diubah.');
    }

    public function destroy(OtherIncomeCategory $otherIncomeCategory): RedirectResponse
    {
        if ($blocked = $this->guardSystem($otherIncomeCategory, 'Kategori sistem tidak bisa dihapus.')) {
            return $blocked;
        }

        if ($blocked = $this->guardSewa($otherIncomeCategory, 'Kategori Sewa tidak bisa dihapus.')) {
            return $blocked;
        }

        // Soft delete: income lama tetap ada dan tetap menampilkan nama kategorinya.
        $otherIncomeCategory->delete();

        return redirect()->route('other-income.categories.index')->with('toast_success', 'Kategori berhasil dihapus.');
    }

    /**
     * Atur Nominal Listing (K1). Perubahan tercatat di activity log lewat model; income lama tidak berubah.
     */
    public function updateListingNominal(Request $request): RedirectResponse
    {
        $listing = OtherIncomeCategory::listing();
        abort_unless($listing, 404, 'Kategori Listing Product belum tersedia. Jalankan migrasi Pendapatan Lain.');

        $request->merge(['nominal' => IndonesianNumber::parse($request->input('nominal'))]);

        $data = $request->validateWithBag('nominal', [
            'nominal' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
        ], [
            'nominal.required' => 'Nominal Listing wajib diisi.',
            'nominal.numeric' => 'Nominal Listing harus berupa angka.',
            'nominal.min' => 'Nominal Listing minimal 1.',
            'nominal.max' => 'Nominal Listing terlalu besar.',
        ]);

        $listing->update(['nominal' => $data['nominal']]);

        return redirect()->route('other-income.categories.index')->with('toast_success', 'Nominal Listing berhasil disimpan.');
    }

    private function guardSystem(OtherIncomeCategory $category, string $message): ?RedirectResponse
    {
        if ($category->is_system) {
            return redirect()->route('other-income.categories.index')->with('toast_error', $message);
        }

        return null;
    }

    /**
     * Kategori Sewa dikenali form Pendapatan Lain lewat namanya (slug), jadi tidak boleh diubah nama / dihapus
     * supaya isian periode, supplier, dan produk sewa tidak hilang diam-diam.
     */
    private function guardSewa(OtherIncomeCategory $category, string $message): ?RedirectResponse
    {
        if ($category->isSewa()) {
            return redirect()->route('other-income.categories.index')->with('toast_error', $message);
        }

        return null;
    }

    /** Nama unik di antara kategori yang belum dihapus (termasuk kategori sistem). */
    private function uniqueName(?int $ignoreId = null)
    {
        return Rule::unique('other_income_categories', 'name')
            ->whereNull('deleted_at')
            ->ignore($ignoreId);
    }

    private function nameMessages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
            'name.max' => 'Nama kategori maksimal 100 karakter.',
            'name.unique' => 'Nama kategori sudah dipakai.',
        ];
    }
}