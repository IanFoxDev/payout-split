<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Tests;

use Brick\Math\BigInteger;
use IanFoxDev\PayoutSplit\Allocation;
use IanFoxDev\PayoutSplit\Remainder;
use PHPUnit\Framework\TestCase;

/**
 * Properties checked on many random pools and weights, with a fixed seed so a failure
 * can be repeated: the sum is the pool, every share is within one unit of its exact
 * value, zero weights get nothing, and the order of the input does not matter.
 */
final class AllocationPropertiesTest extends TestCase
{
    private const CASES = 10_000;

    public function testInvariants(): void
    {
        $random = new \Random\Randomizer(new \Random\Engine\Mt19937(20261005));
        for ($case = 0; $case < self::CASES; $case++) {
            $amount = match ($case % 3) {
                0 => $random->getInt(0, 1_000),
                1 => $random->getInt(0, 1_000_000_000),
                default => $random->getInt(0, PHP_INT_MAX),
            };
            $weights = [];
            $n = $random->getInt(1, 40);
            for ($i = 0; $i < $n; $i++) {
                $weights['p' . $random->getInt(0, 1_000_000)] = $random->getInt(0, 3) === 0 ? 0 : $random->getInt(1, $case % 2 === 0 ? 100 : 1_000_000_000_000);
            }
            if (array_sum($weights) === 0) {
                $weights['p-last'] = 1;
            }

            $a = Allocation::split($amount, $weights);
            $context = sprintf('case %d: amount %d, weights %s', $case, $amount, json_encode($weights));

            self::assertSame($amount, array_sum($a->shares), $context);

            $total = BigInteger::zero();
            foreach ($weights as $w) {
                $total = $total->plus($w);
            }
            foreach ($weights as $id => $w) {
                $floor = BigInteger::of($amount)->multipliedBy($w)->quotient($total)->toInt();
                $share = $a->shares[$id];
                if ($share !== $floor && $share !== $floor + 1) {
                    self::fail(sprintf('%s: %s got %d, exact share is between %d and %d', $context, $id, $share, $floor, $floor + 1));
                }
                if ($w === 0) {
                    self::assertSame(0, $share, $context);
                }
            }

            $shuffled = $weights;
            $keys = array_keys($shuffled);
            $keys = $random->shuffleArray($keys);
            $reordered = [];
            foreach ($keys as $k) {
                $reordered[$k] = $shuffled[$k];
            }
            self::assertSame($a->shares, Allocation::split($amount, $reordered)->shares, $context . ' (reordered)');
        }
    }

    public function testEveryRuleKeepsTheSum(): void
    {
        $random = new \Random\Randomizer(new \Random\Engine\Mt19937(42));
        foreach (Remainder::cases() as $rule) {
            for ($case = 0; $case < 2_000; $case++) {
                $amount = $random->getInt(0, 1_000_000_000);
                $weights = ['a' => $random->getInt(1, 1000), 'b' => $random->getInt(0, 1000), 'c' => $random->getInt(0, 1000)];
                $a = Allocation::split($amount, $weights, $rule, 'house');
                self::assertSame($amount, array_sum($a->shares), $rule->value);
            }
        }
    }
}
