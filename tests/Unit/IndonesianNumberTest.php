<?php

namespace Tests\Unit;

use App\Support\IndonesianNumber;
use PHPUnit\Framework\TestCase;

class IndonesianNumberTest extends TestCase
{
    public function test_thousands_separator_is_removed_without_changing_the_amount(): void
    {
        $this->assertSame(100000.0, IndonesianNumber::parse('100.000'));
        $this->assertSame(1250500.0, IndonesianNumber::parse('1.250.500'));
    }

    public function test_indonesian_decimal_separator_is_preserved(): void
    {
        $this->assertSame(100000.5, IndonesianNumber::parse('100.000,50'));
    }
}
