<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

/**
 * An amount of money as an integer count of minor units: 1099 USD is 10.99 dollars.
 */
final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency,
    ) {
        if (preg_match('/^[A-Z][A-Z0-9]{2,9}$/', $currency) !== 1) {
            throw new \InvalidArgumentException(sprintf('Currency must be an upper-case code such as "EUR", got "%s".', $currency));
        }
    }

    public static function of(int $minor, string $currency): self
    {
        return new self($minor, $currency);
    }

    public function plus(self $other): self
    {
        $this->sameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    private function sameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new \InvalidArgumentException(sprintf('Cannot combine %s with %s.', $this->currency, $other->currency));
        }
    }
}
