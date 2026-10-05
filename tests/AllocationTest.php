<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Tests;

use IanFoxDev\PayoutSplit\Allocation;
use IanFoxDev\PayoutSplit\Remainder;
use PHPUnit\Framework\TestCase;

final class AllocationTest extends TestCase
{
    public function testLargestRemainder(): void
    {
        // 100 by 1:1:1 is 33.33 each; the one unit left goes to the first id.
        $a = Allocation::split(100, ['b' => 1, 'a' => 1, 'c' => 1]);
        self::assertSame(['a' => 34, 'b' => 33, 'c' => 33], $a->shares);
        self::assertSame(1, $a->remainder);
    }

    public function testLargestFractionWinsBeforeTheId(): void
    {
        // 1000 by 1:2:4 (total 7): 142.857, 285.714, 571.428. Base 142+285+571 = 998.
        // Fractions .857, .714, .428: the two units go to x and y.
        $a = Allocation::split(1000, ['x' => 1, 'y' => 2, 'z' => 4]);
        self::assertSame(['x' => 143, 'y' => 286, 'z' => 571], $a->shares);
    }

    public function testOtherRules(): void
    {
        $w = ['a' => 1, 'b' => 1, 'c' => 1, 'big' => 3];
        // 101 by 1:1:1:3 (total 6): 16.83 x3 and 50.5. Base 16*3 + 50 = 98, three left.
        self::assertSame(['a' => 16, 'b' => 16, 'big' => 53, 'c' => 16], Allocation::split(101, $w, Remainder::LargestShare)->shares);
        self::assertSame(['a' => 19, 'b' => 16, 'big' => 50, 'c' => 16], Allocation::split(101, $w, Remainder::First)->shares);
        self::assertSame(['a' => 16, 'b' => 16, 'big' => 50, 'c' => 16, 'house' => 3], Allocation::split(101, $w, Remainder::HouseAccount, 'house')->shares);
    }

    public function testNumericIdsAreSortedAsStrings(): void
    {
        $a = Allocation::split(100, [10 => 1, 9 => 1, '2' => 1]);
        // As strings "10" < "2" < "9", so "10" gets the extra unit.
        self::assertSame([10 => 34, 2 => 33, 9 => 33], $a->shares);
    }

    public function testHugeProductsDoNotOverflow(): void
    {
        // pool * weight is about 10^27, far past PHP_INT_MAX.
        $a = Allocation::split(PHP_INT_MAX, ['a' => 1_000_000_000, 'b' => 2_000_000_000, 'c' => 3_000_000_001]);
        self::assertSame(PHP_INT_MAX, array_sum($a->shares));
    }

    public function testZeroWeightGetsNothing(): void
    {
        $a = Allocation::split(7, ['a' => 1, 'b' => 0, 'c' => 1]);
        self::assertSame(0, $a->shares['b']);
    }

    /**
     * @return iterable<string, array{int, array<string, int>, Remainder, ?string}>
     */
    public static function invalid(): iterable
    {
        yield 'negative amount' => [-1, ['a' => 1], Remainder::LargestRemainder, null];
        yield 'no partners' => [10, [], Remainder::LargestRemainder, null];
        yield 'negative weight' => [10, ['a' => -1, 'b' => 2], Remainder::LargestRemainder, null];
        yield 'all zero' => [10, ['a' => 0, 'b' => 0], Remainder::LargestRemainder, null];
        yield 'house without id' => [10, ['a' => 1], Remainder::HouseAccount, null];
        yield 'house is a partner' => [10, ['a' => 1], Remainder::HouseAccount, 'a'];
    }

    /**
     * @param array<string, int> $weights
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalid')]
    public function testRejects(int $amount, array $weights, Remainder $rule, ?string $house): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Allocation::split($amount, $weights, $rule, $house);
    }
}
