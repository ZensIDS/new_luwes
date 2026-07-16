<?php

namespace App\Jobs;

use App\Imports\ProductsImport;
use App\Models\ProductImport;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessProductImportChunk implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        private readonly int $productImportId,
        private readonly int $startRow,
        private readonly int $chunkSize
    ) {
        $this->onQueue('imports');
    }

    public function handle(ProductsImport $productsImport): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $productImport = ProductImport::query()->findOrFail($this->productImportId);

        if (! $productImport->started_at) {
            $productImport->forceFill([
                'status' => ProductImport::STATUS_PROCESSING,
                'started_at' => now(),
            ])->save();
        }

        $result = $productsImport->processChunk(
            $productImport,
            $this->startRow,
            $this->chunkSize
        );

        DB::table('product_imports')
            ->where('id', $this->productImportId)
            ->update([
                'processed_chunks' => DB::raw('processed_chunks + 1'),
                'processed_rows' => DB::raw('processed_rows + ' . (int) $result['processed_rows']),
                'successful_rows' => DB::raw('successful_rows + ' . (int) $result['successful_rows']),
                'failed_rows' => DB::raw('failed_rows + ' . (int) $result['failed_rows']),
                'status' => ProductImport::STATUS_PROCESSING,
                'updated_at' => now(),
            ]);
    }
}
