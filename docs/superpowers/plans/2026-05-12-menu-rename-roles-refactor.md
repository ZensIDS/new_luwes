# Plan: Menu Rename, Laporan Titles & Role Refactor

**Date:** 2026-05-12  
**Source:** Role Aplikasi Warehouse Management System.pdf

---

## Overview

Two-part change:
1. **Sidebar + Laporan titles** — restructure sidebar menu groups and rename labels to match the PDF's menu spec.
2. **Roles** — rename the 4 role slugs throughout the codebase to match the PDF's Pembagian Hak Akses.

---

## Part 1 — Sidebar Restructure (`sidebar.blade.php`)

### New Menu Hierarchy (per PDF, page 1)

| New Group | Items | Route(s) |
|-----------|-------|----------|
| Dashboard | — | `/dashboard` |
| Setting | — | `/setting` |
| **User** | Admin, Supplier | `/admin`, `/supplier` |
| **Produk** | Kategori, Produk | `/category-product`, `/product` |
| **Stok** | Stok, Kartu stok, Stock opname | `/stock`, `/stock-kartu`, `/stock-opname` |
| **Pembelian** | Po, Penerimaan barang | `/pembelian`, `/penerimaan` |
| **Permintaan barang** | Outlet minta gudang, Picking & packing, Outbound | `/request-orders`, `/picking-lists`, `/delivery-orders` |
| **Retur barang** | Retur barang gudang, Retur barang outlet | `/refundPembelian?type=gudang`, `/refundPembelian?type=outlet` |
| Outlet | — | `/outlet` |
| Laporan | — | `/laporan` |

> Note: "Retur barang gudang" and "Retur barang outlet" both point to `/refundPembelian` — the existing resource handles both types. Two separate sidebar links with a type filter, or one link to the index which already lists both.

### Role-Based Visibility (per PDF, page 2)

| Menu Item | superadmin | admin-gudang | staff-outlet | owner |
|-----------|:---:|:---:|:---:|:---:|
| Dashboard | ✓ | ✓ | ✓ | ✓ |
| Setting | ✓ | — | — | — |
| User > Admin | ✓ | — | — | — |
| User > Supplier | ✓ | ✓ | — | — |
| Produk | ✓ | ✓ | — | — |
| Stok | ✓ | ✓ | — | ✓ (view) |
| Pembelian | ✓ | ✓ | — | — |
| Permintaan barang > Outlet minta gudang | ✓ | — | ✓ | — |
| Permintaan barang > Picking & packing | ✓ | ✓ | — | — |
| Permintaan barang > Outbound | ✓ | ✓ | — | — |
| Retur barang gudang | ✓ | ✓ | — | — |
| Retur barang outlet | ✓ | — | ✓ | — |
| Outlet | ✓ | — | — | — |
| Laporan (all) | ✓ | subset | subset | ✓ |

**Laporan visibility per role:**
- `admin-gudang`: Laporan PO, Laporan penerimaan barang, Laporan stok barang, Laporan stok opname & adjusment, Laporan picking & packing, Laporan pengiriman barang, Laporan retur ke supplier
- `staff-outlet`: Laporan retur outlet, Laporan PR (permintaan outlet)
- `owner`: semua laporan

> The laporan index page (`laporan/index.blade.php`) also needs role-gating on which cards are shown. This is covered in Part 1b below.

---

## Part 1b — Laporan Index Titles & Order (`laporan/index.blade.php`)

### Card Title Renames (per PDF order)

