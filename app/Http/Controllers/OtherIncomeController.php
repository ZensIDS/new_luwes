<?php

namespace App\Http\Controllers;

use App\Exports\OtherIncomeExport;
use App\Http\Requests\OtherIncomeRequest;
use App\Models\OtherIncome;
use App\Models\OtherIncomeCategory;
use App\Support\OtherIncomeFilter;
use App\Support\OutletAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Daftar dan input manual Other Income (pemasukan di luar penjualan kasir).
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
            ->with(['category', 'outlet'])
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
        return view('other-incomes.form', [
            'income' => new OtherIncome(['tanggal' => now()]),
            'categories' => OtherIncomeCategory::manual()->orderBy('name')->get(),
            'outlets' => OutletAccess::outlets(),
            'scoped' => $this->isOutletScoped($request->user()),
            'method' => 'POST',
            'action' => route('other-income.store'),
        ]);
    }

    public function store(OtherIncomeRequest $request): RedirectResponse
    {
        OtherIncome::create($request->validated() + [
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

        return view('other-incomes.form', [
            'income' => $otherIncome,
            'categories' => $categories,
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

        $otherIncome->update($request->validated());

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