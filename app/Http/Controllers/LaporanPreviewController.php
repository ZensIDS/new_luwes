<?php

namespace App\Http\Controllers;

use App\Services\ExcelPreviewRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Preview laporan (Laporan Toko) tanpa mengunduh.
 *
 * Menjalankan method export yang SAMA dengan tombol "Unduh Excel" (filter, validasi, hak akses, dan isi sama persis),
 * lalu file Excel yang dihasilkan dibaca dan dikirim sebagai tabel HTML (baris pertama saja).
 *
 * Route: GET /laporan/outlet/preview/{report}?<filter yang sama dengan export>
 */
class LaporanPreviewController extends Controller
{
    /** id laporan (sama dengan id kartu di halaman laporan) => [controller, method] */
    private const REPORTS = [
        'outlet_penjualan' => [LaporanController::class, 'exportOutletPenjualan'],
        'outlet_sell_out_brand' => [LaporanController::class, 'exportOutletSellOutBrand'],
        'outlet_kassa' => [LaporanController::class, 'exportOutletKassa'],
        'outlet_rafaksi' => [LaporanController::class, 'exportOutletRafaksi'],
        'outlet_retur' => [LaporanController::class, 'exportOutletRetur'],
        'outlet_stok_harian' => [LaporanStokHarianController::class, '__invoke'],
        'outlet_all_stock' => [LaporanController::class, 'exportOutletAllStock'],
        'outlet_minimal_stock' => [LaporanController::class, 'exportOutletMinimalStock'],
        'pr' => [LaporanController::class, 'exportPR'],
        'retur_outlet' => [LaporanController::class, 'exportReturOutlet'],
    ];

    public static function supports(string $id): bool
    {
        return isset(self::REPORTS[$id]);
    }

    public function show(Request $request, string $report, ExcelPreviewRenderer $renderer): JsonResponse
    {
        abort_unless(self::supports($report), 404);

        // Role kasir hanya boleh Sell Out Brand.
        abort_if($request->user()?->role === 'kasir' && $report !== 'outlet_sell_out_brand', 403, 'Laporan ini tidak tersedia untuk role kasir.');

        // Sama dengan route export-nya: Laporan Kassa hanya untuk superadmin / owner / staff-outlet.
        if ($report === 'outlet_kassa') {
            abort_unless(in_array($request->user()?->role, ['superadmin', 'owner', 'staff-outlet'], true), 403, 'Laporan ini tidak tersedia untuk role Anda.');
        }

        [$class, $method] = self::REPORTS[$report];
        $response = app()->call([app($class), $method]);

        if (! $response instanceof BinaryFileResponse) {
            abort(422, 'Preview tidak tersedia untuk laporan ini.');
        }

        $path = $response->getFile()->getPathname();

        try {
            $sheets = $renderer->render($path);
        } finally {
            @unlink($path);
        }

        return response()->json([
            'sheets' => $sheets,
            'max_rows' => ExcelPreviewRenderer::MAX_ROWS,
        ]);
    }
}
