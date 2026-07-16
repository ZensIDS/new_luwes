<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class StockOpnameTemplateExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithDrawings, WithCustomStartCell, WithProperties
{
    use Exportable;

    protected Collection $stocks;

    protected string $date;

    protected array $settings;

    public function __construct(Collection $stocks, string $date, array $settings = [])
    {
        $this->stocks   = $stocks;
        $this->date     = $date;
        $this->settings = $settings;
    }

    public function collection(): Collection
    {
        return $this->stocks;
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode Barang',
            'Nama Barang',
            'Batch/SKU',
            'Expired',
            'Satuan',
            'Stok Sistem',
            'Stok Fisik',
            'Selisih',
        ];
    }

    public function map($stock): array
    {
        static $no = 0;
        $no++;
        $systemQty   = $stock->qty ?? 0;
        $konvDisplay = $stock->product->konversiDisplay($systemQty);
        return [
            $no,
            $stock->product->code ? "'".$stock->product->code : '-',
            $stock->product->name ?? '-',
            $stock->sku ? "'".$stock->sku : '-',
            $stock->expired_at
                ? Carbon::parse($stock->expired_at)->format('d/m/Y')
                : '-',
            $stock->product->satuan ?? 'PCS',
            $systemQty.($konvDisplay && $konvDisplay !== '-' ? " ({$konvDisplay})" : ''),
            '', // Stok Fisik
            '', // Selisih
        ];
    }

    public function startCell(): string
    {
        return 'B16';
    }

    public function styles(Worksheet $sheet): void
    {
        $companyName = $this->settings['name'] ?? 'NAMA PERUSAHAAN';
        $address     = $this->settings['address'] ?? 'ALAMAT';
        $phone       = $this->settings['telp'] ?? '';
        $email       = $this->settings['email'] ?? '';
        $website     = $this->settings['website'] ?? '';
        $contactInfo = trim("$phone | $email | $website", ' |');
    
        $sheet->getRowDimension(1)->setRowHeight(50);
    
        $sheet->setCellValue('D2', $companyName);
        $sheet->mergeCells('D2:J2');
        $sheet->getStyle('D2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 18],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    
        $sheet->setCellValue('D3', $address);
        $sheet->mergeCells('D3:J3');
        $sheet->setCellValue('D4', $contactInfo);
        $sheet->mergeCells('D4:J4');
        $sheet->getStyle('D3:D4')->applyFromArray([
            'font'      => ['bold' => false, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    
        $sheet->getRowDimension(5)->setRowHeight(20);
    
        $sheet->mergeCells('B6:J6');
        $sheet->getStyle('B6:J6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);
    
        $sheet->setCellValue('B8', 'TEMPLATE STOCK OPNAME');
        $sheet->mergeCells('B8:J8');
        $sheet->getStyle('B8')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    
        $sheet->setCellValue('B10', 'Tanggal :');
        $sheet->setCellValue('D10', Carbon::parse($this->date)->isoFormat('DD MMMM YYYY'));
        $sheet->getStyle('B10')->getFont()->setBold(true);
        $sheet->setCellValue('B11', 'Nama :');
        $sheet->setCellValue('D11', ' ');
        $sheet->getStyle('B11')->getFont()->setBold(true);
        $sheet->setCellValue('B13', 'Detail Stok');
        $sheet->getStyle('B13')->getFont()->setBold(true);
        $sheet->getStyle('B10:D13')->applyFromArray([
            'font' => ['bold' => false, 'size' => 12],
        ]);
    
        $sheet->getStyle('B16:J16')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);
        $sheet->getRowDimension(16)->setRowHeight(28);
    
        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 16) {
            $sheet->getStyle('B17:J'.$highestRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
            $sheet->getStyle('C17:C'.$highestRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            $sheet->getStyle('E17:E'.$highestRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
    
            for ($r = 17; $r <= $highestRow; $r++) {
                $sheet->getStyle('D'.$r)->getAlignment()->setWrapText(true);
                $sheet->getStyle('H'.$r)->getAlignment()->setWrapText(true); // G → H
            
                $textNama  = (string) $sheet->getCell('D'.$r)->getValue();
                $textStok  = (string) $sheet->getCell('H'.$r)->getValue(); // G → H
            
                $linesNama = max(1, ceil(mb_strlen($textNama) / 24));
                $linesStok = max(1, ceil(mb_strlen($textStok) / 16));
            
                $lines = max($linesNama, $linesStok);
                $sheet->getRowDimension($r)->setRowHeight($lines * 15);
            }
    
            $sheet->getStyle('B17:J'.$highestRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle('B17:J'.$highestRow)->applyFromArray([
                'font' => ['size' => 12],
            ]);
        }
    
        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(24);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(7); // Stok Sistem
        $sheet->getColumnDimension('H')->setWidth(16); // Stok Fisik — dilebarkan dari 12 ke 16
        $sheet->getColumnDimension('I')->setWidth(10); // Selisih
        $sheet->getColumnDimension('J')->setWidth(9);  // (kosong/cadangan)
    
        $sheet->getStyle('H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // H pakai RIGHT biar wrap text tidak terpotong ke kiri
        foreach (['G', 'I', 'J'] as $col) {
            $sheet->getStyle($col)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    public function drawings(): array
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
            'creator'     => config('app.name'),
            'title'       => 'Template Stock Opname',
            'description' => 'Template Stock Opname '.$this->date,
        ];
    }
}
