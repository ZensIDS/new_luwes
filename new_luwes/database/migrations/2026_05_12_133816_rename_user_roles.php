<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('users')->where('role', 'admin')->update(['role' => 'admin-gudang']);
        DB::table('users')->where('role', 'outlet')->update(['role' => 'staff-outlet']);
        DB::table('users')->where('role', 'kasir')->update(['role' => 'staff-outlet']);
    }

    public function down()
    {
        DB::table('users')->where('role', 'admin-gudang')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'staff-outlet')->update(['role' => 'outlet']);
    }
};
