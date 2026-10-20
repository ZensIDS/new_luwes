<?php

namespace Tests\Unit;

use App\Services\DbSync\RowMerger;
use PHPUnit\Framework\TestCase;

class DbSyncRowMergerTest extends TestCase
{
    private function merger(): RowMerger
    {
        return new RowMerger(['qty'], ['last_number'], 'local');
    }

    public function test_changes_to_different_columns_are_combined()
    {
        $base = ['id' => 1, 'name' => 'A', 'harga' => 100, 'updated_at' => '2026-10-20 10:00:00'];
        $local = ['name' => 'Nama Lokal', 'updated_at' => '2026-10-20 10:05:00'] + $base;
        $server = ['harga' => 150, 'updated_at' => '2026-10-20 10:03:00'] + $base;

        $merged = $this->merger()->merge($local, $server, $base);

        $this->assertSame('Nama Lokal', $merged['name']);
        $this->assertSame(150, $merged['harga']);
        $this->assertSame('2026-10-20 10:05:00', $merged['updated_at']);
    }

    public function test_additive_columns_sum_both_deltas()
    {
        $base = ['id' => 1, 'qty' => 37];

        // terjual 3 di kasir offline, masuk 10 dari hosting
        $merged = $this->merger()->merge(['qty' => 34] + $base, ['qty' => 47] + $base, $base);

        $this->assertSame(44, $merged['qty']);
    }

    public function test_additive_columns_keep_decimal_format()
    {
        $base = ['id' => 1, 'qty' => '10.50'];

        $merged = $this->merger()->merge(['qty' => '9.50'] + $base, ['qty' => '12.00'] + $base, $base);

        $this->assertSame('11.00', $merged['qty']);
    }

    public function test_max_columns_take_the_highest_value()
    {
        $base = ['id' => 1, 'last_number' => 5];

        $merged = $this->merger()->merge(['last_number' => 7] + $base, ['last_number' => 9] + $base, $base);

        $this->assertSame(9, $merged['last_number']);
    }

    public function test_conflicting_column_uses_newest_row_then_preferred_side()
    {
        $base = ['id' => 1, 'name' => 'A', 'updated_at' => '2026-10-20 10:00:00'];
        $local = ['name' => 'L', 'updated_at' => '2026-10-20 10:01:00'] + $base;
        $server = ['name' => 'S', 'updated_at' => '2026-10-20 10:02:00'] + $base;

        $this->assertSame('S', $this->merger()->merge($local, $server, $base)['name']);

        $server['updated_at'] = $local['updated_at'];
        $this->assertSame('L', $this->merger()->merge($local, $server, $base)['name']);
        $this->assertSame('S', (new RowMerger([], [], 'server'))->merge($local, $server, $base)['name']);
    }

    public function test_same_tolerates_representation_differences()
    {
        $this->assertTrue(RowMerger::same(10, '10.00'));
        $this->assertTrue(RowMerger::same('{"a": 1, "b": [1,2]}', '{"a":1,"b":[1,2]}'));
        $this->assertTrue(RowMerger::same(null, null));
        $this->assertFalse(RowMerger::same(null, ''));
        $this->assertFalse(RowMerger::same('0', null));
        $this->assertFalse(RowMerger::same('abc', 'abd'));
    }

    public function test_conflicts_only_report_same_column_changed_differently()
    {
        $base = ['id' => 1, 'name' => 'A', 'harga' => 100, 'qty' => 10, 'last_number' => 3, 'updated_at' => 'x'];
        $local = ['name' => 'L', 'qty' => 8, 'last_number' => 4, 'updated_at' => 'y'] + $base;
        $server = ['name' => 'S', 'harga' => 120, 'qty' => 15, 'last_number' => 5, 'updated_at' => 'z'] + $base;

        $this->assertSame(['name'], $this->merger()->conflicts($local, $server, $base));

        $server['name'] = 'L';
        $this->assertSame([], $this->merger()->conflicts($local, $server, $base));
    }

    public function test_without_base_every_differing_column_is_a_conflict()
    {
        $local = ['id' => 1, 'qty' => 8, 'name' => 'A', 'updated_at' => 'y'];
        $server = ['id' => 1, 'qty' => 9, 'name' => 'A', 'updated_at' => 'z'];

        $this->assertSame(['qty'], $this->merger()->conflicts($local, $server, null));
    }

    public function test_column_states_explain_each_difference()
    {
        $base = ['id' => 1, 'name' => 'A', 'harga' => 100, 'qty' => 10, 'note' => 'x', 'updated_at' => 'a'];
        $local = ['name' => 'L', 'qty' => 8, 'note' => 'y', 'updated_at' => 'b'] + $base;
        $server = ['name' => 'S', 'harga' => 120, 'qty' => 15, 'updated_at' => 'c'] + $base;

        $this->assertEquals([
            'name' => 'conflict',
            'harga' => 'server',
            'qty' => 'merged',
            'note' => 'local',
            'updated_at' => 'merged',
            'id' => 'same',
        ], $this->merger()->columnStates($local, $server, $base));
    }
}
