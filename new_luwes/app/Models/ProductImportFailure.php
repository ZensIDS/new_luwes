<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImportFailure extends Model
{
    protected $fillable = [
        'product_import_id',
        'row_number',
        'product_code',
        'message',
        'row_data',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];

    public function productImport(): BelongsTo
    {
        return $this->belongsTo(ProductImport::class);
    }
}
