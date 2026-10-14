<?php

namespace App\Support;

use App\Models\OtherIncome;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filter daftar Other Income yang dipakai bersama halaman daftar dan Export Excel (E1),
 * supaya total di Excel selalu sama dengan total di halaman.
 */
final class OtherIncomeFilter
{
    private function __construct(
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?int $categoryId,
        public readonly ?string $outlet, // 'umum' | id outlet (string angka) | null
        public readonly bool $scoped,
        private readonly ?User $user,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $user = $request->user();
        $scoped = $user && in_array($user->role, OtherIncome::OUTLET_SCOPED_ROLES, true);

        $request->validate([
            'category_id' => ['nullable', 'integer'],
            'outlet_id' => ['nullable', 'string', 'max:20'],
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date', 'after_or_equal:dari'],
        ], [
            'dari.date' => 'Tanggal awal tidak valid.',
            'sampai.date' => 'Tanggal akhir tidak valid.',
            'sampai.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        // Pertama kali dibuka: bulan berjalan. Dikosongkan (parameter ada tapi kosong) = tanpa batas tanggal.
        $firstVisit = ! $request->has('dari') && ! $request->has('sampai');

        $from = $firstVisit
            ? now()->startOfMonth()->toDateString()
            : ($request->filled('dari') ? (string) $request->input('dari') : null);
        $to = $firstVisit
            ? now()->toDateString()
            : ($request->filled('sampai') ? (string) $request->input('sampai') : null);

        return new self(
            $from,
            $to,
            $request->filled('category_id') ? (int) $request->input('category_id') : null,
            $scoped ? null : ($request->filled('outlet_id') ? (string) $request->input('outlet_id') : null),
            (bool) $scoped,
            $user,
        );
    }

    /** Query income sesuai filter dan hak akses (K11). */
    public function query(): Builder
    {
        return OtherIncome::query()
            ->visibleTo($this->user)
            ->when($this->categoryId, fn ($q) => $q->where('other_income_category_id', $this->categoryId))
            ->when($this->outlet === 'umum', fn ($q) => $q->whereNull('outlet_id'))
            ->when($this->outlet !== null && ctype_digit($this->outlet), fn ($q) => $q->where('outlet_id', (int) $this->outlet))
            ->when($this->from, fn ($q) => $q->where('tanggal', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where('tanggal', '<=', $this->to));
    }

    /** Bentuk yang dipakai view (`$filters`). */
    public function toArray(): array
    {
        return [
            'category_id' => $this->categoryId,
            'outlet_id' => $this->outlet,
            'dari' => $this->from,
            'sampai' => $this->to,
        ];
    }
}