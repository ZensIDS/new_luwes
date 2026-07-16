<?php

namespace App\Exports;

use App\Models\PickingList;
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

class PickingListSingleExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithDrawings, WithCustomStartCell, WithProperties
{
    use Exportable;
    protected $pickingList;
    protected $settings;
    protected $lokasi;

    public function __construct(PickingList $pickingList, array $settings = [], ?string $lokasi = null)
    {
        $this->pickingList = $pickingList;
        $this->settings    = $settings;
        $this->lokasi      = $lokasi;
    }

    public function collection()
    {
        $items = $this->pickingList->items;

        if ($this->lokasi) {
            $items = $items->filter(fn ($item) => ($item->location ?? $item->product?->lokasi) === $this->lokasi);
        }

        return $items->values();
    }

    public function headings(): array
    {
        return ['No', 'Kode Barang', 'Nama Barang', 'Lokasi Rak', 'Satuan', 'Qty Diminta', 'Qty Diambil'];
    }

    public function map($item): array
    {
        static $no = 0;
        $no++;

        $kPick   = $item->product?->konversiDisplay($item->qty_to_pick) ?? '-';
        $kPicked = $item->product?->konversiDisplay($item->qty_picked) ?? '-';

        // Mengambil kode barang asli
        $rawCode = $item->product->code ?? '';

        // Trik: Menambahkan karakter spasi tidak terlihat (\u{00A0}) di awal kode
        // Ini memaksa Excel membacanya sebagai string/teks murni (0 di depan tidak hilang)
        $formattedCode = $rawCode !== '' ? "\u{00A0}" . $rawCode : '';

        return [
            $no,
            $formattedCode,
            $item->product->name ?? '',
            $item->location ?? '-',
            $item->product->satuan ?? 'PCS',
            $item->qty_to_pick.($kPick && $kPick !== '-' ? " ({$kPick})" : ''),
            $item->qty_picked.($kPicked && $kPicked !== '-' ? " ({$kPicked})" : ''),
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

        $sheet->setCellValue('B8', 'DOKUMEN PICKING LIST');
        $sheet->mergeCells('B8:H8');
        $sheet->getStyle('B8')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('C10', 'Kode Picking :');
        
        // Memaksa Kode Picking di bagian atas memakai trik spasi tak terlihat juga agar aman dari format eksponensial Excel
        $sheet->setCellValue('D10', "\u{00A0}" . $this->pickingList->code);
        $sheet->getStyle('C10')->getFont()->setBold(true);

        $sheet->setCellValue('C11', 'Tanggal :');
        $sheet->setCellValue('D11', Carbon::parse($this->pickingList->created_at)->isoFormat('DD MMMM YYYY'));
        $sheet->getStyle('C11')->getFont()->setBold(true);

        $sheet->setCellValue('C12', 'Kode Request :');
        $sheet->setCellValue('D12', $this->pickingList->requestOrder->code ?? '-');
        $sheet->getStyle('C12')->getFont()->setBold(true);

        $sheet->setCellValue('C13', 'Tujuan :');
        $sheet->setCellValue('D13', $this->pickingList->requestOrder->owner->name ?? '-');
        $sheet->getStyle('C13')->getFont()->setBold(true);

        $sheet->setCellValue('B15', 'Daftar Barang Yang Diambil');
        $sheet->getStyle('B15')->getFont()->setBold(true);

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
                
            // Meratakan kolom Kode Barang (Kolom C) ke kiri agar terlihat rapi sebagai text
            $sheet->getStyle('C18:C'.$highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }

        $sheet->getColumnDimension('B')->setWidth(5);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(32);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(13);
        $sheet->getColumnDimension('H')->setWidth(13);

        $sheet->getStyle('E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $notesRow = $highestRow + 2;
        $sheet->setCellValue('B'.$notesRow, 'Catatan :');
        $sheet->setCellValue('D'.$notesRow, $this->pickingList->notes ?? '');
        $sheet->getStyle('B'.$notesRow)->getFont()->setBold(true);

        $statusRow = $notesRow + 1;
        $sheet->setCellValue('B'.$statusRow, 'Status Picking :');
        $sheet->setCellValue('D'.$statusRow, ucfirst($this->pickingList->status));
        $sheet->getStyle('B'.$statusRow)->getFont()->setBold(true);

        $row = $statusRow + 3;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('D'.$row.':F'.$row);
        $sheet->mergeCells('G'.$row.':H'.$row);
        $sheet->setCellValue('B'.$row, 'Disiapkan Oleh');
        $sheet->setCellValue('D'.$row, 'Diperiksa');
        $sheet->setCellValue('G'.$row, 'Diserahkan');
        $sheet->getStyle('B'.$row.':H'.$row)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row++;
        $sheet->mergeCells('B'.$row.':C'.$row);
        $sheet->mergeCells('D'.$row.':F'.$row);
        $sheet->mergeCells('G'.$row.':H'.$row);
        $sheet->setCellValue('B'.$row, 'Staff Gudang');
        $sheet->setCellValue('D'.$row, 'Supervisor Gudang');
        $sheet->setCellValue('G'.$row, 'Pengirim');
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
            'title' => 'Picking List',
            'description' => 'PK '.$this->pickingList->code,
        ];
    }
}