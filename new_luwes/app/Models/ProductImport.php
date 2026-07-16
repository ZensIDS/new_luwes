<?php

namespace App\Models;

use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Bus;

class ProductImport extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'batch_id',
        'original_file_name',
        'stored_file_path',
        'status',
        'total_rows',
        'chunk_size',
        'total_chunks',
        'processed_chunks',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'error_message',
        'started_at',
        'finished_at',
        'requested_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function failures(): HasMany
    {
        return $this->hasMany(ProductImportFailure::class);
    }

    public function batch(): ?Batch
    {
        return $this->batch_id ? Bus::findBatch($this->batch_id) : null;
    }

    public function progressPercentage(): int
    {
        $batch = $this->batch();

        if ($batch) {
            return $batch->progress();
        }

        if ($this->total_chunks <= 0) {
            return 0;
        }

        return (int) round(($this->processed_chunks / $this->total_chunks) * 100);
    }
}
