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

class ReturPembelianSingleExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithDrawings, WithCustomStartCell, WithProperties
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
        return $this->retur->refundPembelianItems()->with('product', 'stock.pembelian')->get();
    }

    public function headings(): array
    {
        return ['No', 'Kode Barang', 'Nama Barang', 'Batch/SKU', 'Qty', 'Satuan', 'Harga Satuan', 'Subtotal', 'Alasan', 'Resolusi'];
    }

    public function map($item): array
    {
        static $no = 0;
        $no++;

        $k          = $item->product?->konversiDisplay($item->qty) ?? '-';
        $resolution = match ($item->resolution) {
            'barang' => 'Retur Barang',
            'uang'   => 'Ganti Uang',
            default  => 'Menunggu',
        };

        return [
            $no,
            $item->product->code ?? '-',
            $item->product->name ?? '-',
            $item->sku ?? '-',
            $item->qty.($k && $k !== '-' ? " ({$k})" : ''),
            $item->product->satuan ?? 'PCS',
            'Rp '.number_format($item->harga ?? 0, 0, ',', '.'),
            'Rp '.number_format(($item->qty * ($item->harga ?? 0)), 0, ',', '.'),
            $item->alasan,
            $resolution,
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
        $sheet->mergeCells('D2:K2');
        $sheet->getStyle('D2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('D3', $address);
        $sheet->mergeCells('D3:K3');
        $sheet->setCellValue('D4', $contactInfo);
        $sheet->mergeCells('D4:K4');
        $sheet->getStyle('D3:D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension(5)->setRowHeight(20);

        $sheet->mergeCells('B6:K6');
        $sheet->getStyle('B6:K6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);

        $sheet->setCellValue('B8', 'DOKUMEN RETUR PEMBELIAN (GUDANG → SUPPLIER)');
        $sheet->mergeCells('B8:K8');
        $sheet->getStyle('B8')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('C10', 'Kode Retur :');
        $sheet->setCellValue('D10', $this->retur->code);
        $sheet->setCellValue('C11', 'Tanggal :');
        $sheet->setCellValue('D11', Carbon::parse($this->retur->tanggal)->isoFormat('DD MMMM YYYY'));
        $sheet->setCellValue('C12', 'Supplier :');
        $sheet->setCellValue('D12', $this->retur->supplier->name ?? '-');
        $sheet->setCellValue('C13', 'Operator :');
        $sheet->setCellValue('D13', $this->retur->user->name ?? '-');
        $sheet->setCellValue('C14', 'Status :');
        $sheet->setCellValue('D14', $this->retur->status === 'retur' ? 'Proses' : 'Complete');
        $sheet->getStyle('C10:C14')->getFont()->setBold(true);

        $sheet->setCellValue('C16', 'Detail Item Retur');
        $sheet->getStyle('C16')->getFont()->setBold(true);

        $sheet->getStyle('B17:K17')->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);

        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 17) {
            $sheet->getStyle('B18:K'.$highestRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(14);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(16);
        $sheet->getColumnDimension('I')->setWidth(18);
        $sheet->getColumnDimension('J')->setWidth(25);
        $sheet->getColumnDimension('K')->setWidth(15);

        $sheet->getStyle('B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('I')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $totalRow = $highestRow + 1;
        $sheet->mergeCells('B'.$totalRow.':H'.$totalRow);
        $sheet->setCellValue('B'.$totalRow, 'Total');
        $sheet->setCellValue('I'.$totalRow, 'Rp '.number_format($this->retur->total ?? 0, 0, ',', '.'));
        $sheet->getStyle('B'.$totalRow.':K'.$totalRow)->applyFromArray([
            'borders' => [
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM],
                'bottom' => ['borderStyle' => Border::BORDER_THIN],
                'left'   => ['borderStyle' => Border::BORDER_THIN],
                'right'  => ['borderStyle' => Border::BORDER_THIN],
            ],
        ]);
        $sheet->getStyle('B'.$totalRow)->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $sheet->getStyle('I'.$totalRow)->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);

        $row = $totalRow + 4;
        $sheet->mergeCells('B'.$row.':D'.$row);
        $sheet->mergeCells('F'.$row.':H'.$row);
        $sheet->mergeCells('J'.$row.':K'.$row);
        $sheet->setCellValue('B'.$row, 'Dibuat Oleh');
        $sheet->setCellValue('F'.$row, 'Diperiksa Oleh');
        $sheet->setCellValue('J'.$row, 'Disetujui Oleh');
        $sheet->getStyle('B'.$row.':K'.$row)->applyFromArray([
            'font'      => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row++;
        $sheet->mergeCells('B'.$row.':D'.$row);
        $sheet->mergeCells('F'.$row.':H'.$row);
        $sheet->mergeCells('J'.$row.':K'.$row);
        $sheet->setCellValue('B'.$row, 'Staff Gudang');
        $sheet->setCellValue('F'.$row, 'Supervisor Gudang');
        $sheet->setCellValue('J'.$row, 'Manager');
        $sheet->getStyle('B'.$row.':K'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row += 5;
        $sheet->mergeCells('B'.$row.':D'.$row);
        $sheet->mergeCells('F'.$row.':H'.$row);
        $sheet->mergeCells('J'.$row.':K'.$row);
        $sheet->setCellValue('B'.$row, 'Nama');
        $sheet->setCellValue('F'.$row, 'Nama');
        $sheet->setCellValue('J'.$row, 'Nama');
        $sheet->getStyle('B'.$row.':K'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B'.($row - 1).':K'.($row - 1))
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
            'title'       => 'Dokumen Retur Pembelian',
            'description' => 'Retur '.$this->retur->code,
        ];
    }
}
