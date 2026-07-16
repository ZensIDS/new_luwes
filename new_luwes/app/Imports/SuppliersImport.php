<?php

namespace App\Imports;

use App\Models\Supplier;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class SuppliersImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    private const DAY_MAP = [
        'senin'   => 1,
        'selasa'  => 2,
        'rabu'    => 3,
        'kamis'   => 4,
        'jumat'   => 5,
        'sabtu'   => 6,
        'minggu'  => 7,
    ];

    public function model(array $row)
    {
        $deadlineDays = null;
        if (! empty($row['hari_order'])) {
            $names = array_map('trim', explode(',', strtolower($row['hari_order'])));
            $deadlineDays = array_values(array_filter(array_map(
                fn ($name) => self::DAY_MAP[$name] ?? null,
                $names
            )));
        }

        return Supplier::updateOrCreate(
            ['kode_supplier' => $row['kode_supplier']],
            [
                'name'                    => $row['nama'],
                'pic_supplier'            => $row['pic'] ?? null,
                'alamat'                  => $row['alamat'] ?? null,
                'no_telp'                 => $row['no_telp'] ?? null,
                'deadline_interval_weeks' => ! empty($row['interval_minggu']) ? (int) $row['interval_minggu'] : null,
                'deadline_days'           => $deadlineDays ?: null,
            ]
        );
    }
}
