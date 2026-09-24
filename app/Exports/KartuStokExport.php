<?php

namespace App\Exports;

use App\Models\Product;
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

class KartuStokExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithDrawings, WithCustomStartCell, WithProperties
{
    use Exportable;
    protected Product $product;
    protected array $kartu;
    protected $transactions = [];
    protected $settings;

    /**
     * @param array $kartu hasil App\Services\KartuStokBuilder::build() (sumber angka yang sama
     *                     dengan halaman Kartu Stok).
     */
    public function __construct(Product $product, array $kartu, array $settings = [])
    {
        $this->product  = $product;
        $this->kartu    = $kartu;
        $this->settings = $settings;

        foreach ($kartu['transactions'] as $t) {
            $this->transactions[] = [
                'tanggal'    => Carbon::parse($t['tanggal'])->isoFormat('DD MMMM YYYY'),
                'batch'      => $t['sku'],
                'keterangan' => $t['keterangan'] ?? '-',
                'masuk'      => $t['masuk'] > 0 ? $t['masuk'] : '0',
                'keluar'     => $t['keluar'] > 0 ? $t['keluar'] : '0',
                'total'      => $t['stok_akhir'] ?? '0', // saldo berjalan per SKU
            ];
        }
    }

    public function collection()
    {
        return collect($this->transactions);
    }

    public function headings(): array
    {
        return ['Tanggal', 'No Batch', 'Keterangan', 'Qty Masuk', 'Qty Keluar', 'Total'];
    }

    public function map($row): array
    {
        $fmt = function ($qty) {
            $k = $this->product->konversiDisplay($qty);

            return $qty.($k && $k !== '-' ? " ({$k})" : '');
        };

        return [
            $row['tanggal'],
            $row['batch'],
            $row['keterangan'],
            $fmt($row['masuk']),
            $fmt($row['keluar']),
            $fmt($row['total']),
        ];
    }

    public function startCell(): string
    {
        return 'B15';
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

        $sheet->setCellValue('B8', 'KARTU STOK BARANG');
        $sheet->mergeCells('B8:H8');
        $sheet->getStyle('B8')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->setCellValue('B10', 'Barcode :');
        $sheet->setCellValue('D10', $this->product->code ?? '-');
        $sheet->getStyle('B10')->getFont()->setBold(true);

        $sheet->setCellValue('B11', 'Nama Barang :');
        $sheet->setCellValue('D11', $this->product->name ?? '-');
        $sheet->getStyle('B11')->getFont()->setBold(true);

        $sheet->setCellValue('B12', 'Satuan :');
        $sheet->setCellValue('D12', $this->product->satuan ?? 'PCS');
        $sheet->getStyle('B12')->getFont()->setBold(true);

        $sheet->setCellValue('B13', 'Supplier :');
        $sheet->setCellValue('D13', $this->kartu['product']['suppliers'] ?? '-');
        $sheet->getStyle('B13')->getFont()->setBold(true);

        // Lokasi from stock->location
        $sheet->setCellValue('F12', 'Lokasi Penyimpanan :');
        $sheet->setCellValue('H12', $this->product->lokasi ?? '-');
        $sheet->getStyle('F12')->getFont()->setBold(true);

        $sheet->setCellValue('B14', 'Mutasi Stok (semua SKU)');
        $sheet->getStyle('B14')->getFont()->setBold(true);

        // TABLE HEADER
        $sheet->getStyle('B15:H15')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);

        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 15) {
            $sheet->getStyle('B16:H'.$highestRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(24);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(12);

        $sheet->getStyle('E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // SUMMARY
        $summary     = $this->kartu['product_summary'];
        $totalMasuk  = collect($this->transactions)->sum('masuk');
        $totalKeluar = collect($this->transactions)->sum('keluar');

        $summaryRow = $highestRow + 2;
        $sheet->setCellValue('B'.$summaryRow, 'Total Masuk :');
        $sheet->setCellValue('D'.$summaryRow, $totalMasuk);
        $sheet->setCellValue('F'.$summaryRow, 'Total Keluar :');
        $sheet->setCellValue('H'.$summaryRow, $totalKeluar);

        $sheet->setCellValue('B'.($summaryRow + 1), 'Stok Fisik (Gudang) :');
        $sheet->setCellValue('D'.($summaryRow + 1), $summary['total_qty']);
        $sheet->setCellValue('F'.($summaryRow + 1), 'Saldo Kartu :');
        $sheet->setCellValue('H'.($summaryRow + 1), $summary['total_saldo_kartu']);

        $sheet->setCellValue('B'.($summaryRow + 2), 'Selisih :');
        $sheet->setCellValue('D'.($summaryRow + 2), $summary['total_selisih']);

        $sheet->getStyle('B'.$summaryRow.':H'.($summaryRow + 2))
            ->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);

        // RINCIAN PER SKU
        $r = $summaryRow + 4;
        $sheet->setCellValue('B'.$r, 'Rincian per SKU');
        $sheet->getStyle('B'.$r)->getFont()->setBold(true);
        $r++;
        $sheet->setCellValue('B'.$r, 'SKU');
        $sheet->setCellValue('D'.$r, 'Stok Fisik');
        $sheet->setCellValue('F'.$r, 'Saldo Kartu');
        $sheet->setCellValue('H'.$r, 'Selisih');
        $sheet->getStyle('B'.$r.':H'.$r)->getFont()->setBold(true);
        foreach ($summary['breakdown'] as $b) {
            $r++;
            $sheet->setCellValue('B'.$r, $b['sku']);
            $sheet->setCellValue('D'.$r, $b['qty']);
            $sheet->setCellValue('F'.$r, $b['saldo_kartu']);
            $sheet->setCellValue('H'.$r, $b['selisih']);
        }

        // SIGNATURE
        $row = $r + 3;
        $sheet->mergeCells('B'.$row.':D'.$row);
        $sheet->mergeCells('F'.$row.':H'.$row);
        $sheet->setCellValue('B'.$row, 'Dibuat Oleh');
        $sheet->setCellValue('F'.$row, 'Diperiksa');
        $sheet->getStyle('B'.$row.':H'.$row)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row++;
        $sheet->mergeCells('B'.$row.':D'.$row);
        $sheet->mergeCells('F'.$row.':H'.$row);
        $sheet->setCellValue('B'.$row, 'Staff Gudang');
        $sheet->setCellValue('F'.$row, 'Supervisor Gudang');
        $sheet->getStyle('B'.$row.':H'.$row)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row += 5;
        $sheet->mergeCells('B'.$row.':D'.$row);
        $sheet->mergeCells('F'.$row.':H'.$row);
        $sheet->setCellValue('B'.$row, 'Nama');
        $sheet->setCellValue('F'.$row, 'Nama');
        $sheet->getStyle('B'.$row.':H'.$row)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('B'.($row - 1).':H'.($row - 1))
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
            'title' => 'Kartu Stok',
            'description' => 'Kartu Stok '.$this->product->code,
        ];
    }
}