#!/bin/bash
set -e

echo "=== 1. Restore file domain TOKO yang salah ikut terhapus (dari HEAD/beta) ==="
git checkout HEAD -- \
  "app/Http/Controllers/CampaignController.php" \
  "app/Http/Controllers/CashierSessionController.php" \
  "app/Http/Controllers/OutletPriceController.php" \
  "app/Http/Controllers/OutletPurchaseController.php" \
  "app/Http/Controllers/PriceCheckerController.php" \
  "app/Http/Controllers/PromotionController.php" \
  "app/Http/Controllers/RefundPenjualanController.php" \
  "app/Http/Requests/CampaignRequest.php" \
  "app/Http/Requests/OutletPriceRequest.php" \
  "app/Http/Requests/ProfileUpdateRequest.php" \
  "app/Http/Requests/PromotionRequest.php" \
  "app/Models/CashierDrawerEntry.php" \
  "app/Models/CashierSession.php" \
  "app/Models/CashierShift.php" \
  "app/Models/OutletPrice.php" \
  "app/Models/OutletPurchase.php" \
  "app/Models/OutletPurchaseItem.php" \
  "app/Models/Promotion.php" \
  "app/Models/PromotionApplication.php" \
  "app/Models/PromotionBonus.php" \
  "app/Models/PromotionProduct.php" \
  "app/Models/RefundPenjualan.php" \
  "app/Models/RefundPenjualanItem.php" \
  "app/Models/VoucherRedemption.php" \
  "app/Services/CashierSaleService.php" \
  "app/Services/OutletStockService.php" \
  "app/Services/PriceCalculator.php" \
  "app/Services/PromotionService.php" \
  "app/Support/IndonesianNumber.php" \
  "app/Support/OutletAccess.php" \
  "database/migrations/2026_09_03_000001_create_outlet_prices_and_voucher_redemptions.php" \
  "database/migrations/2026_09_03_000002_add_pos_and_outlet_stock_fields.php" \
  "database/migrations/2026_09_03_000003_create_outlet_purchases.php" \
  "database/migrations/2026_09_05_000003_create_promotions_tables.php" \
  "database/migrations/2026_09_13_000001_add_scope_and_bonus_rules.php" \
  "database/migrations/2026_09_17_000001_add_payment_reference_to_penjualans_table.php" \
  "database/migrations/2026_09_18_000001_add_tax_and_outlet_adjustment_to_prices.php" \
  "database/migrations/2026_09_19_000001_repair_product_columns.php" \
  "database/migrations/2026_09_19_000002_create_cashier_sessions_and_drawer_entries.php" \
  "database/migrations/2026_09_19_000003_add_cashier_session_to_penjualans.php" \
  "database/migrations/2026_09_21_000001_create_refund_penjualans_tables.php" \
  "database/migrations/2026_09_23_000001_make_refund_penjualan_invoice_optional.php" \
  "database/migrations/2026_09_24_000001_add_shift_cashiers_to_cashier_sessions.php" \
  "database/migrations/2026_09_24_000002_create_cashier_shifts_table.php" \
  "database/seeders/CashierStockSeeder.php" \
  "docs/label-harga-thermal-80mm-label-harga.html" \
  "docs/plan-improvement.md" \
  "docs/revisi_18_09_2026.md" \
  "docs/revisi_24_09_2026.md" \
  "docs/struk-thermal-80mm-penjualan.html" \
  "docs/struk-thermal-80mm.html" \
  "docs/table-cashier-penjualan.md" \
  "resources/js/components/ReactSelectField.jsx" \
  "resources/views/campaigns/form.blade.php" \
  "resources/views/cashier/history.blade.php" \
  "resources/views/cashier/print-products.blade.php" \
  "resources/views/components/app-layout.blade.php" \
  "resources/views/outlet-prices/form.blade.php" \
  "resources/views/outlet-prices/index.blade.php" \
  "resources/views/outlet-purchases/create.blade.php" \
  "resources/views/outlet-purchases/index.blade.php" \
  "resources/views/outlet-purchases/show.blade.php" \
  "resources/views/price-checker/index.blade.php" \
  "resources/views/promotions/form.blade.php" \
  "resources/views/promotions/index.blade.php" \
  "resources/views/refundPenjualans/create.blade.php" \
  "resources/views/refundPenjualans/index.blade.php" \
  "resources/views/refundPenjualans/show.blade.php" \
  "tests/Unit/IndonesianNumberTest.php" \
  "tests/Unit/PriceCalculatorTest.php" \
  "tests/Unit/PromotionServiceTest.php"

echo "=== 2. Perbaiki file yang statusnya benar di index tapi hilang fisik di disk ==="
git checkout -- \
  "resources/views/layouts/app.blade.php" \
  "resources/views/layouts/base.blade.php" \
  "resources/views/layouts/guest.blade.php" \
  "resources/views/layouts/market/footer.blade.php" \
  "resources/views/layouts/market/header.blade.php" \
  "resources/views/layouts/market/slider.blade.php" \
  "resources/views/layouts/master.blade.php" \
  "resources/views/layouts/navigation.blade.php" \
  "resources/views/outlets/create.blade.php" \
  "resources/views/outlets/edit.blade.php" \
  "resources/views/outlets/index.blade.php" \
  "resources/views/pembelians/index.blade.php" \
  "resources/views/pembelians/pembelian_pdf.blade.php" \
  "resources/views/pembelians/penerimaan-index.blade.php" \
  "resources/views/pembelians/penerimaan.blade.php" \
  "resources/views/penjualan/create.blade.php" \
  "resources/views/penjualan/marketplace.blade.php" \
  "resources/views/penjualan/print.blade.php" \
  "resources/views/penjualan/show.blade.php" \
  "resources/views/refundPembelians/show.blade.php" \
  "resources/views/refundPembelians/terima.blade.php" \
  "resources/views/vouchers/create.blade.php" \
  "resources/views/vouchers/edit.blade.php" \
  "resources/views/vouchers/show.blade.php"

echo "=== 3. Rapikan file currency-input.js (untracked tapi seharusnya tetap ada) ==="
git add "resources/js/currency-input.js"

echo "=== 4. Bersihkan sisa folder bantuan dari saya (bukan bagian project) ==="
git status --short | grep "^??" | grep -E "resolved-files/|\.DS_Store" || true

echo ""
echo "SELESAI. Sekarang jalankan 'git status' lagi untuk cek hasilnya."