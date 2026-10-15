<?php

namespace App\Exports;

use App\Models\OutletPurchase;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Dokumen "PURCHASE ORDER OUTLET" (POUT). Meniru PembelianSingleExport (Dokumen PO):
 * kop dari settings.json, tabel No / Kode / Nama / Qty (+konversi) / Satuan tanpa harga,
 * ditambah baris Outlet, dan tanda tangan Staff Outlet / Manager.
 */
class OutletPurchaseSingleExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithDrawings, WithCustomStartCell, WithProperties
{
    use Exportable;

    /** Baris judul tabel (satu baris lebih bawah dari PO karena ada baris Outlet). */
    private const HEADER_ROW = 15;

    protected OutletPurchase $purchase;
    protected array $settings;
    private int $no = 0;

    public function __construct(OutletPurchase $purchase, array $settings = [])
    {
        $this->purchase = $purchase;
        $this->settings = $settings;
    }

    public function collection()
    {
        // Diurutkan menurut nama produk, sama seperti Dokumen PO.
        return $this->purchase->items
            ->sortBy(fn ($item) => mb_strtolower($item->product->name ?? ''))
            ->values();
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Barang',
            'Nama Barang',
            '', // kolom E (bagian merge nama produk)
            '', // kolom F (bagian merge nama produk)
            'Qty',
            'Satuan',
        ];
    }

    public function map($item): array
    {
        $this->no++;
        $product = $item->product;
        $qty = (float) $item->qty;
        $qtyText = $qty == (int) $qty ? (string) (int) $qty : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
        $k = $product ? $product->konversiDisplay($item->qty) : null;

        return [
            $this->no,
            $product?->code ? "'" . $product->code : '',
            $product?->name ?? '',
            '',
            '',
            $qtyText . ($k && $k !== '-' ? " ({$k})" : ''),
            $product?->satuan ?? 'PCS',
        ];
    }

    public function startCell(): string
    {
        return 'B' . self::HEADER_ROW;
    }

    public function styles(Worksheet $sheet)
    {
        $h = self::HEADER_ROW;
        $first = $h + 1;

        $companyName = $this->settings['name'] ?? 'NAMA PERUSAHAAN';
        $address     = $this->settings['address'] ?? 'ALAMAT';
        $phone       = $this->settings['telp'] ?? '';
        $email       = $this->settings['email'] ?? '';
        $website     = $this->settings['website'] ?? '';
        $contactInfo = trim("$phone | $email | $website", ' |');

        $sheet->getRowDimension(1)->setRowHeight(50);

        $sheet->setCellValue('D2', $companyName);
        $sheet->mergeCells('D2:H2');
        $sheet->getStyle('D2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('D3', $address);
        $sheet->mergeCells('D3:H3');

        $sheet->setCellValue('D4', $contactInfo);
        $sheet->mergeCells('D4:H4');
        $sheet->getStyle('D3:H4')->applyFromArray([
            'font' => ['size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->getRowDimension(5)->setRowHeight(20);

        $sheet->mergeCells('B6:H6');
        $sheet->getStyle('B6:H6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);

        $sheet->setCellValue('B8', 'PURCHASE ORDER OUTLET');
        $sheet->mergeCells('B8:H8');
        $sheet->getStyle('B8')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $tanggal = $this->purchase->purchase_date ?? $this->purchase->created_at;

        $sheet->setCellValue('C10', 'Kode POUT :');
        $sheet->setCellValue('D10', $this->purchase->code);
        $sheet->setCellValue('C11', 'Tanggal :');
        $sheet->setCellValue('D11', $tanggal ? Carbon::parse($tanggal)->isoFormat('DD MMMM YYYY') : '');
        $sheet->setCellValue('C12', 'Outlet :');
        $sheet->setCellValue('D12', $this->purchase->outlet->name ?? '');
        $sheet->setCellValue('C13', 'Nama Supplier :');
        $sheet->setCellValue('D13', $this->purchase->supplier->name ?? '');
        $sheet->getStyle('C10:D13')->applyFromArray([
            'font' => ['bold' => false, 'size' => 12],
        ]);

        $sheet->mergeCells("D{$h}:F{$h}");

        $sheet->getStyle("B{$h}:H{$h}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);

        $highestRow = $sheet->getHighestRow();
        if ($highestRow > $h) {
            $sheet->getStyle("B{$first}:H{$highestRow}")
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("B{$first}:H{$highestRow}")->applyFromArray([
                'font' => ['size' => 12],
            ]);
            $sheet->getStyle("C{$first}:C{$highestRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);

            for ($r = $first; $r <= $highestRow; $r++) {
                $sheet->mergeCells("D{$r}:F{$r}");
                $sheet->getStyle("D{$r}:F{$r}")->getAlignment()->setWrapText(true);

                // Tinggi baris otomatis menurut panjang nama barang (sama seperti Dokumen PO).
                $text = (string) $sheet->getCell("D{$r}")->getValue();
                $charsPerLine = 65;
                $lines = max(1, (int) ceil(mb_strlen($text) / $charsPerLine));
                $sheet->getRowDimension($r)->setRowHeight($lines * 15);
            }

            $sheet->getStyle("B{$first}:H{$highestRow}")
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);
        }

        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(46);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(20);

        if ($highestRow > $h) {
            $sheet->getStyle("G{$first}:G{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H{$first}:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $row = $highestRow + 3;
        $sheet->mergeCells('B' . $row . ':D' . $row);
        $sheet->mergeCells('G' . $row . ':H' . $row);
        $sheet->setCellValue('B' . $row, 'Dibuat Oleh');
        $sheet->setCellValue('G' . $row, 'Disetujui Oleh');
        $sheet->getStyle('B' . $row . ':H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
        $sheet->mergeCells('B' . $row . ':D' . $row);
        $sheet->mergeCells('G' . $row . ':H' . $row);
        $sheet->setCellValue('B' . $row, 'Staff Outlet');
        $sheet->setCellValue('G' . $row, 'Manager');
        $sheet->getStyle('B' . $row . ':D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G' . $row . ':H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row += 5;
        $sheet->mergeCells('B' . $row . ':D' . $row);
        $sheet->mergeCells('G' . $row . ':H' . $row);
        $sheet->setCellValue('B' . $row, '');
        $sheet->setCellValue('G' . $row, '');
        $sheet->getStyle('B' . ($row - 1) . ':H' . ($row - 1))
            ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');

        $logoPath = $this->settings['logo'] ?? null;
        if ($logoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
            $drawing->setPath(\Illuminate\Support\Facades\Storage::disk('public')->path($logoPath));
        } else {
            $drawing->setPath(public_path('img/logo.jpeg'));
        }

        $drawing->setHeight(80);
        $drawing->setCoordinates('B2');

        return [$drawing];
    }

    public function properties(): array
    {
        return [
            'creator' => config('app.name'),
            'title' => 'Purchase Order Outlet',
            'description' => 'POUT ' . $this->purchase->code,
        ];
    }
}