| # | Current Title | New Title |
|---|---------------|-----------|
| 1 | Laporan Purchase Order | **Laporan PO** |
| 2 | Laporan Purchase Request | **Laporan PR (Permintaan Outlet)** |
| 3 | Laporan Barang Masuk | Laporan Barang Masuk *(unchanged)* |
| 4 | Laporan Barang Keluar | Laporan Barang Keluar *(unchanged)* |
| 5 | Laporan Stok Barang | Laporan Stok Barang *(unchanged)* |
| 6 | Laporan Penerimaan Barang | Laporan Penerimaan Barang *(unchanged)* |
| 7 | Laporan Pengiriman Barang | Laporan Pengiriman Barang *(unchanged)* |
| 8 | Laporan Picking & Packing | Laporan Picking & Packing *(unchanged)* |
| 9 | Laporan Aktivitas Gudang | Laporan Aktivitas Gudang *(unchanged)* |
| 10 | Laporan Pembelian Barang | Laporan Pembelian Barang *(unchanged)* |
| 11 | Laporan Stok Opname & Adjusment | Laporan Stok Opname & Adjusment *(unchanged)* |
| 12 | Laporan Pergerakan & Kebutuhan Stok | Laporan Pergerakan & Kebutuhan Stok *(unchanged)* |
| 13 | Laporan Retur Ke Supplier | Laporan Retur Ke Supplier *(unchanged)* |
| 14 | Laporan Retur Outlet | Laporan Retur Outlet *(unchanged)* |

Order in PDF matches current order — no reordering needed.

**Role-gated card visibility** on the index page (add `@if(in_array(auth()->user()->role, [...]))` wrappers per the table above).

---

## Part 2 — Role Slug Rename

### Mapping

| Old Slug | New Slug | Notes |
|----------|----------|-------|
| `superadmin` | `superadmin` | No change |
| `admin` | `admin-gudang` | Warehouse admin |
| `outlet` | `staff-outlet` | Outlet staff |
| `kasir` | `staff-outlet` | POS is disabled; kasir maps to staff-outlet |

> `kasir` effectively has no distinct role in the current app (POS routes all commented out). Merging into `staff-outlet` is safe.

### Files to Update

#### 1. `app/Http/Middleware/RoleMiddleware.php`
- Update `$homeRoutes` map: replace `kasir`, `outlet`, `admin` keys with `admin-gudang`, `staff-outlet`; add `owner`
- Add `owner` → `'dashboard'` landing

#### 2. `routes/web.php`
- Line 37: `role:outlet|kasir|admin|superadmin` → `role:admin-gudang|staff-outlet|owner|superadmin`
- Line 239: `role:superadmin` — unchanged

#### 3. `app/Http/Controllers/AdminController.php`
- `create()` and `edit()` methods: update `$roles` array from `['superadmin','admin','kasir','outlet']` to `['superadmin','admin-gudang','staff-outlet','owner']`
- `update()` validation: `required_if:role,kasir|required_if:role,admin` → `required_if:role,staff-outlet|required_if:role,admin-gudang`

#### 4. `resources/views/layouts/sidebar.blade.php`
- Replace all role condition strings (`'kasir'`, `'admin'`, `'outlet'`) with new slugs
- Full restructure per Part 1 above

#### 5. Database Migration
```php
// Update existing users table role column values
DB::table('users')->where('role', 'admin')->update(['role' => 'admin-gudang']);
DB::table('users')->where('role', 'outlet')->update(['role' => 'staff-outlet']);
DB::table('users')->where('role', 'kasir')->update(['role' => 'staff-outlet']);
```
- Create: `database/migrations/YYYY_MM_DD_000000_rename_user_roles.php`

#### 6. Any other references (minor)
- `app/Http/Controllers/LaporanController.php` — check for hardcoded role checks
- `app/Http/Controllers/UserController.php` — check for role references
- `app/Models/User.php` — no role constants, no change needed

---

## Part 3 — Login Page Redesign + Indonesian Messages

### Goal
Replace the Breeze/Tailwind login page with a Bootstrap-based AdminLTE-style login page, and translate all auth/validation messages to Indonesian.

### Current State
- `resources/views/layouts/guest.blade.php` — Tailwind CDN + Breeze layout (`<x-guest-layout>`)
- `resources/views/auth/login.blade.php` — uses Breeze `<x-*>` components (Tailwind styled)
- `lang/en/auth.php` — English auth messages
- `lang/en/validation.php` — English validation messages

### Changes

#### 1. `resources/views/layouts/guest.blade.php` — Replace with Bootstrap layout

Remove Tailwind CDN. Load Bootstrap 3 CSS CDN + FontAwesome (same versions as AdminLTE theme) + local AdminLTE CSS already in `public/assets`. The page uses a full-screen centered card pattern (AdminLTE `login-page` body class).

