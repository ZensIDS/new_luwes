<?php

namespace App\Exports;

use App\Models\RefundPembelian;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReturSupplierExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize, WithProperties
{
    use Exportable;

    protected $mulai;
    protected $selesai;
    protected $settings;
    protected $no = 0;

    public function __construct($mulai, $selesai, array $settings = [])
    {
        $this->mulai = $mulai;
        $this->selesai = $selesai;
        $this->settings = $settings;
    }

    public function collection()
    {
        return RefundPembelian::with([
            'supplier',
            'refundPembelianItems.product',
            'refundPembelianItems.stock.pembelian',
        ])
            ->where('type', 'gudang_ke_supplier')
            ->whereDate('tanggal', '>=', $this->mulai)
            ->whereDate('tanggal', '<=', $this->selesai)
            ->orderBy('tanggal')
            ->get()
            ->flatMap(function ($retur) {
                return $retur->refundPembelianItems->map(function ($item) use ($retur) {
                    $item->retur = $retur;

                    return $item;
                });
            });
    }

    public function title(): string
    {
        return 'LAPORAN RETUR KE SUPPLIER';
    }

    // 6 rows so data starts at row 7, matching styles() which targets row 6 as header
    public function headings(): array
    {
        return [
            ['LAPORAN RETUR KE SUPPLIER'], // row 1 — overwritten by styles()
            [],                            // row 2
            [],                            // row 3
            [],                            // row 4
            [],                            // row 5
            ['No', 'Tanggal', 'Kode Retur', 'Kode PO', 'Supplier', 'Kode Barang', 'Nama Barang', 'Batch', 'Qty Retur', 'Satuan', 'Alasan Retur', 'Status', 'Keterangan'],
        ];
    }

    public function map($item): array
    {
        $this->no++;
        $retur = $item->retur;

        return [
            $this->no,
            Carbon::parse($retur->tanggal)->isoFormat('DD MMMM YYYY'),
            $retur->code,
            $item->stock->pembelian->code ?? '-',   // Kode PO dari stock pembelian
            $retur->supplier->name ?? '-',
            $item->product->code ?? '-',
            $item->product->name ?? '-',
            $item->sku ?? '-',
            (function () use ($item) {
                $k = $item->product?->konversiDisplay($item->qty) ?? '-';

                return $item->qty.($k && $k !== '-' ? " ({$k})" : '');
            })(),
            $item->product->satuan ?? 'PCS',
            $item->alasan,
            $retur->status === 'retur' ? 'Proses' : 'Complete',
            '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $companyName = $this->settings['name'] ?? 'NAMA PERUSAHAAN';
        $address     = $this->settings['address'] ?? 'ALAMAT';
        $phone       = $this->settings['telp'] ?? '';
        $email       = $this->settings['email'] ?? '';
        $website     = $this->settings['website'] ?? '';
        $contactInfo = trim("$phone | $email | $website", ' |');

        $sheet->mergeCells('A1:M1');
        $sheet->setCellValue('A1', $companyName);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $sheet->mergeCells('A2:M2');
        $sheet->setCellValue('A2', $address);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:M3');
        $sheet->setCellValue('A3', $contactInfo);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $periode = Carbon::parse($this->mulai)->isoFormat('DD MMMM YYYY').' s/d '.Carbon::parse($this->selesai)->isoFormat('DD MMMM YYYY');
        $sheet->mergeCells('A4:M4');
        $sheet->setCellValue('A4', 'Periode: '.$periode);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle('A6:M6')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8EAADB']],
        ]);

        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(30);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(10);
        $sheet->getColumnDimension('K')->setWidth(20);
        $sheet->getColumnDimension('L')->setWidth(12);
        $sheet->getColumnDimension('M')->setWidth(20);

        $highestRow = $sheet->getHighestRow();
        if ($highestRow > 6) {
            $sheet->getStyle('A7:M'.$highestRow)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }

        $sheet->getStyle('A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('I')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('J')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('L')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    public function properties(): array
    {
        return [
            'creator' => config('app.name'),
            'title' => 'Laporan Retur ke Supplier',
            'description' => 'Laporan Retur ke Supplier Periode '.$this->mulai.' - '.$this->selesai,
        ];
    }
}
