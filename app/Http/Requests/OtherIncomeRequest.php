<?php

namespace App\Http\Requests;

use App\Models\OtherIncome;
use App\Models\OtherIncomeCategory;
use App\Support\IndonesianNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Input Other Income MANUAL (tambah / ubah).
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
    }

    public function rules(): array
    {
        /** @var OtherIncome|null $current */
        $current = $this->route('otherIncome');

        return [
            'tanggal' => ['required', 'date'],
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
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date' => 'Tanggal tidak valid.',
            'other_income_category_id.required' => 'Kategori wajib dipilih.',
            'other_income_category_id.integer' => 'Kategori tidak valid.',
            'jumlah.required' => 'Jumlah wajib diisi.',
            'jumlah.numeric' => 'Jumlah harus berupa angka.',
            'jumlah.min' => 'Jumlah minimal 1.',
            'jumlah.max' => 'Jumlah terlalu besar.',
            'keterangan.max' => 'Keterangan maksimal 1000 karakter.',
            'outlet_id.exists' => 'Outlet tidak ditemukan.',
        ];
    }
}