<?php

namespace App\Exports;

use App\Models\RequestOrder;
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

class RequestOrderSingleExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithDrawings, WithCustomStartCell, WithProperties
{
    use Exportable;
    protected $requestOrder;
    protected $settings;

    public function __construct(RequestOrder $requestOrder, array $settings = [])
    {
        $this->requestOrder = $requestOrder;
        $this->settings = $settings;
    }

    public function collection()
    {
        return $this->requestOrder->items;
    }

    public function headings(): array
    {
        return ['No', 'Kode Barang', 'Nama Barang', 'Qty', 'Satuan', 'Harga'];
    }

    public function map($item): array
    {
        static $no = 0;
        $no++;

        $k = $item->product?->konversiDisplay($item->qty_requested) ?? '-';

        // Mengambil kode barang asli
        $rawCode = $item->product->code ?? '';

        // Trik: Menambahkan karakter spasi tidak terlihat (\u{00A0}) di awal kode barang
        $formattedCode = $rawCode !== '' ? "\u{00A0}" . $rawCode : '';

        return [
            $no,
            $formattedCode,
            $item->product->name ?? '',
            $item->qty_requested.($k && $k !== '-' ? " ({$k})" : ''),
            $item->product->satuan ?? 'PCS',
            'Rp '.number_format($item->product->harga_beli ?? 0, 0, ',', '.'),
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
        $sheet->mergeCells('D2:H2');
        $sheet->getStyle('D2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('D3', $address);
        $sheet->mergeCells('D3:H3');
        $sheet->setCellValue('D4', $contactInfo);
        $sheet->mergeCells('D4:H4');
        $sheet->getStyle('D3:D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension(5)->setRowHeight(20);

        $sheet->mergeCells('B6:H6');
        $sheet->getStyle('B6:H6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);

        $sheet->setCellValue('B8', 'SURAT PERMINTAAN BARANG (SPB)');
        $sheet->mergeCells('B8:H8');
        $sheet->getStyle('B8')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('B10', 'Nomor SPB :');
        
        // Trik: Mengamankan Nomor SPB di bagian header agar tidak disingkat Excel
        $sheet->setCellValue('D10', "\u{00A0}" . $this->requestOrder->code);
        $sheet->getStyle('B10')->getFont()->setBold(true);
        
        $sheet->setCellValue('B11', 'Tanggal :');
        $sheet->setCellValue('D11', Carbon::parse($this->requestOrder->request_date)->isoFormat('DD MMMM YYYY'));
        $sheet->getStyle('B11')->getFont()->setBold(true);
        $sheet->setCellValue('B12', 'Nama Outlet :');
        $sheet->setCellValue('D12', $this->requestOrder->owner->name ?? '-');
        $sheet->getStyle('B12')->getFont()->setBold(true);
        $sheet->setCellValue('B13', 'Pemohon :');
        $sheet->setCellValue('D13', $this->requestOrder->requestedBy->name ?? '-');
        $sheet->getStyle('B13')->getFont()->setBold(true);
        $sheet->setCellValue('B14', 'Jabatan :');
        $sheet->setCellValue('D14', $this->requestOrder->requestedBy->jabatan ?? '-');
        $sheet->getStyle('B14')->getFont()->setBold(true);

        $sheet->setCellValue('B16', 'Detail Permintaan Barang');
        $sheet->getStyle('B16')->getFont()->setBold(true);

        $sheet->getStyle('B17:H17')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);

        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 17) {
            $sheet->getStyle('B18:H'.$highestRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                
            // Meratakan kolom Kode Barang (Kolom C) ke kiri agar rapi sebagai teks
            $sheet->getStyle('C18:C'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(32);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(8);
        $sheet->getColumnDimension('H')->setWidth(14);

        $sheet->getStyle('E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $notesRow = $highestRow + 2;
        $sheet->setCellValue('B'.$notesRow, 'Catatan');
        $sheet->setCellValue('D'.$notesRow, $this->requestOrder->notes ?? '');
        $sheet->getStyle('B'.$notesRow)->getFont()->setBold(false);

        // Sample Barang (extra notes)
        $extraNotes = $this->requestOrder->additionalNotes ?? collect();
        $afterNotesRow = $notesRow + 2;
        if ($extraNotes->isNotEmpty()) {
            $sheet->setCellValue('B'.$afterNotesRow, 'Sample Barang');
            $sheet->getStyle('B'.$afterNotesRow)->getFont()->setBold(true);

            $enHeader = $afterNotesRow + 1;
            $sheet->setCellValue('B'.$enHeader, 'No');
            $sheet->setCellValue('C'.$enHeader, 'Kategori');
            $sheet->setCellValue('D'.$enHeader, 'Qty');
            $sheet->setCellValue('E'.$enHeader, 'Nama PJ');
            $sheet->getStyle('B'.$enHeader.':E'.$enHeader)->applyFromArray([
                'font'      => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            ]);

            $enRow = $enHeader + 1;
            foreach ($extraNotes as $i => $note) {
                $sheet->setCellValue('B'.$enRow, $i + 1);
                $sheet->setCellValue('C'.$enRow, $note->kategori);
                $sheet->setCellValue('D'.$enRow, $note->qty);
                $sheet->setCellValue('E'.$enRow, $note->nama_pj ?? '-');
                $sheet->getStyle('B'.$enRow.':E'.$enRow)
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $enRow++;
            }
            $afterNotesRow = $enRow + 1;
        }

        $row = $afterNotesRow + 1;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('D'.$row.':F'.$row);
        $sheet->mergeCells('G'.$row.':H'.$row);
        $sheet->setCellValue('B'.$row, 'Pemohon');
        $sheet->setCellValue('D'.$row, 'Disetujui');
        $sheet->setCellValue('G'.$row, 'Gudang');
        $sheet->getStyle('B'.$row.':H'.$row)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row++;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('D'.$row.':F'.$row);
        $sheet->mergeCells('G'.$row.':H'.$row);
        $sheet->setCellValue('B'.$row, 'Kepala Toko');
        $sheet->setCellValue('D'.$row, 'Manager');
        $sheet->setCellValue('G'.$row, 'Staff Gudang');
        $sheet->getStyle('B'.$row.':H'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row += 5;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('D'.$row.':F'.$row);
        $sheet->mergeCells('G'.$row.':H'.$row);
        $sheet->setCellValue('B'.$row, 'Nama');
        $sheet->setCellValue('D'.$row, 'Nama');
        $sheet->setCellValue('G'.$row, 'Nama');
        $sheet->getStyle('B'.$row.':H'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B'.($row - 1).':H'.($row - 1))
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
            'title' => 'Surat Permintaan Barang',
            'description' => 'SPB '.$this->requestOrder->code,
        ];
    }
}