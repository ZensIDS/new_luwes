<?php

namespace App\Exports;

use App\Models\Pembelian;
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

class PenerimaanExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithDrawings, WithCustomStartCell, WithProperties
{
    use Exportable;
    protected $pembelian;
    protected $type;
    protected $settings;

    public function __construct(Pembelian $pembelian, string $type = 'po', array $settings = [])
    {
        $this->pembelian = $pembelian;
        $this->type = $type;
        $this->settings = $settings;
    }

    public function collection()
    {
        return $this->pembelian->pembelianProducts;
    }

    public function headings(): array
    {
        return ['No', 'Kode Barang', 'Nama Barang', 'Satuan', 'Qty PO', 'Qty Diterima', 'Harga', 'Total'];
    }

    public function map($item): array
    {
        static $no = 0;
        $no++;

        $k  = $item->product->konversiDisplay($item->qty);
        $kd = $item->product->konversiDisplay($item->qty_diterima ?? 0);

        return [
            $no,
            $item->product->code ?? '',
            $item->product->name ?? '',
            $item->product->satuan ?? 'PCS',
            $item->qty.($k && $k !== '-' ? " ({$k})" : ''),
            ($item->qty_diterima ?? 0).($kd && $kd !== '-' ? " ({$kd})" : ''),
            'Rp '.number_format($item->harga_beli, 0, ',', '.'),
            'Rp '.number_format($item->subtotal, 0, ',', '.'),
        ];
    }

    public function startCell(): string
    {
        return 'B16';
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
        $sheet->mergeCells('D2:J2');
        $sheet->getStyle('D2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('D3', $address);
        $sheet->mergeCells('D3:J3');
        $sheet->setCellValue('D4', $contactInfo);
        $sheet->mergeCells('D4:J4');
        $sheet->getStyle('D3:D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension(5)->setRowHeight(20);

        $sheet->mergeCells('B6:J6');
        $sheet->getStyle('B6:J6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);

        $title = $this->type === 'outlet' ? 'DOKUMEN PENERIMAAN BARANG OUTLET' : 'DOKUMEN PENERIMAAN BARANG PO';
        $sheet->setCellValue('B8', $title);
        $sheet->mergeCells('B8:J8');
        $sheet->getStyle('B8')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        if ($this->type === 'outlet') {
            $sheet->setCellValue('B10', 'No Penerimaan :');
            $sheet->setCellValue('D10', $this->pembelian->code_gr ?? '-');
            $sheet->setCellValue('B11', 'Tanggal :');
            $sheet->setCellValue('D11', $this->pembelian->receipt_date
                ? Carbon::parse($this->pembelian->receipt_date)->isoFormat('DD MMMM YYYY HH:mm')
                : '-');
            $sheet->setCellValue('B12', 'Kode Request :');
            $sheet->setCellValue('D12', $this->pembelian->code ?? '-');
            $sheet->setCellValue('B13', 'Request By :');
            $sheet->setCellValue('D13', $this->pembelian->receipt_pic ?? '-');
            $sheet->setCellValue('B14', 'Kode DO :');
            $sheet->setCellValue('D14', $this->pembelian->code_gr ?? '-');
            $sheet->setCellValue('B15', 'Tanggal DO :');
            $sheet->setCellValue('D15', $this->pembelian->receipt_date
                ? Carbon::parse($this->pembelian->receipt_date)->isoFormat('DD MMMM YYYY')
                : '-');
        } else {
            $sheet->setCellValue('B10', 'Pembelian :');
            $sheet->setCellValue('D10', $this->pembelian->code_gr ?? '-');
            $sheet->setCellValue('B11', 'Tanggal :');
            $sheet->setCellValue('D11', $this->pembelian->receipt_date
                ? Carbon::parse($this->pembelian->receipt_date)->isoFormat('DD MMMM YYYY HH:mm')
                : '-');
            $sheet->setCellValue('B12', 'Kode PO :');
            $sheet->setCellValue('D12', $this->pembelian->code ?? '-');
            $sheet->setCellValue('B13', 'Supplier :');
            $sheet->setCellValue('D13', $this->pembelian->supplier->name ?? '-');
            $sheet->setCellValue('B14', 'No Invoice :');
            $sheet->setCellValue('D14', $this->pembelian->code_gr ?? '-');
            $sheet->setCellValue('B15', 'Tanggal Invoice :');
            $sheet->setCellValue('D15', $this->pembelian->receipt_date
                ? Carbon::parse($this->pembelian->receipt_date)->isoFormat('DD MMMM YYYY')
                : '-');
        }

        $sheet->getStyle('B10:B15')->getFont()->setBold(true);

        // TABLE HEADER
        $sheet->getStyle('B16:J16')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);

        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 16) {
            $sheet->getStyle('B17:J'.$highestRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(14);
        $sheet->getColumnDimension('J')->setWidth(16);

        $sheet->getStyle('E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('I')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('J')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // CATATAN
        $notesRow = $highestRow + 2;
        $sheet->setCellValue('B'.$notesRow, 'Catatan :');

        // SIGNATURE
        $row = $notesRow + 3;
        $sheet->mergeCells('B'.$row.':D'.$row);
        $sheet->mergeCells('F'.$row.':G'.$row);
        $sheet->mergeCells('I'.$row.':J'.$row);
        $sheet->setCellValue('B'.$row, 'Diterima Oleh');
        $sheet->setCellValue('F'.$row, 'Diperiksa');
        $sheet->setCellValue('I'.$row, 'Disetujui');
        $sheet->getStyle('B'.$row.':J'.$row)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row++;
        $sheet->mergeCells('B'.$row.':D'.$row);
        $sheet->mergeCells('F'.$row.':G'.$row);
        $sheet->mergeCells('I'.$row.':J'.$row);
        $sheet->setCellValue('B'.$row, 'Staff Gudang');
        $sheet->setCellValue('F'.$row, 'Supervisor Gudang');
        $sheet->setCellValue('I'.$row, 'Manager');
        $sheet->getStyle('B'.$row.':J'.$row)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row += 5;
        $sheet->mergeCells('B'.$row.':D'.$row);
        $sheet->mergeCells('F'.$row.':G'.$row);
        $sheet->mergeCells('I'.$row.':J'.$row);
        $sheet->setCellValue('B'.$row, 'Nama');
        $sheet->setCellValue('F'.$row, 'Nama');
        $sheet->setCellValue('I'.$row, 'Nama');
        $sheet->getStyle('B'.$row.':J'.$row)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('B'.($row - 1).':J'.($row - 1))
            ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    }

    public function drawings()
    {
        $drawing = new Drawing();
        $drawing->setName('Logo');
        $drawing->setDescription('Logo');

        // Use stored logo path or fallback to default
        $logoPath = $this->settings['logo'] ?? null;
        if ($logoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
            $drawing->setPath(\Illuminate\Support\Facades\Storage::disk('public')->path($logoPath));
        } else {
            $drawing->setPath(public_path('img/logo.jpeg')); // fallback
        }

        $drawing->setHeight(80);
        $drawing->setCoordinates('B2');

        return [$drawing];
    }

    public function properties(): array
    {
        return [
            'creator' => config('app.name'),
            'title' => 'Dokumen Penerimaan',
            'description' => $this->pembelian->code,
        ];
    }
}
