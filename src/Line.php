<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

/**
 * One partner's part of a run, in minor units.
 */
final readonly class Line
{
    public function __construct(
        public string $partner,
        public int $weight,
        /** Share of this period's pool before the remainder rule. */
        public int $baseShare,
        /** Units the remainder rule added. */
        public int $remainderUnits,
        public int $carriedIn,
        public int $payout,
        public int $carriedOut,
    ) {}

    public function allocated(): int
    {
        return $this->baseShare + $this->remainderUnits;
    }

    /**
     * @return array{partner: string, weight: int, base_share: int, remainder_units: int, carried_in: int, payout: int, carried_out: int}
     */
    public function toArray(): array
    {
        return [
            'partner' => $this->partner,
            'weight' => $this->weight,
            'base_share' => $this->baseShare,
            'remainder_units' => $this->remainderUnits,
            'carried_in' => $this->carriedIn,
            'payout' => $this->payout,
            'carried_out' => $this->carriedOut,
        ];
    }
}
