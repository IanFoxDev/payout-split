<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

use Brick\Math\BigInteger;

/**
 * Splits an amount in minor units by integer weights. The shares always add up to the
 * amount. See docs/adr/0001-exact-arithmetic-and-the-sum.md.
 */
final readonly class Allocation
{
    /**
     * @param array<array-key, int> $shares     partner id => minor units, in id order (numeric ids are int keys, as PHP makes them)
     * @param array<array-key, int> $baseShares partner id => share before the remainder
     * @param int                $remainder  units given out by the remainder rule
     */
    private function __construct(
        public array $shares,
        public array $baseShares,
        public int $remainder,
        public Remainder $rule,
    ) {}

    /**
     * @param iterable<string|int, int> $weights partner id => non-negative weight
     */
    public static function split(int $amount, iterable $weights, Remainder $rule = Remainder::LargestRemainder, ?string $houseAccount = null): self
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException(sprintf('The amount to split must not be negative, got %d.', $amount));
        }
        $w = [];
        foreach ($weights as $id => $weight) {
            $id = (string) $id;
            if ($weight < 0) {
                throw new \InvalidArgumentException(sprintf('Weight of "%s" is negative: %d.', $id, $weight));
            }
            if (isset($w[$id])) {
                throw new \InvalidArgumentException(sprintf('Partner "%s" appears twice.', $id));
            }
            $w[$id] = $weight;
        }
        if ($w === []) {
            throw new \InvalidArgumentException('There are no partners to split between.');
        }
        ksort($w, SORT_STRING);
        if ($rule === Remainder::HouseAccount) {
            if ($houseAccount === null || $houseAccount === '') {
                throw new \InvalidArgumentException('The house_account rule needs the id of the house account.');
            }
            if (isset($w[$houseAccount])) {
                throw new \InvalidArgumentException(sprintf('The house account "%s" is also a partner.', $houseAccount));
            }
        }

        $total = BigInteger::zero();
        foreach ($w as $weight) {
            $total = $total->plus($weight);
        }
        if ($total->isZero()) {
            throw new \InvalidArgumentException('All weights are zero: there is nothing to split by.');
        }

        $pool = BigInteger::of($amount);
        $base = [];
        $fractions = [];
        $given = 0;
        foreach ($w as $id => $weight) {
            [$quotient, $rest] = $pool->multipliedBy($weight)->quotientAndRemainder($total);
            $base[$id] = $quotient->toInt();
            $fractions[$id] = $rest;
            $given += $base[$id];
        }
        $left = $amount - $given;

        $shares = $base;
        switch ($rule) {
            case Remainder::LargestRemainder:
                $ids = array_keys($fractions);
                // PHP turns numeric string keys into ints, so ids are compared as strings.
                usort($ids, static fn(int|string $a, int|string $b): int => $fractions[$b]->compareTo($fractions[$a]) ?: strcmp((string) $a, (string) $b));
                for ($i = 0; $i < $left; $i++) {
                    $shares[$ids[$i]]++;
                }
                break;
            case Remainder::LargestShare:
                $top = null;
                foreach ($w as $id => $weight) {
                    if ($top === null || $weight > $w[$top]) {
                        $top = $id;
                    }
                }
                $shares[$top] += $left;
                break;
            case Remainder::First:
                foreach ($w as $id => $weight) {
                    if ($weight > 0) {
                        $shares[$id] += $left;
                        break;
                    }
                }
                break;
            case Remainder::HouseAccount:
                $shares[(string) $houseAccount] = $left;
                ksort($shares, SORT_STRING);
                break;
        }

        if (array_sum($shares) !== $amount) {
            // Cannot happen with the arithmetic above; the check makes that a fact.
            throw new \LogicException(sprintf('Shares add up to %d, not to %d.', array_sum($shares), $amount));
        }

        return new self($shares, $base, $left, $rule);
    }
}
