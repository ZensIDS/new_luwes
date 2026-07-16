<?php

namespace App\Imports\ReadFilters;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class HeadingAndChunkReadFilter implements IReadFilter
{
    public function __construct(
        private readonly int $headingRow,
        private readonly int $startRow,
        private readonly int $chunkSize
    ) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        if ($row === $this->headingRow) {
            return true;
        }

        return $row >= $this->startRow && $row < ($this->startRow + $this->chunkSize);
    }
}
