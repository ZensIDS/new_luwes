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
        return ['No', 'Kode Barang', 'Nama Barang', 'Qty', 'Satuan'];
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
            $item->qty_requested . ($k && $k !== '-' ? " ({$k})" : ''),
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

        // Header Kertas / Kop Perusahaan
        $sheet->setCellValue('D2', $companyName);
        $sheet->mergeCells('D2:F2');
        $sheet->getStyle('D2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('D3', $address);
        $sheet->mergeCells('D3:F3');
        $sheet->setCellValue('D4', $contactInfo);
        $sheet->mergeCells('D4:F4');
        $sheet->getStyle('D3:D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension(5)->setRowHeight(20);

        // Garis Pembatas Kop Surat
        $sheet->mergeCells('B6:F6');
        $sheet->getStyle('B6:F6')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THICK);

        // Judul Dokumen
        $sheet->setCellValue('B8', 'SURAT PERMINTAAN BARANG (SPB)');
        $sheet->mergeCells('B8:F8');
        $sheet->getStyle('B8')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Detail Header SPB
        $sheet->setCellValue('B10', 'Nomor SPB :');
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

        // Header Tabel Barang (Kolom B17 sampai F17)
        $sheet->getStyle('B17:F17')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);

        // Border Isi Tabel Barang (Hanya B s/d F)
        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 17) {
            $sheet->getStyle('B18:F' . $highestRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // Alignment Isian Tabel
            $sheet->getStyle('B18:B' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // No
            $sheet->getStyle('C18:C' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);   // Kode Barang
            $sheet->getStyle('E18:E' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Qty
            $sheet->getStyle('F18:F' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Satuan

            // AKTIFKAN WRAP TEXT UNTUK NAMA BARANG (KOLOM D)
            $sheet->getStyle('D18:D' . $highestRow)->getAlignment()->setWrapText(true);

            // Opsional: Buat posisi teks secara vertikal berada di tengah cell agar rapi saat baris meninggi
            $sheet->getStyle('B18:F' . $highestRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        }

        // Pengaturan Lebar Kolom (B - F)
        $sheet->getColumnDimension('B')->setWidth(6);   // No
        $sheet->getColumnDimension('C')->setWidth(18);  // Kode Barang
        $sheet->getColumnDimension('D')->setWidth(35);  // Nama Barang
        $sheet->getColumnDimension('E')->setWidth(16);  // Qty
        $sheet->getColumnDimension('F')->setWidth(12);  // Satuan

        // Bagian Catatan
        $notesRow = $highestRow + 2;
        $sheet->setCellValue('B' . $notesRow, 'Catatan');
        $sheet->setCellValue('D' . $notesRow, $this->requestOrder->notes ?? '');
        $sheet->getStyle('B' . $notesRow)->getFont()->setBold(false);

        // Sample Barang (Jika ada)
        $extraNotes = $this->requestOrder->additionalNotes ?? collect();
        $afterNotesRow = $notesRow + 2;
        if ($extraNotes->isNotEmpty()) {
            $sheet->setCellValue('B' . $afterNotesRow, 'Sample Barang');
            $sheet->getStyle('B' . $afterNotesRow)->getFont()->setBold(true);

            $enHeader = $afterNotesRow + 1;
            $sheet->setCellValue('B' . $enHeader, 'No');
            $sheet->setCellValue('C' . $enHeader, 'Kategori');
            $sheet->setCellValue('D' . $enHeader, 'Qty');
            $sheet->setCellValue('E' . $enHeader, 'Nama PJ');
            $sheet->getStyle('B' . $enHeader . ':E' . $enHeader)->applyFromArray([
                'font'      => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            ]);

            $enRow = $enHeader + 1;
            foreach ($extraNotes as $i => $note) {
                $sheet->setCellValue('B' . $enRow, $i + 1);
                $sheet->setCellValue('C' . $enRow, $note->kategori);
                $sheet->setCellValue('D' . $enRow, $note->qty);
                $sheet->setCellValue('E' . $enRow, $note->nama_pj ?? '-');
                $sheet->getStyle('B' . $enRow . ':E' . $enRow)
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $enRow++;
            }
            $afterNotesRow = $enRow + 1;
        }

        // Section Tanda Tangan (Proporsional B sampai F)
        $row = $afterNotesRow + 1;
        $sheet->mergeCells('B' . $row . ':C' . $row);
        $sheet->mergeCells('E' . $row . ':F' . $row);
        $sheet->setCellValue('B' . $row, 'Pemohon');
        $sheet->setCellValue('D' . $row, 'Disetujui');
        $sheet->setCellValue('E' . $row, 'Gudang');
        $sheet->getStyle('B' . $row . ':F' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row++;
        $sheet->mergeCells('B' . $row . ':C' . $row);
        $sheet->mergeCells('E' . $row . ':F' . $row);
        $sheet->setCellValue('B' . $row, 'Kepala Toko');
        $sheet->setCellValue('D' . $row, 'Manager');
        $sheet->setCellValue('E' . $row, 'Staff Gudang');
        $sheet->getStyle('B' . $row . ':F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row += 5;
        $sheet->mergeCells('B' . $row . ':C' . $row);
        $sheet->mergeCells('E' . $row . ':F' . $row);
        $sheet->setCellValue('B' . $row, 'Nama');
        $sheet->setCellValue('D' . $row, 'Nama');
        $sheet->setCellValue('E' . $row, 'Nama');
        $sheet->getStyle('B' . $row . ':F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B' . ($row - 1) . ':F' . ($row - 1))
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
            'description' => 'SPB ' . $this->requestOrder->code,
        ];
    }
}
