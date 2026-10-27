<?php

namespace App\Http\Requests;

use App\Models\OtherIncome;
use App\Models\OtherIncomeCategory;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\IndonesianNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Input Pendapatan Lain MANUAL (tambah / ubah).
 *
 * - Tanggal tidak diinput: otomatis waktu input (diisi controller saat simpan, tidak berubah saat edit).
 * - Kategori Sewa wajib mengisi periode, supplier, dan produk (produk harus milik supplier terpilih).
 *   Kategori lain: isian sewa dikosongkan di server walau dikirim paksa.
 *
 * - Kategori sistem (Listing Product) ditolak di server walau dikirim paksa (D2).
 * - Outlet opsional; kosong = umum. staff-outlet dan kasir otomatis terkunci ke outlet sendiri (K12).
 */
class OtherIncomeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        $outletId = $this->filled('outlet_id') ? $this->input('outlet_id') : null;

        if ($user && in_array($user->role, OtherIncome::OUTLET_SCOPED_ROLES, true)) {
            abort_unless($user->outlet_id, 422, 'User belum memiliki outlet.');
            if ($outletId !== null && (int) $outletId !== (int) $user->outlet_id) {
                abort(403, 'User tidak memiliki akses ke outlet tersebut.');
            }
            $outletId = $user->outlet_id;
        }

        $this->merge([
            'jumlah' => IndonesianNumber::parse($this->input('jumlah')),
            'outlet_id' => $outletId,
        ]);

        if (! $this->isSewaCategory()) {
            $this->merge([
                'sewa_mulai' => null,
                'sewa_selesai' => null,
                'supplier_id' => null,
                'product_id' => null,
            ]);
        }
    }

    private function isSewaCategory(): bool
    {
        $id = $this->input('other_income_category_id');

        if (! is_numeric($id)) {
            return false;
        }

        return (bool) OtherIncomeCategory::withTrashed()->find((int) $id)?->isSewa();
    }

    public function rules(): array
    {
        /** @var OtherIncome|null $current */
        $current = $this->route('otherIncome');
        $sewa = $this->isSewaCategory();

        return [
            'other_income_category_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($current) {
                    $category = OtherIncomeCategory::withTrashed()->find($value);

                    if (! $category) {
                        $fail('Kategori tidak valid.');
                    } elseif ($category->is_system) {
                        $fail('Kategori sistem (Listing Product) tidak bisa dipakai untuk input manual.');
                    } elseif ($category->trashed()
                        && ! ($current && (int) $current->other_income_category_id === (int) $value)) {
                        // Income lama boleh tetap memakai kategorinya yang sudah dihapus, tapi tidak untuk pilihan baru.
                        $fail('Kategori sudah dihapus. Pilih kategori lain.');
                    }
                },
            ],
            'jumlah' => ['required', 'numeric', 'min:1', 'max:9999999999999'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id'],

            'sewa_mulai' => [Rule::requiredIf($sewa), 'nullable', 'date'],
            'sewa_selesai' => [Rule::requiredIf($sewa), 'nullable', 'date', 'after_or_equal:sewa_mulai'],
            'supplier_id' => [
                Rule::requiredIf($sewa),
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($current) {
                    $supplier = Supplier::withTrashed()->find($value);

                    if (! $supplier) {
                        $fail('Supplier tidak ditemukan.');
                    } elseif ($supplier->trashed()
                        && ! ($current && (int) $current->supplier_id === (int) $value)) {
                        // Income lama boleh tetap memakai supplier yang sudah dihapus, tapi tidak untuk pilihan baru.
                        $fail('Supplier sudah dihapus. Pilih supplier lain.');
                    }
                },
            ],
            'product_id' => [
                Rule::requiredIf($sewa),
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($current) {
                    $product = Product::withTrashed()->find($value);

                    if (! $product) {
                        $fail('Produk tidak ditemukan.');
                    } elseif ($product->trashed()
                        && ! ($current && (int) $current->product_id === (int) $value)) {
                        $fail('Produk sudah dihapus. Pilih produk lain.');
                    }
                },
                function ($attribute, $value, $fail) use ($current) {
                    $supplierId = $this->input('supplier_id');
                    if (! $supplierId || ! $value) {
                        return;
                    }

                    // Data lama yang tidak diubah tetap lolos walau relasi supplier-produk sudah berubah.
                    if ($current
                        && (int) $current->product_id === (int) $value
                        && (int) $current->supplier_id === (int) $supplierId) {
                        return;
                    }

                    $milikSupplier = DB::table('product_supplier')
                        ->where('product_id', $value)
                        ->where('supplier_id', $supplierId)
                        ->exists();

                    if (! $milikSupplier) {
                        $fail('Produk tidak terdaftar pada supplier yang dipilih.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'other_income_category_id.required' => 'Kategori wajib dipilih.',
            'other_income_category_id.integer' => 'Kategori tidak valid.',
            'jumlah.required' => 'Jumlah wajib diisi.',
            'jumlah.numeric' => 'Jumlah harus berupa angka.',
            'jumlah.min' => 'Jumlah minimal 1.',
            'jumlah.max' => 'Jumlah terlalu besar.',
            'keterangan.max' => 'Keterangan maksimal 1000 karakter.',
            'outlet_id.exists' => 'Outlet tidak ditemukan.',
            'sewa_mulai.required' => 'Tanggal mulai sewa wajib diisi.',
            'sewa_mulai.date' => 'Tanggal mulai sewa tidak valid.',
            'sewa_selesai.required' => 'Tanggal selesai sewa wajib diisi.',
            'sewa_selesai.date' => 'Tanggal selesai sewa tidak valid.',
            'sewa_selesai.after_or_equal' => 'Tanggal selesai sewa tidak boleh sebelum tanggal mulai.',
            'supplier_id.required' => 'Supplier wajib dipilih.',
            'product_id.required' => 'Produk wajib dipilih.',

        ];
    }
}