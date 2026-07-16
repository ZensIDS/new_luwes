<?php

namespace App\Imports;

use App\Imports\ReadFilters\HeadingAndChunkReadFilter;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductImportFailure;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductsImport
{
    public function countDataRows(string $absoluteFilePath): int
    {
        $reader = IOFactory::createReaderForFile($absoluteFilePath);
        $info = $reader->listWorksheetInfo($absoluteFilePath);
        $totalRows = (int) ($info[0]['totalRows'] ?? 0);

        return max($totalRows - 1, 0);
    }

    public function processChunk(ProductImport $productImport, int $startRow, int $chunkSize): array
    {
        $rows = $this->loadChunkRows($productImport->stored_file_path, $startRow, $chunkSize);

        if ($rows->isEmpty()) {
            return [
                'processed_rows' => 0,
                'successful_rows' => 0,
                'failed_rows' => 0,
            ];
        }

        return $this->processRows($productImport, $rows);
    }

    private function loadChunkRows(string $storedFilePath, int $startRow, int $chunkSize): Collection
    {
        $absoluteFilePath = Storage::disk('local')->path($storedFilePath);
        $reader = IOFactory::createReaderForFile($absoluteFilePath);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new HeadingAndChunkReadFilter(1, $startRow, $chunkSize));

        $spreadsheet = $reader->load($absoluteFilePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $headingCells = $sheet->rangeToArray("A1:{$highestColumn}1", null, true, false, false)[0] ?? [];
        $headings = HeadingRowFormatter::format($headingCells);
        $endRow = $startRow + $chunkSize - 1;
        $rows = collect();

        for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
            $cellValues = $sheet->rangeToArray("A{$rowNumber}:{$highestColumn}{$rowNumber}", null, true, false, false)[0] ?? [];

            if ($this->isEmptySpreadsheetRow($cellValues)) {
                continue;
            }

            $row = [];

            foreach ($headings as $index => $heading) {
                if ($heading === null || $heading === '') {
                    continue;
                }

                $row[$heading] = $cellValues[$index] ?? null;
            }

            $rows->push([
                'row_number' => $rowNumber,
                'data' => $row,
            ]);
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    private function processRows(ProductImport $productImport, Collection $rows): array
    {
        $rows = $rows
            ->map(function (array $rowEntry) {
                return [
                    'row_number' => $rowEntry['row_number'],
                    'data' => $this->normalizeRow($rowEntry['data']),
                ];
            })
            ->values();

        $categoryData = $this->resolveCategoryData($rows);
        $supplierData = $this->resolveSupplierData($rows);
        $productCodeCounts = $this->resolveDuplicateProductCodes($rows);

        $validRows = [];
        $failures = [];

        foreach ($rows as $rowEntry) {
            $rowNumber = $rowEntry['row_number'];
            $row = $rowEntry['data'];

            if (empty($row['kode'])) {
                $failures[] = $this->buildFailure($productImport->id, $rowNumber, $row, 'Kode wajib diisi.');
                continue;
            }

            if (empty($row['nama'])) {
                $failures[] = $this->buildFailure($productImport->id, $rowNumber, $row, 'Nama produk wajib diisi.');
                continue;
            }

            if (isset($productCodeCounts[$row['kode']])) {
                $failures[] = $this->buildFailure(
                    $productImport->id,
                    $rowNumber,
                    $row,
                    'Kode produk duplikat di database, baris ini dilewati.'
                );
                continue;
            }

            if ($row['kategori'] && isset($categoryData['ambiguous_names'][$row['kategori']])) {
                $failures[] = $this->buildFailure(
                    $productImport->id,
                    $rowNumber,
                    $row,
                    'Nama kategori duplikat di database, baris ini dilewati.'
                );
                continue;
            }

            $supplierResolution = $this->resolveSupplierIdsForRow($row, $supplierData);

            if ($supplierResolution['error']) {
                $failures[] = $this->buildFailure($productImport->id, $rowNumber, $row, $supplierResolution['error']);
                continue;
            }

            $validRows[] = [
                'row_number' => $rowNumber,
                'data' => $row,
                'supplier_ids' => $supplierResolution['ids'],
            ];
        }

        $this->storeFailures($failures);

        if ($validRows === []) {
            return [
                'processed_rows' => $rows->count(),
                'successful_rows' => 0,
                'failed_rows' => count($failures),
            ];
        }

        $validRows = collect($validRows);

        DB::transaction(function () use ($validRows, $categoryData) {
            $codes = $validRows->map(fn (array $row) => $row['data']['kode'])->unique()->values();

            $existingProducts = Product::withTrashed()
                ->whereIn('code', $codes)
                ->get(['id', 'code', 'deleted_at'])
                ->keyBy('code');

            $trashedProductIds = $existingProducts
                ->filter(fn (Product $product) => $product->deleted_at !== null)
                ->pluck('id');

            if ($trashedProductIds->isNotEmpty()) {
                Product::withTrashed()
                    ->whereIn('id', $trashedProductIds)
                    ->restore();
            }

            $now = now();
            $insertPayload = [];
            $updatePayload = [];
            $supplierMap = [];

            foreach ($validRows as $rowEntry) {
                $row = $rowEntry['data'];
                $productData = [
                    'code' => $row['kode'],
                    'name' => $row['nama'],
                    'category_id' => $row['kategori'] ? ($categoryData['ids_by_name'][$row['kategori']] ?? null) : null,
                    'brand' => $row['brand'],
                    'model' => $row['model'],
                    'warna' => $row['warna'],
                    'ukuran' => $row['ukuran'],
                    'satuan' => $row['satuan'],
                    'min_stock' => $this->normalizeInteger($row['min_stock'], 0),
                    'lokasi' => $row['lokasi'],
                    'harga_beli' => $this->normalizeInteger($row['harga_beli'], 0),
                    'desc' => $row['deskripsi'],
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];

                if ($existingProducts->has($row['kode'])) {
                    $productData['id'] = $existingProducts[$row['kode']]->id;
                    $updatePayload[$row['kode']] = $productData;
                } else {
                    $productData['created_at'] = $now;
                    $insertPayload[$row['kode']] = $productData;
                }

                if ($rowEntry['supplier_ids'] !== null) {
                    $supplierMap[$row['kode']] = $rowEntry['supplier_ids'];
                }
            }

            if ($insertPayload !== []) {
                Product::insert(array_values($insertPayload));
            }

            if ($updatePayload !== []) {
                Product::upsert(
                    array_values($updatePayload),
                    ['id'],
                    [
                        'name',
                        'category_id',
                        'brand',
                        'model',
                        'warna',
                        'ukuran',
                        'satuan',
                        'min_stock',
                        'lokasi',
                        'harga_beli',
                        'desc',
                        'updated_at',
                        'deleted_at',
                    ]
                );
            }

            $this->syncSuppliers($supplierMap);
        });

        return [
            'processed_rows' => $rows->count(),
            'successful_rows' => $validRows->count(),
            'failed_rows' => count($failures),
        ];
    }

    private function resolveCategoryData(Collection $rows): array
    {
        $categoryNames = $rows
            ->map(fn (array $rowEntry) => $rowEntry['data']['kategori'])
            ->filter()
            ->unique()
            ->values();

        if ($categoryNames->isEmpty()) {
            return [
                'ids_by_name' => [],
                'ambiguous_names' => [],
            ];
        }

        $ambiguousNames = Category::withTrashed()
            ->select('name')
            ->whereIn('name', $categoryNames)
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name')
            ->flip()
            ->all();

        $validNames = $categoryNames
            ->reject(fn (string $name) => isset($ambiguousNames[$name]))
            ->values();

        if ($validNames->isNotEmpty()) {
            Category::withTrashed()
                ->whereIn('name', $validNames)
                ->whereNotNull('deleted_at')
                ->restore();

            $existingNames = Category::whereIn('name', $validNames)
                ->pluck('name')
                ->all();

            $missingNames = $validNames
                ->diff($existingNames)
                ->values();

            if ($missingNames->isNotEmpty()) {
                $timestamp = now();
                Category::insert(
                    $missingNames
                        ->map(fn (string $name) => [
                            'name' => $name,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ])
                        ->all()
                );
            }
        }

        return [
            'ids_by_name' => $validNames->isEmpty()
                ? []
                : Category::whereIn('name', $validNames)->pluck('id', 'name')->all(),
            'ambiguous_names' => $ambiguousNames,
        ];
    }

    private function resolveSupplierData(Collection $rows): array
    {
        $supplierNames = $rows
            ->map(fn (array $rowEntry) => $rowEntry['data']['supplier'])
            ->filter()
            ->flatMap(fn (string $supplierList) => collect(explode(',', $supplierList)))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->values();

        if ($supplierNames->isEmpty()) {
            return [
                'ids_by_name' => [],
                'ambiguous_names' => [],
            ];
        }

        $ambiguousNames = Supplier::withTrashed()
            ->select('name')
            ->whereIn('name', $supplierNames)
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name')
            ->flip()
            ->all();

        $validNames = $supplierNames
            ->reject(fn (string $name) => isset($ambiguousNames[$name]))
            ->values();

        if ($validNames->isNotEmpty()) {
            Supplier::withTrashed()
                ->whereIn('name', $validNames)
                ->whereNotNull('deleted_at')
                ->restore();

            $existingNames = Supplier::whereIn('name', $validNames)
                ->pluck('name')
                ->all();

            $missingNames = $validNames
                ->diff($existingNames)
                ->values();

            if ($missingNames->isNotEmpty()) {
                $timestamp = now();
                $supplierCodes = $this->generateSupplierCodes($missingNames->count());

                Supplier::insert(
                    $missingNames
                        ->values()
                        ->map(fn (string $name, int $index) => [
                            'kode_supplier' => $supplierCodes[$index],
                            'name' => $name,
                            'alamat' => '-',
                            'no_telp' => '-',
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ])
                        ->all()
                );
            }
        }

        return [
            'ids_by_name' => $validNames->isEmpty()
                ? []
                : Supplier::whereIn('name', $validNames)->pluck('id', 'name')->all(),
            'ambiguous_names' => $ambiguousNames,
        ];
    }

    private function resolveDuplicateProductCodes(Collection $rows): array
    {
        $codes = $rows
            ->map(fn (array $rowEntry) => $rowEntry['data']['kode'])
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            return [];
        }

        return Product::withTrashed()
            ->select('code')
            ->whereIn('code', $codes)
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('code')
            ->flip()
            ->all();
    }

    private function resolveSupplierIdsForRow(array $row, array $supplierData): array
    {
        if ($row['supplier'] === null) {
            return ['ids' => null, 'error' => null];
        }

        $supplierNames = collect(explode(',', $row['supplier']))
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->values();

        $ambiguousNames = $supplierNames
            ->filter(fn (string $name) => isset($supplierData['ambiguous_names'][$name]))
            ->values();

        if ($ambiguousNames->isNotEmpty()) {
            return [
                'ids' => null,
                'error' => 'Nama supplier duplikat di database: ' . $ambiguousNames->implode(', '),
            ];
        }

        $supplierIds = $supplierNames
            ->map(function (string $name) use ($supplierData) {
                return $supplierData['ids_by_name'][$name] ?? null;
            })
            ->filter()
            ->values()
            ->all();

        return ['ids' => $supplierIds, 'error' => null];
    }

    private function syncSuppliers(array $supplierMap): void
    {
        if ($supplierMap === []) {
            return;
        }

        $productsByCode = Product::whereIn('code', array_keys($supplierMap))
            ->get(['id', 'code'])
            ->keyBy('code');

        $productIdsToReplace = [];
        $pivotRows = [];
        $timestamp = now();

        foreach ($supplierMap as $code => $supplierIds) {
            $product = $productsByCode->get($code);

            if (! $product) {
                continue;
            }

            $productIdsToReplace[] = $product->id;

            foreach (array_unique($supplierIds) as $supplierId) {
                $pivotRows[] = [
                    'product_id' => $product->id,
                    'supplier_id' => $supplierId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        if ($productIdsToReplace === []) {
            return;
        }

        DB::table('product_supplier')
            ->whereIn('product_id', array_unique($productIdsToReplace))
            ->delete();

        foreach (array_chunk($pivotRows, 1000) as $chunk) {
            DB::table('product_supplier')->insert($chunk);
        }
    }

    private function storeFailures(array $failures): void
    {
        if ($failures === []) {
            return;
        }

        foreach (array_chunk($failures, 200) as $chunk) {
            ProductImportFailure::insert($chunk);
        }
    }

    private function buildFailure(int $productImportId, int $rowNumber, array $row, string $message): array
    {
        $timestamp = now();

        return [
            'product_import_id' => $productImportId,
            'row_number' => $rowNumber,
            'product_code' => $row['kode'] ?? null,
            'message' => $message,
            'row_data' => json_encode($row),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    private function generateSupplierCodes(int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $lastCode = Supplier::withTrashed()
            ->where('kode_supplier', 'like', 'S%')
            ->orderByRaw('CAST(SUBSTRING(kode_supplier, 2) AS UNSIGNED) DESC')
            ->value('kode_supplier');

        $nextNumber = $lastCode ? ((int) substr($lastCode, 1)) + 1 : 1;
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = 'S' . str_pad((string) ($nextNumber + $i), 5, '0', STR_PAD_LEFT);
        }

        return $codes;
    }

    private function normalizeRow(array $row): array
    {
        return [
            'kode' => $this->normalizeString($row['kode'] ?? null),
            'nama' => $this->normalizeString($row['nama'] ?? null),
            'kategori' => $this->normalizeString($row['kategori'] ?? null),
            'brand' => $this->normalizeString($row['brand'] ?? null),
            'model' => $this->normalizeString($row['model'] ?? null),
            'warna' => $this->normalizeString($row['warna'] ?? null),
            'ukuran' => $this->normalizeString($row['ukuran'] ?? null),
            'satuan' => $this->normalizeString($row['satuan'] ?? null),
            'min_stock' => $row['min_stock'] ?? null,
            'lokasi' => $this->normalizeString($row['lokasi'] ?? null),
            'harga_beli' => $row['harga_beli'] ?? null,
            'deskripsi' => $this->normalizeString($row['deskripsi'] ?? null),
            'supplier' => $this->normalizeString($row['supplier'] ?? null),
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeInteger(mixed $value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $normalized = preg_replace('/[^\d-]/', '', (string) $value);

        if ($normalized === null || $normalized === '' || $normalized === '-') {
            return $default;
        }

        return (int) $normalized;
    }

    private function isEmptySpreadsheetRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
