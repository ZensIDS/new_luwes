<?php

namespace App\Exports;

use App\Models\DeliveryOrder;
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

class DeliveryOrderSingleExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithDrawings, WithCustomStartCell, WithProperties
{
    use Exportable;
    protected $deliveryOrder;
    protected $settings;

    public function __construct(DeliveryOrder $deliveryOrder, array $settings = [])
    {
        $this->deliveryOrder = $deliveryOrder;
        $this->settings = $settings;
    }

    public function collection()
    {
        return $this->deliveryOrder->items;
    }

    public function headings(): array
    {
        return ['No', 'Kode Barang', 'Nama Barang', 'Qty', 'Satuan'];
    }

    public function map($item): array
    {
        static $no = 0;
        $no++;
    
        $k = $item->product?->konversiDisplay($item->qty) ?? '-';
    
        $rawCode = $item->product->code ?? '';
    
        // Trik: Tambahkan tanda petik tunggal (') di depan kode barang
        $formattedCode = $rawCode !== '' ? "'" . $rawCode : '';
    
        return [
            $no,
            $formattedCode,
            $item->product->name ?? '',
            $item->qty.($k && $k !== '-' ? " ({$k})" : ''),
            $item->product->satuan ?? 'PCS',
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
        $sheet->mergeCells('D2:F2'); // G → F
        $sheet->getStyle('D2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('D3', $address);
        $sheet->mergeCells('D3:F3'); // G → F
        $sheet->setCellValue('D4', $contactInfo);
        $sheet->mergeCells('D4:F4'); // G → F
        $sheet->getStyle('D3:D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
        $sheet->getRowDimension(5)->setRowHeight(20);
    
        $sheet->mergeCells('B6:F6'); // G → F
        $sheet->getStyle('B6:F6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);
    
        $sheet->setCellValue('B8', 'DELIVERY ORDER');
        $sheet->mergeCells('B8:F8'); // G → F
        $sheet->getStyle('B8')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    
        $sheet->setCellValue('B10', 'Kode DO :');
        $sheet->setCellValue('D10', "\u{00A0}" . $this->deliveryOrder->code);
        $sheet->setCellValue('B11', 'Tanggal :');
        $sheet->setCellValue('D11', Carbon::parse($this->deliveryOrder->delivery_date)->isoFormat('DD MMMM YYYY'));
        $sheet->setCellValue('B12', 'Kode Request :');
        $sheet->setCellValue('D12', "\u{00A0}" . ($this->deliveryOrder->requestOrder->code ?? '-'));
        $sheet->setCellValue('B13', 'Tujuan :');
        $sheet->setCellValue('D13', $this->deliveryOrder->owner->name ?? '-');
        $sheet->setCellValue('B14', 'Nama Pengirim :');
        $sheet->setCellValue('D14', $this->deliveryOrder->preparedBy->name ?? '-');
        $sheet->setCellValue('B15', 'Status :');
        $sheet->setCellValue('D15', $this->deliveryOrder->status ?? '-');
        $sheet->getStyle('B10:B15')->getFont()->setBold(true);
    
        $sheet->setCellValue('B16', 'Detail Barang Dikirim');
        $sheet->getStyle('B16')->getFont()->setBold(true);
    
        // TABLE HEADER
        $sheet->getStyle('B17:F17')->applyFromArray([ // G → F
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);
    
        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 17) {
            $sheet->getStyle('B18:F'.$highestRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        
            $sheet->getStyle('C18:C'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        
            // Wrap text untuk kolom Nama Barang (D)
            $sheet->getStyle('D18:D'.$highestRow)->getAlignment()->setWrapText(true);
        
            for ($r = 18; $r <= $highestRow; $r++) {
                $text = (string) $sheet->getCell('D'.$r)->getValue();
                $charsPerLine = 22; // lebih konservatif dari 32 biar tidak under-estimate
                $lines = max(1, ceil(mb_strlen($text) / $charsPerLine));
                $sheet->getRowDimension($r)->setRowHeight($lines * 16); // 16pt per baris
            }
        
            // Ganti CENTER ke TOP biar teks tidak terpotong atas
            $sheet->getStyle('B18:F'.$highestRow)
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_TOP);
        }
    
        // COLUMN WIDTHS
        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(32);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(10);
        // Kolom G dihapus
    
        // ALIGNMENT
        $sheet->getStyle('E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
        // CATATAN
        $notesRow = $highestRow + 2;
        $sheet->setCellValue('B'.$notesRow, 'Catatan :');
        $sheet->setCellValue('D'.$notesRow, $this->deliveryOrder->notes ?? '');
    
        // SIGNATURE
        $row = $notesRow + 3;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('D'.$row.':E'.$row);  // sebelumnya D:E → tetap
        $sheet->mergeCells('F'.$row.':F'.$row);  // sebelumnya F:G → F saja
        $sheet->setCellValue('B'.$row, 'Disiapkan Oleh');
        $sheet->setCellValue('D'.$row, 'Dikirim Oleh');
        $sheet->setCellValue('F'.$row, 'Diterima Oleh');
        $sheet->getStyle('B'.$row.':F'.$row)->applyFromArray([ // G → F
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    
        $row++;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('D'.$row.':E'.$row);
        $sheet->mergeCells('F'.$row.':F'.$row);
        $sheet->setCellValue('B'.$row, 'Staff Gudang');
        $sheet->setCellValue('D'.$row, 'Driver');
        $sheet->setCellValue('F'.$row, 'Penerima');
        $sheet->getStyle('B'.$row.':F'.$row)->applyFromArray([ // G → F
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    
        $row += 5;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('D'.$row.':E'.$row);
        $sheet->mergeCells('F'.$row.':F'.$row);
        $sheet->setCellValue('B'.$row, 'Nama');
        $sheet->setCellValue('D'.$row, 'Nama');
        $sheet->setCellValue('F'.$row, 'Nama');
        $sheet->getStyle('B'.$row.':F'.$row)->applyFromArray([ // G → F
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('B'.($row - 1).':F'.($row - 1)) // G → F
            ->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    
        // RECEIVED INFO
        $receivedRow = $row + 2;
        $sheet->setCellValue('B'.$receivedRow, 'Nama Penerima :');
        $sheet->setCellValue('D'.$receivedRow, $this->deliveryOrder->receivedBy->name ?? '-');
        $sheet->setCellValue('F'.$receivedRow, 'TTD');
    
        $sheet->setCellValue('B'.($receivedRow + 1), 'Jabatan :');
        $sheet->setCellValue('D'.($receivedRow + 1), $this->deliveryOrder->receivedBy->jabatan ?? '-');
    
        $sheet->setCellValue('B'.($receivedRow + 2), 'Tanggal Terima :');
        $sheet->setCellValue('D'.($receivedRow + 2), $this->deliveryOrder->received_date
            ? Carbon::parse($this->deliveryOrder->received_date)->isoFormat('DD MMMM YYYY')
            : '-');
    
        $sheet->getStyle('B'.$receivedRow.':B'.($receivedRow + 2))->getFont()->setBold(true);
    
        $sheet->getStyle('B'.$receivedRow.':F'.($receivedRow + 3)) // G → F
            ->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
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
            'title' => 'Delivery Order',
            'description' => 'DO '.$this->deliveryOrder->code,
        ];
    }
}