<?php

namespace App\Exports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class SuppliersExport implements FromQuery, WithHeadings, WithMapping
{
    private const DAY_MAP = [
        1 => 'senin',
        2 => 'selasa',
        3 => 'rabu',
        4 => 'kamis',
        5 => 'jumat',
        6 => 'sabtu',
        7 => 'minggu',
    ];

    public function __construct(private bool $templateOnly = false) {}

    /** Dibaca per potongan (chunk), bukan Supplier::all(). */
    public function query()
    {
        $query = Supplier::query()->orderBy('id');

        return $this->templateOnly ? $query->whereRaw('1 = 0') : $query;
    }

    public function headings(): array
    {
        return ['kode_supplier', 'nama', 'pic', 'alamat', 'no_telp', 'interval_minggu', 'hari_order'];
    }

    public function map($row): array
    {
        $hariOrder = null;
        if (! empty($row->deadline_days)) {
            $hariOrder = implode(',', array_map(
                fn ($d) => self::DAY_MAP[(int) $d] ?? $d,
                $row->deadline_days
            ));
        }

        return [
            $row->kode_supplier,
            $row->name,
            $row->pic_supplier,
            $row->alamat,
            $row->no_telp,
            $row->deadline_interval_weeks,
            $hariOrder,
        ];
    }
}