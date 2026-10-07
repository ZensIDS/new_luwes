<?php

namespace App\Models;

use Darryldecode\Cart\CartCollection;
use Illuminate\Database\Eloquent\Model;

class DatabaseStorage extends Model
{
    public function has($key)
    {
        return DatabaseStorageModel::find($key);
    }

    public function get($key)
    {
        // Cukup 1 query find() (sebelumnya has() + find() = 2 query per akses).
        $row = DatabaseStorageModel::find($key);

        return $row ? new CartCollection($row->cart_data) : [];
    }

    public function put($key, $value)
    {
        if ($row = DatabaseStorageModel::find($key)) {
            // update
            $row->cart_data = $value;
            $row->save();
        } else {
            DatabaseStorageModel::create([
                'id' => $key,
                'cart_data' => $value,
            ]);
        }
    }
}