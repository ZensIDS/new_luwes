<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'role',
        'status',
        'email',
        'alamat',
        'no_telp',
        'password',
        'limit_discount',
        'outlet_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // public function cart()
    // {
    //     return $this->belongsToMany(Product::class, 'user_cart')->withPivot('qty', 'serial_number', 'stock_id');
    // }

    // public function wishlist()
    // {
    //     return $this->belongsToMany(Product::class, 'user_wishlist')->withPivot('qty', 'name', 'customer_id', 'outlet_id');
    // }

    // public function reviews()
    // {
    //     return $this->hasMany(Review::class);
    // }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
