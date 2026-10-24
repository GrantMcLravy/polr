<?php

namespace Tests\Unit;

use App\Helpers\BaseHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BaseHelperTest extends TestCase
{
    public static function numbers(): array
    {
        return [[0], [1], [23], [45], [1204], [523002], [PHP_INT_MAX >> 16]];
    }

    #[DataProvider('numbers')]
    public function test_base32_and_base62_round_trip(int $n): void
    {
        $this->assertEquals($n, BaseHelper::toBase10(BaseHelper::toBase($n, 32), 32));
        $this->assertEquals($n, BaseHelper::toBase10(BaseHelper::toBase($n, 62), 62));
    }

    public function test_known_endings(): void
    {
        // Counter endings of existing links must not change.
        $this->assertSame('a', BaseHelper::toBase(10, 32));
        $this->assertSame('10', BaseHelper::toBase(32, 32));
        $this->assertSame('Z', BaseHelper::toBase(61, 62));
        $this->assertSame('10', BaseHelper::toBase(62, 62));
        $this->assertEquals(1023, BaseHelper::toBase10('vv', 32));
    }
}
