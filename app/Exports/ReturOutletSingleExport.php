<?php

namespace App\Exports;

use App\Models\RefundPembelian;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReturOutletSingleExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithDrawings, WithCustomStartCell, WithProperties
{
    use Exportable;

    protected RefundPembelian $retur;
    protected array $settings;

    public function __construct(RefundPembelian $retur, array $settings = [])
    {
        $this->retur    = $retur;
        $this->settings = $settings;
    }

    public function collection()
    {
        return $this->retur->refundPembelianItems()->with('product', 'stock')->get();
    }

    public function headings(): array
    {
        return ['No', 'Kode Barang', 'Nama Barang', 'Batch/SKU', 'Expired', 'Qty', 'Satuan', 'Alasan'];
    }

    public function map($item): array
    {
        static $no = 0;
        $no++;

        $k = $item->product?->konversiDisplay($item->qty) ?? '-';

        return [
            $no,
            $item->product->code ?? '-',
            $item->product->name ?? '-',
            $item->sku ?? '-',
            $item->stock?->expired_at
                ? Carbon::parse($item->stock->expired_at)->isoFormat('DD MMMM YYYY')
                : '-',
            $item->qty.($k && $k !== '-' ? " ({$k})" : ''),
            $item->product->satuan ?? 'PCS',
            $item->alasan,
        ];
    }

    public function startCell(): string
    {
        return 'B17';
    }

    public function styles(Worksheet $sheet)
    {
        $companyName = $this->settings['name'] ?? 'NAMA PERUSAHAAN';
        $address     = $this->settings['address'] ?? 'ALAMAT';
        $phone       = $this->settings['telp'] ?? '';
        $email       = $this->settings['email'] ?? '';
        $website     = $this->settings['website'] ?? '';
        $contactInfo = trim("$phone | $email | $website", ' |');

        $sheet->getRowDimension(1)->setRowHeight(50);

        $sheet->setCellValue('D2', $companyName);
        $sheet->mergeCells('D2:I2');
        $sheet->getStyle('D2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('D3', $address);
        $sheet->mergeCells('D3:I3');
        $sheet->setCellValue('D4', $contactInfo);
        $sheet->mergeCells('D4:I4');
        $sheet->getStyle('D3:D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension(5)->setRowHeight(20);

        $sheet->mergeCells('B6:I6');
        $sheet->getStyle('B6:I6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);

        $sheet->setCellValue('B8', 'DOKUMEN RETUR OUTLET (OUTLET → GUDANG)');
        $sheet->mergeCells('B8:I8');
        $sheet->getStyle('B8')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('C10', 'Kode Retur :');
        $sheet->setCellValue('D10', $this->retur->code);
        $sheet->setCellValue('C11', 'Tanggal :');
        $sheet->setCellValue('D11', Carbon::parse($this->retur->tanggal)->isoFormat('DD MMMM YYYY'));
        $sheet->setCellValue('C12', 'Outlet :');
        $sheet->setCellValue('D12', $this->retur->outlet->name ?? '-');
        $sheet->setCellValue('C13', 'No. DO :');
        $sheet->setCellValue('D13', $this->retur->deliveryOrder->code ?? '-');
        $sheet->setCellValue('C14', 'Operator :');
        $sheet->setCellValue('D14', $this->retur->user->name ?? '-');
        $sheet->getStyle('C10:C14')->getFont()->setBold(true);

        $sheet->setCellValue('C16', 'Detail Item Retur');
        $sheet->getStyle('C16')->getFont()->setBold(true);

        $sheet->getStyle('B17:I17')->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);

        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 17) {
            $sheet->getStyle('B18:I'.$highestRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(14);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(25);

        $sheet->getStyle('B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = $highestRow + 4;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('E'.$row.':F'.$row);
        $sheet->mergeCells('H'.$row.':I'.$row);
        $sheet->setCellValue('B'.$row, 'Dibuat Oleh');
        $sheet->setCellValue('E'.$row, 'Diperiksa Oleh');
        $sheet->setCellValue('H'.$row, 'Disetujui Oleh');
        $sheet->getStyle('B'.$row.':I'.$row)->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row++;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('E'.$row.':F'.$row);
        $sheet->mergeCells('H'.$row.':I'.$row);
        $sheet->setCellValue('B'.$row, 'Staff Outlet');
        $sheet->setCellValue('E'.$row, 'Supervisor');
        $sheet->setCellValue('H'.$row, 'Manager');
        $sheet->getStyle('B'.$row.':I'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row += 5;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('E'.$row.':F'.$row);
        $sheet->mergeCells('H'.$row.':I'.$row);
        $sheet->setCellValue('B'.$row, 'Nama');
        $sheet->setCellValue('E'.$row, 'Nama');
        $sheet->setCellValue('H'.$row, 'Nama');
        $sheet->getStyle('B'.$row.':I'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B'.($row - 1).':I'.($row - 1))
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
            'creator'     => config('app.name'),
            'title'       => 'Dokumen Retur Outlet',
            'description' => 'Retur '.$this->retur->code,
        ];
    }
}
