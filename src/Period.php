<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

/**
 * The period a pool belongs to: a month such as 2026-09, or any label you use.
 */
final readonly class Period
{
    private function __construct(public string $id) {}

    public static function month(string $month): self
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new \InvalidArgumentException(sprintf('A month is YYYY-MM, got "%s".', $month));
        }

        return new self($month);
    }

    /**
     * Any period label: "2026-W40", "2026-Q3", "season-7".
     */
    public static function named(string $id): self
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/', $id) !== 1) {
            throw new \InvalidArgumentException(sprintf('A period label is 1 to 64 letters, digits and . _ : -, got "%s".', $id));
        }

        return new self($id);
    }
}
