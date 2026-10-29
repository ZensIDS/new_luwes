<?php

/*
|--------------------------------------------------------------------------
| Sinkronisasi Database Offline <-> Hosting
|--------------------------------------------------------------------------
|
| Sinkronisasi dua arah dijalankan HANYA dari PC server lokal (offline),
| karena hosting tidak bisa menjangkau PC di jaringan lokal, sedangkan PC
| lokal bisa menjangkau MySQL hosting (Remote MySQL).
|
| Server lokal : DB_SYNC_ENABLED=true + DB_SERVER_* diisi kredensial MySQL hosting.
| Hosting      : DB_SYNC_ENABLED=false, DB_SERVER_* dikosongkan.
|
| Kedua sisi WAJIB memakai DB_AUTO_INCREMENT_INCREMENT=2 dengan
| DB_AUTO_INCREMENT_OFFSET berbeda (lokal=1, hosting=2) supaya id baris
| baru yang dibuat di dua tempat tidak pernah bentrok.
|
*/

return [

    'enabled' => (bool) env('DB_SYNC_ENABLED', false),

    // Nama koneksi (config/database.php) untuk database hosting.
    'server_connection' => 'server',

    // Koneksi aplikasi lokal yang disinkronkan.
    'local_connection' => env('DB_CONNECTION', 'mysql'),

    // Jalan setiap N menit lewat scheduler (php artisan schedule:run di cron).
    'interval' => (int) env('DB_SYNC_INTERVAL', 2),

    // Ukuran blok id yang dibandingkan per checksum, dan ukuran batch insert.
    'chunk_size' => 1000,
    'batch_size' => 200,

    // Tabel yang tidak ikut disinkronkan (data teknis / per-mesin).
    'exclude_tables' => array_merge([
        'migrations',
        'jobs',
        'failed_jobs',
        'job_batches',
        'password_resets',
        'personal_access_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'cart_storage',
        'db_sync_runs',
        'db_sync_rows',
        'db_sync_conflicts',
    ], array_filter(explode(',', (string) env('DB_SYNC_EXCLUDE_TABLES', '')))),

    /*
    | Kolom penghitung: bila kolom ini berubah di DUA sisi sejak sinkron
    | terakhir, perubahannya dijumlahkan (lokal + hosting - nilai lama),
    | misal stok terjual di kasir offline dan stok masuk dari hosting.
    */
    'additive_columns' => [
        'stocks' => ['qty', 'qty_reserved'],
        'owner_stocks' => ['qty'],
        'kas' => ['nominal'],
        'promotions' => ['used_qty'],
        'products' => ['update_count'],
    ],

    // Kolom yang diambil nilai terbesarnya bila berubah di dua sisi.
    'max_columns' => [
        'invoice_sequences' => ['last_number'],
    ],

    /*
    | Kolom lain yang diubah di DUA sisi ke nilai berbeda:
    |  'newest' (default): menang data yang TERAKHIR diubah (updated_at, atau created_at bila tidak ada).
    |             Kolom yang hanya diubah di satu sisi tetap diambil dari sisi itu, jadi perubahan
    |             lokal & hosting digabung berurutan. Bila waktunya sama persis, menang sisi 'prefer'.
    |  'skip'   : baris tidak disinkron (lokal & hosting tetap dengan datanya masing-masing),
    |             muncul peringatan di dashboard, diselesaikan dengan
    |             php artisan dbsync:resolve {tabel} {id} --keep=local|server|newest
    */
    'conflict' => env('DB_SYNC_CONFLICT', 'newest'),

    // Bila updated_at sama persis, menang sisi ini ('local' atau 'server'). Server lokal = sumber utama.
    'prefer' => env('DB_SYNC_PREFER', 'local'),

    /*
    | Tabel log (hanya ditambah, tidak pernah diubah). Bila id yang sama berisi baris berbeda di lokal
    | & hosting (id bentrok, mis. sebelum DB_AUTO_INCREMENT_OFFSET diset), KEDUA baris disimpan:
    | baris hosting dipindah ke id baru, bukan ditimpa.
    */
    'append_only' => ['activity_log', 'stock_movements'],

    /*
    | Pengaman: bila satu kali sinkron akan menghapus lebih dari
    | max(min_rows, ratio x jumlah baris) di sebuah tabel (misal salah satu
    | database tidak sengaja dikosongkan), penghapusan ditahan sampai
    | dijalankan manual dengan: php artisan dbsync:run --force
    */
    'delete_guard' => [
        'min_rows' => 20,
        'ratio' => 0.25,
    ],

    /*
    | Modul untuk sinkron sebagian dari dashboard / php artisan dbsync:run --group=...
    | Tabel yang direferensikan lewat foreign key (mis. products -> categories, outlets)
    | otomatis ikut disinkron, jadi data yang saling terkait tidak tertinggal.
    | Tiap modul juga memuat tabel stok/kas yang ikut berubah oleh transaksinya.
    | Tabel yang tidak masuk modul mana pun muncul sebagai modul "Lainnya".
    */
    'groups' => [
        'master' => [
            'label' => 'Master Data (User, Outlet, Kategori, Supplier)',
            'tables' => ['users', 'outlets', 'categories', 'suppliers', 'banks', 'payment_methods', 'salesmans', 'sliders'],
        ],
        'produk_stok' => [
            'label' => 'Produk & Stok',
            'tables' => [
                'products', 'product_supplier', 'product_minimum_adjustments', 'outlet_prices', 'stocks', 'owner_stocks',
                'stock_movements', 'stock_adjustments', 'product_imports', 'product_import_failures', 'reviews',
            ],
        ],
        'pembelian' => [
            'label' => 'Pembelian & Retur Supplier',
            'tables' => [
                'pembelians', 'pembelian_products', 'pembelian_transactions', 'stock_pembelians', 'refund_pembelians',
                'refund_pembelian_items', 'outlet_purchases', 'outlet_purchase_items', 'stocks', 'owner_stocks', 'stock_movements', 'kas',
            ],
        ],
        'distribusi' => [
            'label' => 'Request Order, Picking & Delivery',
            'tables' => [
                'request_orders', 'request_order_items', 'request_order_notes', 'picking_lists', 'picking_list_items',
                'delivery_orders', 'delivery_order_items', 'stocks', 'owner_stocks', 'stock_movements',
            ],
        ],
        'penjualan' => [
            'label' => 'Penjualan & Kasir',
            'tables' => [
                'penjualans', 'penjualan_items', 'invoice_sequences', 'cashier_sessions', 'cashier_shifts', 'cashier_drawer_entries',
                'refund_penjualans', 'refund_penjualan_items', 'refunds', 'refund_items', 'transactions', 'user_cart', 'user_wishlist',
                'promotion_applications', 'voucher_redemptions', 'owner_stocks', 'stocks', 'stock_movements', 'kas',
            ],
        ],
        'promo' => [
            'label' => 'Promo & Voucher',
            'tables' => [
                'promotions', 'promotion_products', 'promotion_bonuses', 'promotion_outlets', 'promotion_applications',
                'vouchers', 'voucher_products', 'voucher_outlets', 'voucher_redemptions',
            ],
        ],
        'keuangan' => [
            'label' => 'Kas, Pengeluaran & Pendapatan Lain',
            'tables' => ['kas', 'pengeluarans', 'other_income_categories', 'other_incomes'],
        ],
        'log' => [
            'label' => 'Log Aktivitas',
            'tables' => ['activity_log'],
        ],
    ],

    // Riwayat sinkron disimpan sekian hari.
    'keep_runs_days' => 14,

];
