<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartUserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KasController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PembelianController;
use App\Http\Controllers\PengeluaranController;
use App\Http\Controllers\PenjualanController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductMinimumAdjustmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\RefundPembelianController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SalesmanController;
use App\Http\Controllers\SliderController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::middleware(['role:admin-gudang|staff-outlet|owner|superadmin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/setting', [DashboardController::class, 'setting'])->name('setting');
    Route::post('/setting-store', [DashboardController::class, 'store'])->name('setting.store');
    Route::get('/get-customer/{penjualan_id}', [CustomerController::class, 'getCustomer']);
    Route::get('/get-penjualan/{outlet_id}', [PenjualanController::class, 'getPenjualan']);
    Route::get('/penjualan-detail/{penjualan_id}/items', [PenjualanController::class, 'getItems']);
    Route::get('/get-pembelian/{outlet_id}', [PembelianController::class, 'getPembelian']);
    Route::get('/pembelian-detail/{pembelian_id}/items', [PembelianController::class, 'getItems']);

    Route::get('/kasir', [UserController::class, 'kasir'])->name('kasir.index');
    Route::resource('/customer', CustomerController::class);
    Route::resource('/kas', KasController::class);
    Route::resource('/payment', PaymentMethodController::class);
    Route::resource('/outlet', OutletController::class);
    Route::get('/outlet/{outlet_id}/kas', [OutletController::class, 'getKas']);
    Route::resource('/supplier', SupplierController::class);
    Route::resource('/salesman', SalesmanController::class);
    Route::resource('/category', CategoryController::class);
    Route::get('/category-product', [CategoryController::class, 'indexProduct'])->name('category.product.index');
    Route::get('/category-product-create', [CategoryController::class, 'createProduct'])->name('category.product.create');
    Route::get('/category-product/{category}/edit', [CategoryController::class, 'editProduct'])->name('category.product.edit');

    Route::post('/product/minimum-adjustment', [ProductMinimumAdjustmentController::class, 'store'])
        ->name('product.minimum-adjustment.store');
    Route::resource('/product', ProductController::class);

    Route::resource('/stock', StockController::class);
    Route::resource('/voucher', VoucherController::class);
    Route::resource('/slider', SliderController::class);

    Route::resource('/pengeluaran', PengeluaranController::class);
    Route::get('/pembelian/cek-stok-produk', [PembelianController::class, 'getAllProducts'])->name('pembelian.all-products');
    Route::resource('/pembelian', PembelianController::class);
    Route::post('/pembelian/{pembelian}/owner-approve', [PembelianController::class, 'approveOwner'])->name('pembelian.owner-approve');
    Route::post('/pembelian/{pembelian}/owner-reject', [PembelianController::class, 'rejectOwner'])->name('pembelian.owner-reject');
    Route::get('/pembelian/{pembelian}/pembayaran/edit', [PembelianController::class, 'editPembayaran'])->name('pembelian.pembayaran.edit');
    Route::put('/pembelian/{pembelian}/pembayaran', [PembelianController::class, 'updatePembayaran'])->name('pembelian.pembayaran.update');
    Route::get('/pembelian/{pembelian}/publish', [PembelianController::class, 'publish'])->name('pembelian.publish');

    Route::get('/penerimaan', [PembelianController::class, 'penerimaanIndex'])->name('penerimaan.index');
    Route::get('/pembelian/{pembelian}/penerimaan', [PembelianController::class, 'penerimaan'])->name('pembelian.penerimaan');
    Route::post('/pembelian/{pembelian}/penerimaan', [PembelianController::class, 'storePenerimaan'])->name('pembelian.store-penerimaan');
    Route::post('/pembelian/{pembelian}/update-penerimaan', [PembelianController::class, 'updatePenerimaan'])->name('pembelian.update-penerimaan');
    Route::post('/pembelian/{pembelian}/penerimaan/save-item', [PembelianController::class, 'savePenerimaanItem'])
        ->name('pembelian.penerimaan.save-item');

    Route::get('/pembelian/{pembelian}/print', [PembelianController::class, 'print'])->name('pembelian.print');
    Route::get('/pembelian/{id}/destroy', [PembelianController::class, 'stockDestroy'])->name('pembelian.stock.destroy');
    Route::get('/supplier/{supplier}/products', [App\Http\Controllers\PembelianController::class, 'getProductsBySupplier'])->name('supplier.products');

    Route::get('/retur/supplier/{supplier}/products', [RefundPembelianController::class, 'getSupplierProducts'])->name('retur.supplier.products');
    Route::get('/retur/outlet/{outlet}/products', [RefundPembelianController::class, 'getOutletProducts'])->name('retur.outlet.products');

    Route::get('/refundPembelian/{refundPembelian}/terima', [RefundPembelianController::class, 'terimaForm'])->name('refundPembelian.terima.form');
    Route::post('/refundPembelian/{refundPembelian}/terima', [RefundPembelianController::class, 'terima'])->name('refundPembelian.terima');

    Route::resource('/refundPembelian', RefundPembelianController::class);

    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/pembelian/{id?}', [LaporanController::class, 'exportPembelian'])->name('laporan.pembelian');
    Route::get('/laporan/pickinglist/{id?}', [LaporanController::class, 'exportPickingList'])->name('laporan.pickinglist');
    Route::get('/laporan/request-order/{id?}', [LaporanController::class, 'exportRequestOrder'])->name('laporan.request-order');
    Route::get('/laporan/delivery-order/{id?}', [LaporanController::class, 'exportDeliveryOrder'])->name('laporan.delivery-order');
    Route::get('/laporan/kartu-stok/{id?}', [LaporanController::class, 'exportKartuStok'])->name('laporan.kartu-stok');
    Route::get('/laporan/stock-opname', [LaporanController::class, 'exportStockOpname'])->name('laporan.stock-opname');
    Route::get('/laporan/penerimaan/{pembelian}/{type?}', [LaporanController::class, 'exportPenerimaan'])->name('laporan.penerimaan');

    Route::get('/laporan/pembelian-supplier', [LaporanController::class, 'exportPembelianSupplier'])->name('laporan.pembelian-supplier');
    Route::get('/laporan/penjualan', [LaporanController::class, 'exportPenjualan'])->name('laporan.penjualan');
    Route::get('/laporan/penjualan-kasir', [LaporanController::class, 'exportPenjualanKasir'])->name('laporan.penjualan-kasir');
    Route::get('/laporan/penjualan-supplier', [LaporanController::class, 'exportPenjualanSupplier'])->name('laporan.penjualan-supplier');
    Route::get('/laporan/stock', [LaporanController::class, 'exportStock'])->name('laporan.stock');
    Route::get('/laporan/pengeluaran', [LaporanController::class, 'exportPengeluaran'])->name('laporan.pengeluaran');
    Route::get('/laporan/labarugi', [LaporanController::class, 'exportLabaRugi'])->name('laporan.labarugi');

    // Request Orders
    Route::resource('request-orders', App\Http\Controllers\RequestOrderController::class);
    Route::post('request-orders/{requestOrder}/update-stocks', [App\Http\Controllers\RequestOrderController::class, 'updateStocks'])->name('request-orders.update-stocks');
    Route::get('request-orders/{requestOrder}/verify', [App\Http\Controllers\RequestOrderController::class, 'verify'])->name('request-orders.verify');
    Route::post('request-orders/{requestOrder}/verify', [App\Http\Controllers\RequestOrderController::class, 'processVerification'])->name('request-orders.process-verification');

    // ── BARU: Proses & Kirim (1 halaman, tanpa verify & generate PL terpisah) ──
    Route::get('request-orders/{requestOrder}/process', [App\Http\Controllers\RequestOrderController::class, 'processView'])->name('request-orders.process');
    Route::post('request-orders/{requestOrder}/scan-pick', [App\Http\Controllers\RequestOrderController::class, 'scanPick'])->name('request-orders.scan-pick');
    Route::post('request-orders/{requestOrder}/complete-ship', [App\Http\Controllers\RequestOrderController::class, 'completeAndShip'])->name('request-orders.complete-ship');
    // ── END BARU ──

    // Picking Lists
    Route::resource('picking-lists', App\Http\Controllers\PickingListController::class);
    Route::post('request-orders/{requestOrder}/generate-picking', [App\Http\Controllers\PickingListController::class, 'generate'])->name('picking-lists.generate');
    Route::post('picking-lists/{pickingList}/start', [App\Http\Controllers\PickingListController::class, 'startPicking'])->name('picking-lists.start');
    Route::get('picking-lists/{pickingList}/pick', [App\Http\Controllers\PickingListController::class, 'pick'])->name('picking-lists.pick');
    Route::patch('picking-list-items/{item}', [App\Http\Controllers\PickingListController::class, 'updateItem'])->name('picking-list-items.update');
    Route::post('picking-lists/{pickingList}/complete', [App\Http\Controllers\PickingListController::class, 'complete'])->name('picking-lists.complete');
    Route::post('picking-lists/{pickingList}/bulk-update', [App\Http\Controllers\PickingListController::class, 'bulkUpdate'])->name('picking-lists.bulk-update');
    Route::patch('picking-lists/{pickingList}/picker-name', [App\Http\Controllers\PickingListController::class, 'updatePickerName'])->name('picking-lists.update-picker-name');

    // Delivery Orders
    Route::resource('delivery-orders', App\Http\Controllers\DeliveryOrderController::class);
    Route::post('picking-lists/{pickingList}/generate-do', [App\Http\Controllers\DeliveryOrderController::class, 'generate'])->name('delivery-orders.generate');
    Route::post('delivery-orders/{deliveryOrder}/send', [App\Http\Controllers\DeliveryOrderController::class, 'send'])->name('delivery-orders.send');
    Route::post('delivery-orders/{deliveryOrder}/receive', [App\Http\Controllers\DeliveryOrderController::class, 'receive'])->name('delivery-orders.receive');

    // Owner Stocks
    Route::get('owner-stocks', [App\Http\Controllers\OwnerStockController::class, 'index'])->name('owner-stocks.index');
    Route::get('owner-stocks/{owner}', [App\Http\Controllers\OwnerStockController::class, 'show'])->name('owner-stocks.show');

    Route::get('/product/{product}/price-history', [App\Http\Controllers\ProductController::class, 'priceHistory'])->name('product.price-history');
    Route::get('/stock/{stock}/history', [App\Http\Controllers\StockController::class, 'history'])->name('stock.history');

    // Stock Kartu
    Route::get('/stock-kartu', [App\Http\Controllers\StockController::class, 'kartu'])->name('stock.kartu');
    Route::get('/stock/kartu/data', [App\Http\Controllers\StockController::class, 'getKartuData'])->name('stock.kartu.data');

    // Stock Opname
    Route::get('/stock-opname', [App\Http\Controllers\StockController::class, 'opname'])->name('stock.opname');
    Route::get('/stock-opname/data', [App\Http\Controllers\StockController::class, 'getOpnameData'])->name('stock.opname.data');
    Route::post('/stock-opname/save', [App\Http\Controllers\StockController::class, 'saveOpname'])->name('stock.opname.save');
    Route::get('/stock-opname/export-template', [App\Http\Controllers\StockController::class, 'exportOpnameTemplate'])->name('stock.opname.export-template');

    // Supplier
    Route::get('suppliers/export', [SupplierController::class, 'export'])->name('supplier.export');
    Route::get('suppliers/export-template', [SupplierController::class, 'exportTemplate'])->name('supplier.export.template');
    Route::post('suppliers/import', [SupplierController::class, 'import'])->name('supplier.import');

    // Category
    Route::get('categories/product/export', [CategoryController::class, 'exportProduct'])->name('category.product.export');
    Route::get('categories/pengeluaran/export', [CategoryController::class, 'exportPengeluaran'])->name('category.pengeluaran.export');
    Route::get('categories/export-template', [CategoryController::class, 'exportTemplate'])->name('category.export.template');
    Route::post('categories/import', [CategoryController::class, 'import'])->name('category.import');

    // Product
    Route::get('products/export', [ProductController::class, 'export'])->name('product.export');
    Route::get('products/export-template', [ProductController::class, 'exportTemplate'])->name('product.export.template');
    Route::post('products/import', [ProductController::class, 'import'])->name('product.import');
    Route::get('products/import-statuses', [ProductController::class, 'importStatuses'])->name('product.import-statuses');
    Route::get('products/min-stock/export', [ProductController::class, 'exportMinStock'])->name('product.min-stock.export');
    Route::get('products/min-stock/export-template', [ProductController::class, 'exportMinStockTemplate'])->name('product.min-stock.export.template');
    Route::post('products/min-stock/import', [ProductController::class, 'importMinStock'])->name('product.min-stock.import');

    // Laporan PDF & Excel
    Route::get('laporan/pdf/po', [LaporanController::class, 'pdfPO'])->name('laporan.pdf.po');
    Route::get('laporan/pdf/pr', [LaporanController::class, 'pdfPR'])->name('laporan.pdf.pr');
    Route::get('laporan/pdf/barang-masuk', [LaporanController::class, 'pdfBarangMasuk'])->name('laporan.pdf.barang-masuk');
    Route::get('laporan/pdf/barang-keluar', [LaporanController::class, 'pdfBarangKeluar'])->name('laporan.pdf.barang-keluar');
    Route::get('laporan/pdf/stok', [LaporanController::class, 'pdfStok'])->name('laporan.pdf.stok');
    Route::get('laporan/pdf/kartu-stok/{id}', [LaporanController::class, 'pdfKartuStok'])->name('laporan.pdf.kartu-stok');
    Route::get('laporan/pdf/penerimaan', [LaporanController::class, 'pdfPenerimaanBarang'])->name('laporan.pdf.penerimaan');
    Route::get('laporan/pdf/penerimaan/{id}', [LaporanController::class, 'pdfPenerimaanSingle'])->name('laporan.pdf.penerimaan-single');
    Route::get('laporan/pdf/pengiriman', [LaporanController::class, 'pdfPengiriman'])->name('laporan.pdf.pengiriman');
    Route::get('laporan/pdf/picking', [LaporanController::class, 'pdfPicking'])->name('laporan.pdf.picking');
    Route::get('laporan/pdf/aktifitas', [LaporanController::class, 'pdfAktifitas'])->name('laporan.pdf.aktifitas');
    Route::get('laporan/pdf/pembelian', [LaporanController::class, 'pdfPembelianBarang'])->name('laporan.pdf.pembelian');
    Route::get('laporan/pdf/faktur-pembelian/{id}', [LaporanController::class, 'pdfFakturPembelian'])->name('laporan.pdf.faktur-pembelian');
    Route::get('laporan/pdf/opname', [LaporanController::class, 'pdfOpname'])->name('laporan.pdf.opname');
    Route::get('laporan/pdf/pergerakan', [LaporanController::class, 'pdfPergerakan'])->name('laporan.pdf.pergerakan');

    Route::get('laporan/export/po', [LaporanController::class, 'exportLaporanPO'])->name('laporan.export.po');
    Route::get('laporan/export/pr', [LaporanController::class, 'exportPR'])->name('laporan.export.pr');
    Route::get('laporan/export/barang-masuk', [LaporanController::class, 'exportBarangMasuk'])->name('laporan.export.barang-masuk');
    Route::get('laporan/export/barang-keluar', [LaporanController::class, 'exportBarangKeluar'])->name('laporan.export.barang-keluar');
    Route::get('laporan/export/penerimaan', [LaporanController::class, 'exportPenerimaanBarang'])->name('laporan.export.penerimaan');
    Route::get('laporan/export/pengiriman', [LaporanController::class, 'exportPengiriman'])->name('laporan.export.pengiriman');
    Route::get('laporan/export/picking', [LaporanController::class, 'exportPickingPacking'])->name('laporan.export.picking');
    Route::get('laporan/export/aktifitas', [LaporanController::class, 'exportAktifitas'])->name('laporan.export.aktifitas');
    Route::get('laporan/export/pembelian', [LaporanController::class, 'exportPembelianBarang'])->name('laporan.export.pembelian');
    Route::get('laporan/export/pergerakan', [LaporanController::class, 'exportPergerakan'])->name('laporan.export.pergerakan');

    Route::get('/laporan/retur-supplier', [LaporanController::class, 'exportReturSupplier'])->name('laporan.retur-supplier');
    Route::get('/laporan/retur-outlet', [LaporanController::class, 'exportReturOutlet'])->name('laporan.retur-outlet');

    Route::get('/laporan/retur-pembelian/{refundPembelian}/export', [LaporanController::class, 'exportReturPembelianSingle'])->name('laporan.retur-pembelian.single');
    Route::get('/laporan/retur-outlet/{refundPembelian}/export', [LaporanController::class, 'exportReturOutletSingle'])->name('laporan.retur-outlet.single');
    Route::get('/laporan/pdf/retur-pembelian/{refundPembelian}', [LaporanController::class, 'pdfReturPembelianSingle'])->name('laporan.pdf.retur-pembelian-single');
    Route::get('/laporan/pdf/retur-outlet/{refundPembelian}', [LaporanController::class, 'pdfReturOutletSingle'])->name('laporan.pdf.retur-outlet-single');

    Route::get('/laporan/pdf/retur-supplier', [LaporanController::class, 'pdfReturSupplier'])->name('laporan.pdf.retur-supplier');
    Route::get('/laporan/pdf/retur-outlet', [LaporanController::class, 'pdfReturOutlet'])->name('laporan.pdf.retur-outlet');
});

Route::middleware(['role:superadmin'])->group(function () {
    Route::resource('/admin', AdminController::class);
});

require __DIR__ . '/auth.php';

//* Artisan Commands
Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');
    return redirect('/login')->with(['success' => 'Optimization Berhasil']);
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return redirect('/login')->with(['success' => 'Optimization Berhasil']);
});
