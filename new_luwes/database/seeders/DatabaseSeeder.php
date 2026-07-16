<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Pembelian;
use App\Models\Pengeluaran;
use App\Models\Product;
use App\Models\Slider;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'name' => 'superadmin',
            'username' => 'superadmin@mailinator.com',
            'role' => 'superadmin',
            'status' => 'active',
            'email' => 'superadmin@mailinator.com',
            'alamat' => 'magelang',
            'no_telp' => '+62'.str_pad(3, 10, '0', STR_PAD_LEFT),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', //password
            'remember_token' => Str::random(10),
        ]);
        // User::create([
        //     'name' => 'customer',
        //     'username' => 'customer@mailinator.com',
        //     'role' => 'customer',
        //     'status' => 'active',
        //     'email' => 'customer@mailinator.com',
        //     'alamat' => 'magelang',
        //     'no_telp' => '+62'.str_pad(5, 10, '0', STR_PAD_LEFT),
        //     'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        //     'remember_token' => Str::random(10),
        // ]);

        // for ($i = 1; $i <= 3; $i++) {
        //     Slider::create([
        //         'status' => ($i % 2 == 0) ? 'active' : 'non-active',
        //         'type' => ($i % 2 == 0) ? 'default' : 'link',
        //         'desc' => 'Description '.$i,
        //         'pic' => 'pic'.$i,
        //     ]);
        // }

        // for ($i = 1; $i <= 3; $i++) {
        //     Bank::create([
        //         'name' => 'Bank '.$i,
        //         'name_rek' => 'Name Rek '.$i,
        //         'no_rek' => str_pad($i, 10, '0', STR_PAD_LEFT),
        //         'pic' => 'pic'.$i,
        //     ]);
        // }

        for ($i = 1; $i <= 3; $i++) {
            Outlet::create([
                'logo' => 'logo'.$i,
                'name' => 'Outlet '.$i,
                'alamat' => 'Address '.$i,
                'npwp' => 'NPWP '.$i,
                'slogan' => 'Slogan '.$i,
                'desc' => 'Description '.$i,
                'footer' => 'Footer '.$i,
            ]);
        }

        for ($i = 1; $i <= 5; $i++) {
            Supplier::create([
                'name' => 'Supplier '.$i,
                'alamat' => 'Address '.$i,
                'no_telp' => '+62'.str_pad($i, 10, '0', STR_PAD_LEFT),
            ]);
        }

        for ($i = 1; $i <= 7; $i++) {
            Category::create([
                'name' => 'Category '.$i,
                'type' => ($i % 2 == 0) ? 'product' : 'pengeluaran',
            ]);
        }

        for ($i = 1; $i <= 3; $i++) {
            Product::create([
                'pic' => 'pic'.$i,
                'code' => 'code'.$i,
                'name' => 'Product '.$i,
                'category_id' => Category::where('type', 'product')->inRandomOrder()->first()->id,
                'desc' => 'Description '.$i,
                'warna' => ($i % 2 == 0) ? 'Red' : 'Blue',
                'ukuran' => ($i % 2 == 0) ? 'Large' : 'Small',
                // 'outlet_id' => Outlet::inRandomOrder()->first()->id,
                // 'supplier_id' => Supplier::inRandomOrder()->first()->id,
                // 'brand' => 'brand'.$i,
                // 'model' => 'model'.$i,
                // 'is_serialized' => rand(0, 1),
                'is_serialized' => 0,
                'harga_beli' => 0,
                'harga_jual' => round(rand(10000, 100000), -4),
                // 'diskon' => rand(0, 50),
                // 'berat' => round(rand(1, 10)),
            ]);
        }

        // for ($i = 1; $i <= 3; $i++) {
        //     Voucher::create([
        //         'name' => 'Voucher '.$i,
        //         'code' => 'Code'.$i,
        //         'type' => ($i % 2 == 0) ? 'nominal' : 'percentage',
        //         'limit' => round(rand(1, 10)),
        //         'value' => round(rand(10000, 100000), -4),
        //         'min_purchase' => round(rand(100000, 1000000), -5),
        //         'start_at' => now()->addDays($i),
        //         'end_at' => now()->addDays($i + 7),
        //         'desc' => 'Description '.$i,
        //     ]);
        // }

        // // for ($i = 1; $i <= 3; $i++) {
        // //     Pengeluaran::create([
        // //         'category_id' => Category::where('type', 'pengeluaran')->inRandomOrder()->first()->id,
        // //         'tanggal' => now()->addDays($i),
        // //         'biaya' => round(rand(10000, 100000), -4),
        // //         'desc' => 'Description '.$i,
        // //         'kas' => round(rand(10000, 100000), -4),
        // //         'jumlah' => round(rand(1, 10)),
        // //     ]);
        // // }

        // for ($i = 1; $i <= 3; $i++) {
        //     $pembelian = Pembelian::create([
        //         'code' => 'Code'.$i,
        //         'outlet_id' => Outlet::inRandomOrder()->first()->id,
        //         'supplier_id' => Supplier::inRandomOrder()->first()->id,
        //         'total' => round(rand(100000, 1000000), -5),
        //     ]);

        //     for ($j = 0; $j < 5; $j++) {
        //         Stock::create([
        //             'pembelian_id' => $pembelian->id,
        //             'product_id' => Product::inRandomOrder()->first()->id,
        //             'subtotal' => round(rand(10000, 100000), -4),
        //             'harga_beli' => round(rand(10000, 100000), -4),
        //             'qty' => round(rand(1, 10)),
        //             'created_at' => now(),
        //             'expired_at' => now()->addDays(rand(30, 365)),
        //         ]);
        //     }
        // }
    }
}
