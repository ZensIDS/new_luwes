# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Start development servers
php artisan serve          # Laravel dev server
npm run dev               # Vite asset bundler (HMR)

# Database
php artisan migrate
php artisan migrate:fresh --seed

# Code style (Laravel Pint)
./vendor/bin/pint          # Fix all files
./vendor/bin/pint app/     # Fix specific directory

# Tests
php artisan test
php artisan test --filter=TestName   # Run a single test
php artisan test tests/Feature/      # Run a test suite

# Cache management
php artisan optimize:clear
```

## Architecture Overview

This is a **Laravel 9 warehouse/POS management system** for a multi-outlet retail business. The system tracks stock through a complete supply chain from supplier to outlet.

### Roles

User roles are stored as a plain `role` column on `users` (no package). Roles: `superadmin`, `admin`, `kasir`, `customer`. Route groups use `middleware(['role:kasir|admin|superadmin'])`. The `RoleMiddleware` handles redirection based on role.

### Core Stock Flow

The warehouse flow is the system's critical path (documented in README.md):

1. **Pembelian** (Purchase Order) → `is_published = false` → stock enters `StockPembelian` (staging)
2. **Publish Pembelian** → stock moves from `StockPembelian` → `Stock` (global warehouse pool), HPP calculated via weighted average (`Product::calculateHPP`), `Kas` debited
3. **RequestOrder** (outlet requests stock) → warehouse verifies → stock reserved (`Stock::reserve()`)
4. **PickingList** → generated from approved RequestOrder → FIFO picking
5. **DeliveryOrder** → generated from completed PickingList → on `send`, `Stock::allocate()` reduces global stock, `OwnerStock` created/updated for outlet
6. Outlet confirms receipt → `delivered`

### Key Models

| Model | Purpose |
|-------|---------|
| `Stock` | Global warehouse pool. Has `qty`, `qty_reserved`, `qty_available` (computed). Methods: `reserve()`, `unreserve()`, `allocate()` |
| `StockPembelian` | Staging stock before Pembelian is published |
| `OwnerStock` | Per-outlet stock, created when DeliveryOrder is sent |
| `StockMovement` | Audit log of all stock in/out events |
| `StockAdjustment` | Stock opname adjustments |
| `Pembelian` | Purchase order to supplier. `is_published` gates stock commitment |
| `RequestOrder` | Outlet's request to warehouse |
| `PickingList` / `PickingListItem` | Warehouse picking tasks |
| `DeliveryOrder` / `DeliveryOrderItem` | Shipment from warehouse to outlet |
| `Penjualan` / `PenjualanItem` | Sales transactions (POS side) |
| `RefundPembelian` | Returns to supplier or from outlet (type-based) |

### Stock Quantity Fields

`Stock` has three quantity fields that must stay consistent:
- `qty` — physical quantity on hand
- `qty_reserved` — reserved for approved RequestOrders (not yet shipped)
- `qty_available` — computed or maintained as `qty - qty_reserved`

### HPP (Cost Price) Calculation

`Product::calculateHPP($newQty, $newPrice)` uses a weighted average. It is called during `Pembelian::publish()`. The `harga_beli` on `Product` reflects the current weighted average cost.

### Frontend Stack

Blade templates with **Alpine.js** (inline interactivity), **Bootstrap 5**, and **Tailwind CSS**. React is installed (`@vitejs/plugin-react`) and available but used minimally. Vite compiles `resources/sass/app.scss` and `resources/js/app.js`.

### Exports / Imports

- `app/Exports/` — Maatwebsite Excel export classes (one per report type)
- `app/Imports/` — Excel import classes for Suppliers, Products, Categories
- PDF reports use `barryvdh/laravel-dompdf` via `LaporanController`

### Activity Logging

`Stock` and `Product` models use `spatie/laravel-activitylog` (`LogsActivity` trait). Changes are logged with `logOnlyDirty()` — only dirty attributes are recorded.

### Naming Conventions

Indonesian business terms are used throughout:
- **Pembelian** = Purchase (from supplier)
- **Penjualan** = Sale (to customer/outlet)
- **Pengeluaran** = Expense
- **Laporan** = Report
- **Kas** = Cash/Fund account
- **Gudang** = Warehouse
- **Retur/Refund** = Return
- **Penerimaan** = Goods receipt