```html
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name') }} — Login</title>
  <!-- Bootstrap 3 CDN -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
  <!-- AdminLTE (local) -->
  <link rel="stylesheet" href="{{ asset('assets/zenTheme/css/AdminLTE.min.css') }}">
</head>
<body class="hold-transition login-page">
  <div class="login-box">
    {{ $slot }}
  </div>
  <script src="https://code.jquery.com/jquery-2.2.4.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>
```

#### 2. `resources/views/auth/login.blade.php` — Rewrite as AdminLTE login card

Remove all `<x-*>` Breeze components. Use plain Bootstrap 3 HTML with AdminLTE `.login-box-body` pattern:

```html
<x-guest-layout>
<div class="login-box-body">
  <p class="login-box-msg">Warehouse Management System</p>

  @if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="form-group has-feedback {{ $errors->has('loginname') ? 'has-error' : '' }}">
      <input type="text" name="loginname" class="form-control"
             placeholder="Email / Username" value="{{ old('loginname') }}" required>
      <span class="fa fa-user form-control-feedback"></span>
      @error('loginname')
        <span class="help-block">{{ $message }}</span>
      @enderror
    </div>
    <div class="form-group has-feedback {{ $errors->has('password') ? 'has-error' : '' }}">
      <input type="password" name="password" class="form-control"
             placeholder="Password" required autocomplete="current-password">
      <span class="fa fa-lock form-control-feedback"></span>
      @error('password')
        <span class="help-block">{{ $message }}</span>
      @enderror
    </div>
    <div class="row">
      <div class="col-xs-8">
        <div class="checkbox icheck">
          <label><input type="checkbox" name="remember"> Ingat saya</label>
        </div>
      </div>
      <div class="col-xs-4">
        <button type="submit" class="btn btn-primary btn-block btn-flat">Masuk</button>
      </div>
    </div>
  </form>
</div>
</x-guest-layout>
```

> No Register link — the register route/page is not needed for this internal WMS app.

#### 3. `lang/en/auth.php` — Indonesian auth messages

```php
'failed'   => 'Email/username atau password salah.',
'password' => 'Password yang dimasukkan tidak benar.',
'throttle' => 'Terlalu banyak percobaan login. Silakan coba lagi dalam :seconds detik.',
```

#### 4. `lang/en/validation.php` — Indonesian validation messages (key ones)

Replace all English messages with Indonesian equivalents. Key messages used in this app:
- `required` → `'Kolom :attribute wajib diisi.'`
- `email` → `'Kolom :attribute harus berupa alamat email yang valid.'`
- `unique` → `':Attribute sudah digunakan.'`
- `same` → `'Kolom :attribute dan :other harus sama.'`
- `min.string` → `'Kolom :attribute minimal :min karakter.'`
- `max.string` → `'Kolom :attribute maksimal :max karakter.'`
- `required_if` → `'Kolom :attribute wajib diisi ketika :other bernilai :value.'`
- (and all other standard rules translated)

Also update `attributes` array at the bottom of the file to map field names to Indonesian labels (e.g. `'name' => 'Nama'`, `'email' => 'Email'`, `'password' => 'Password'`).

---

## Execution Order

1. Write migration for role renames
2. Update `RoleMiddleware.php`
3. Update `routes/web.php`
4. Update `AdminController.php` (roles array + validation)
5. Restructure `sidebar.blade.php` (new groups + role conditions)
6. Update `laporan/index.blade.php` (rename 2 titles + role-gated cards)
7. Rewrite `guest.blade.php` (Bootstrap layout)
8. Rewrite `auth/login.blade.php` (AdminLTE card style)
9. Update `lang/en/auth.php` + `lang/en/validation.php` (Indonesian)
10. Run migration: `php artisan migrate`

---

## Out of Scope (not changing now)

- Controller-level authorization (route middleware only guards route access, not actions within)
- "owner" read-only enforcement in controllers (the PDF says "view seluruh data tanpa edit" but enforcing this requires either policy rules or separate read-only controllers — deferred)
- Adding an `owner` sidebar "Monitoring stok" entry as a separate view (owner sees existing `/stock` index read-only for now)
- Other auth pages (register, forgot-password, reset-password) — not used in this internal app, but left in place
