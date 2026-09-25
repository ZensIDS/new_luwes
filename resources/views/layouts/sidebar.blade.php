<aside class="main-sidebar">
    <section class="sidebar">

        <div class="user-panel">
            <div class="pull-left image">
                <img src="{{ $companyLogo }}" class="img-circle" alt="Logo"><br>
            </div>
            <div class="pull-left info">
                <p>{{ Auth::user()?->name }}</p>
                <p>{{ Auth::user()?->role }}</p>
            </div>
        </div>

    @auth
    @php $role = auth()->user()->role; @endphp
    <ul class="sidebar-menu">

        {{-- Dashboard --}}
        <li class="{{ request()->is('dashboard*') ? 'active' : '' }}">
            <a href="/dashboard"><i class="fa fa-tachometer"></i><span>Dashboard</span></a>
        </li>
        @if (in_array($role, ['staff-outlet', 'kasir']))
            <li class="{{ request()->is('penjualan*') || request()->routeIs('outlet.show') ? 'active' : '' }}">
                <a href="{{ route('penjualan.create') }}"><i class="fa fa-shopping-cart"></i><span>Kasir POS</span></a>
            </li>
        @endif

        @if (in_array($role, ['superadmin', 'admin-gudang']))
        <li class="sidebar-divider"><span>PENGATURAN &amp; DATA UMUM</span></li>
        @endif

        {{-- Setting (superadmin only) --}}
        @if ($role === 'superadmin')
        <li class="{{ request()->is('setting*') ? 'active' : '' }}">
            <a href="/setting"><i class="fa fa-gear"></i><span>Setting</span></a>
        </li>
        @endif

        {{-- User (superadmin: Admin+Supplier | admin-gudang: Supplier only) --}}
        @if (in_array($role, ['superadmin', 'admin-gudang']))
        <li class="treeview {{ request()->is('admin*') || request()->is('supplier*') ? 'active' : '' }}">
            <a href="#"><i class="fa fa-users"></i><span>User</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu">
                @if ($role === 'superadmin')
                <li class="{{ request()->is('admin*') ? 'active' : '' }}">
                    <a href="/admin"><i class="fa fa-user-secret"></i><span>Admin</span></a>
                </li>
                @endif
                <li class="{{ request()->is('supplier*') ? 'active' : '' }}">
                    <a href="/supplier"><i class="fa fa-archive"></i><span>Supplier</span></a>
                </li>
            </ul>
        </li>
        @endif

        {{-- Outlet (superadmin only) --}}
        @if ($role === 'superadmin')
        <li class="{{ in_array(Route::currentRouteName(), ['outlet.index', 'outlet.create', 'outlet.edit']) ? 'active' : '' }}">
            <a href="/outlet"><i class="fa fa-bank"></i><span>Outlet/Toko</span></a>
        </li>
        @endif

        @if (in_array($role, ['superadmin', 'admin-gudang', 'owner']))
        <li class="sidebar-divider"><span>GUDANG</span></li>
        @endif

        {{-- Produk (superadmin, admin-gudang) --}}
        @if (in_array($role, ['superadmin', 'admin-gudang']))
        <li class="treeview {{ request()->is('category-product*') || request()->is('product*') ? 'active' : '' }}">
            <a href="#"><i class="fa fa-archive"></i><span>Produk</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu">
                <li class="{{ request()->is('category-product*') ? 'active' : '' }}">
                    <a href="/category-product"><i class="fa fa-tags"></i><span>Kategori</span></a>
                </li>
                <li class="{{ request()->is('product*') ? 'active' : '' }}">
                    <a href="/product"><i class="fa fa-archive"></i><span>Produk</span></a>
                </li>
            </ul>
        </li>
        @endif

        {{-- Pembelian (superadmin, admin-gudang) --}}
        @if (in_array($role, ['superadmin', 'admin-gudang']))
        <li class="treeview {{ request()->is('pembelian*') || request()->is('penerimaan*') ? 'active' : '' }}">
            <a href="#"><i class="fa fa-shopping-cart"></i><span>Pembelian</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu">
                <li class="{{ request()->is('pembelian*') && !request()->is('pembelian/*/penerimaan') ? 'active' : '' }}">
                    <a href="/pembelian"><i class="fa fa-file-text-o"></i><span>PO</span></a>
                </li>
                <li class="{{ request()->is('penerimaan*') || request()->is('pembelian/*/penerimaan') ? 'active' : '' }}">
                    <a href="/penerimaan"><i class="fa fa-download"></i><span>Penerimaan Barang</span></a>
                </li>
            </ul>
        </li>
        @endif

        {{-- Stok Gudang (superadmin, admin-gudang, owner) --}}
        @if (in_array($role, ['superadmin', 'admin-gudang', 'owner']))
        <li class="treeview {{ request()->is('stock*') ? 'active' : '' }}">
            <a href="#"><i class="fa fa-cubes"></i><span>Stok Gudang</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu">
                <li class="{{ request()->routeIs('stock.index') ? 'active' : '' }}">
                    <a href="/stock"><i class="fa fa-cubes"></i><span>Stok Gudang</span></a>
                </li>
                <li class="{{ request()->routeIs('stock.kartu') ? 'active' : '' }}">
                    <a href="/stock-kartu"><i class="fa fa-cube"></i><span>Kartu Stok Gudang</span></a>
                </li>
                <li class="{{ request()->routeIs('stock.opname') ? 'active' : '' }}">
                    <a href="/stock-opname"><i class="fa fa-cube"></i><span>Stock Opname Gudang</span></a>
                </li>
            </ul>
        </li>
        @endif

        <li class="sidebar-divider"><span>TOKO / OUTLET</span></li>

        {{-- Permintaan Barang --}}
        @if (in_array($role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet']))
        @php
            $permintaanActive = request()->is('request-orders*') || request()->is('picking-lists*')
                || request()->is('delivery-orders*') || request()->is('outlet-purchases*');
        @endphp
        <li class="treeview {{ $permintaanActive ? 'active' : '' }}">
            <a href="#"><i class="fa fa-exchange"></i><span>Permintaan Barang</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu">
                @if (in_array($role, ['superadmin', 'staff-outlet', 'admin-gudang']))
                <li class="{{ request()->is('request-orders*') ? 'active' : '' }}">
                    <a href="/request-orders"><i class="fa fa-cube"></i><span>Outlet Minta Gudang</span></a>
                </li>
                @endif
                @if (in_array($role, ['superadmin', 'admin-gudang', 'staff-outlet']))
                {{--  <li class="{{ request()->is('picking-lists*') ? 'active' : '' }}">
                    <a href="/picking-lists"><i class="fa fa-list-ol"></i><span>Picking &amp; Packing</span></a>
                </li>  --}}
                <li class="{{ request()->is('delivery-orders*') ? 'active' : '' }}">
                    <a href="/delivery-orders"><i class="fa fa-truck"></i><span>Pengiriman Toko</span></a>
                </li>
                @endif
                @if (in_array($role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet']))
                <li class="{{ request()->is('outlet-purchases*') ? 'active' : '' }}">
                    <a href="{{ route('outlet-purchases.index') }}"><i class="fa fa-shopping-cart"></i><span>Belanja Langsung → Stock Toko</span></a>
                </li>
                @endif
            </ul>
        </li>
        @endif

        {{-- Stok Toko (superadmin, admin-gudang, owner, staff-outlet, kasir) --}}
        @if (in_array($role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet', 'kasir']))
        <li class="treeview {{ request()->is('owner-stock*') || request()->is('owner-stocks*') ? 'active' : '' }}">
            <a href="#"><i class="fa fa-cubes"></i><span>Stok Toko</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu">
                <li class="{{ request()->routeIs('owner-stocks.index') ? 'active' : '' }}">
                    <a href="{{ route('owner-stocks.index') }}"><i class="fa fa-cubes"></i><span>Stock Toko</span></a>
                </li>
                <li class="{{ request()->routeIs('owner-stocks.kartu') ? 'active' : '' }}">
                    <a href="{{ route('owner-stocks.kartu') }}"><i class="fa fa-cube"></i><span>Kartu Stock Toko</span></a>
                </li>
                <li class="{{ request()->routeIs('owner-stock-opname') ? 'active' : '' }}">
                    <a href="{{ route('owner-stock-opname') }}"><i class="fa fa-cube"></i><span>Opname Toko</span></a>
                </li>
            </ul>
        </li>
        @endif

        @if (in_array($role, ['superadmin', 'admin-gudang', 'owner']))
        <li class="treeview {{ request()->is('outlet-prices*') || request()->is('voucher*') || request()->is('promotion*') || request()->is('campaign*') ? 'active' : '' }}">
            <a href="#"><i class="fa fa-tags"></i><span>Voucher &amp; Harga</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu">
                <li class="{{ request()->is('voucher*') || request()->is('promotion*') || request()->is('campaign*') ? 'active' : '' }}"><a href="{{ route('voucher.index') }}"><i class="fa fa-ticket"></i><span>Voucher</span></a></li>
                <li class="{{ request()->is('outlet-prices*') ? 'active' : '' }}"><a href="{{ route('outlet-prices.index') }}"><i class="fa fa-money"></i><span>Master Harga Jual</span></a></li>
            </ul>
        </li>
        @endif

        @if (in_array($role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet', 'kasir']))
        <li class="{{ request()->is('penjualan*') ? 'active' : '' }}">
            <a href="{{ route('penjualan.index') }}"><i class="fa fa-file-text"></i><span>Penjualan</span></a>
        </li>
        @endif

        <li class="sidebar-divider"><span>RETUR &amp; LAPORAN</span></li>

        {{-- Retur Barang --}}
        @if (in_array($role, ['superadmin', 'admin-gudang', 'owner', 'staff-outlet', 'kasir']))
        @php
            $returBarangActive = request()->is('refundPembelian*') || request()->is('refundPenjualan*');
        @endphp
        <li class="treeview {{ $returBarangActive ? 'active' : '' }}">
            <a href="#"><i class="fa fa-undo"></i><span>Retur Barang</span><i class="fa fa-angle-left pull-right"></i></a>
            <ul class="treeview-menu">
                @if (in_array($role, ['superadmin', 'admin-gudang']))
                <li class="{{ request()->is('refundPembelian*') && request()->get('type') === 'gudang_ke_supplier' ? 'active' : '' }}">
                    <a href="/refundPembelian?type=gudang_ke_supplier"><i class="fa fa-undo"></i><span>Retur Barang Gudang ke Supplier</span></a>
                </li>
                @endif
                @if (in_array($role, ['superadmin', 'staff-outlet']))
                <li class="{{ request()->is('refundPembelian*') && request()->get('type') === 'outlet_ke_gudang' ? 'active' : '' }}">
                    <a href="/refundPembelian?type=outlet_ke_gudang"><i class="fa fa-undo"></i><span>Retur Barang Toko ke Gudang</span></a>
                </li>
                @endif
                <li class="{{ request()->is('refundPenjualan*') ? 'active' : '' }}">
                    <a href="{{ route('refundPenjualan.index') }}"><i class="fa fa-exchange"></i><span>Ganti Barang Penjualan</span></a>
                </li>
            </ul>
        </li>
        @endif

        {{-- Laporan --}}
        @if (in_array($role, ['superadmin', 'admin-gudang', 'staff-outlet', 'owner']))
        <li class="{{ in_array(Route::currentRouteName(), ['laporan.index', 'laporan.outlet.index']) ? 'active' : '' }}">
            <a href="/laporan"><i class="fa fa-file-excel-o"></i><span>Laporan</span></a>
        </li>
        @endif

    </ul>
    @endauth
    </section>
</aside>
