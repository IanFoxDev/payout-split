<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

/**
 * Everything a run is calculated from: the period, the pool, the weights and what was
 * carried over from the previous period.
 */
final readonly class Input
{
    /** @var array<array-key, int> partner id => weight, sorted by id as a string */
    public array $weights;

    /** @var array<array-key, int> partner id => minor units carried in, sorted by id as a string */
    public array $carriedOver;

    /**
     * @param iterable<string|int, int> $weights
     * @param iterable<string|int, int> $carriedOver
     */
    public function __construct(
        public Period $period,
        public Money $pool,
        iterable $weights,
        iterable $carriedOver = [],
    ) {
        if ($pool->minor < 0) {
            throw new \InvalidArgumentException(sprintf('The pool must not be negative, got %d.', $pool->minor));
        }
        $this->weights = self::byId($weights, 'weight');
        $this->carriedOver = self::byId($carriedOver, 'carried-over amount');
    }

    /**
     * The input as canonical JSON: keys sorted, ids as strings, no whitespace. Two
     * inputs that mean the same thing give the same bytes.
     */
    public function canonical(): string
    {
        return Canonical::json([
            'period' => $this->period->id,
            'currency' => $this->pool->currency,
            'pool' => $this->pool->minor,
            'weights' => Canonical::stringKeys($this->weights),
            'carried_over' => Canonical::stringKeys($this->carriedOver),
        ]);
    }

    public function hash(): string
    {
        return hash('sha256', $this->canonical());
    }

    /**
     * Reads back what canonical() wrote.
     */
    public static function fromCanonical(string $json): self
    {
        $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_string($data['period'] ?? null) || !is_string($data['currency'] ?? null) || !is_int($data['pool'] ?? null)
            || !is_array($data['weights'] ?? null) || !is_array($data['carried_over'] ?? null)) {
            throw new \InvalidArgumentException('Not a canonical payout-split input.');
        }

        return new self(Period::named($data['period']), Money::of($data['pool'], $data['currency']), self::ints($data['weights']), self::ints($data['carried_over']));
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, int>
     */
    private static function ints(array $values): array
    {
        $out = [];
        foreach ($values as $k => $v) {
            if (!is_int($v)) {
                throw new \InvalidArgumentException(sprintf('"%s" is not an integer.', $k));
            }
            $out[(string) $k] = $v;
        }

        return $out;
    }

    /**
     * @param iterable<string|int, int> $values
     *
     * @return array<array-key, int>
     */
    private static function byId(iterable $values, string $what): array
    {
        $out = [];
        foreach ($values as $id => $value) {
            $id = (string) $id;
            if ($id === '') {
                throw new \InvalidArgumentException('A partner id must not be empty.');
            }
            if ($value < 0) {
                throw new \InvalidArgumentException(sprintf('The %s of "%s" is negative: %d.', $what, $id, $value));
            }
            if (array_key_exists($id, $out)) {
                throw new \InvalidArgumentException(sprintf('Partner "%s" appears twice.', $id));
            }
            $out[$id] = $value;
        }
        ksort($out, SORT_STRING);

        return $out;
    }
}
