<?php

namespace App\Http\Controllers;

use App\Models\Kresek;
use Illuminate\Http\Request;

/**
 * Master Kresek (kantong plastik). CRUD hanya superadmin dan staff-outlet (lihat routes);
 * daftar kresek aktif untuk layar kasir lewat active() (semua role kasir).
 */
class KresekController extends Controller
{
    public function index()
    {
        return view('kreseks.index', [
            'kreseks' => Kresek::orderBy('name')->get(),
        ]);
    }

    /** Dipakai layar kasir (F9 Kresek): hanya kresek aktif. */
    public function active()
    {
        return response()->json(
            Kresek::active()->orderBy('name')->get(['id', 'name', 'price'])
        );
    }

    public function create()
    {
        return view('kreseks.create');
    }

    public function store(Request $request)
    {
        Kresek::create($this->validated($request));

        return redirect()->route('kresek.index')->with('toast_success', 'Kresek berhasil disimpan.');
    }

    public function edit(Kresek $kresek)
    {
        return view('kreseks.edit', ['kresek' => $kresek]);
    }

    public function update(Request $request, Kresek $kresek)
    {
        $kresek->update($this->validated($request));

        return redirect()->route('kresek.index')->with('toast_success', 'Kresek berhasil diperbarui.');
    }

    public function destroy(Kresek $kresek)
    {
        // Soft delete: nota lama tetap utuh karena nama dan harga disalin ke penjualan_kreseks.
        $kresek->delete();

        return redirect()->route('kresek.index')->with('toast_success', 'Kresek berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        // Harga boleh 0. Terima format Indonesia (1.200) dari input bertanda currency.
        $request->merge([
            'price' => \App\Support\IndonesianNumber::parse($request->input('price')),
            'is_active' => $request->boolean('is_active'),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'is_active' => ['boolean'],
        ], [
            'name.required' => 'Nama kresek wajib diisi.',
            'price.required' => 'Harga wajib diisi (boleh 0).',
            'price.numeric' => 'Harga harus berupa angka.',
            'price.min' => 'Harga tidak boleh negatif.',
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}