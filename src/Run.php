<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

/**
 * The result of splitting one period's pool: what each partner is paid, what is carried
 * over, and from which input and rules.
 */
final readonly class Run
{
    /**
     * @param array<string, Line> $lines partner id => line, sorted by id
     */
    public function __construct(
        public Input $input,
        public Rules $rules,
        public array $lines,
        public int $totalWeight,
    ) {}

    public function share(string $partner): Money
    {
        return Money::of($this->line($partner)->payout, $this->input->pool->currency);
    }

    public function carriedOver(string $partner): Money
    {
        return Money::of($this->line($partner)->carriedOut, $this->input->pool->currency);
    }

    /**
     * Partner id => minor units carried into the next period, for the next Input.
     *
     * @return array<string, int>
     */
    public function carryOverForNextPeriod(): array
    {
        $out = [];
        foreach ($this->lines as $id => $line) {
            if ($line->carriedOut > 0) {
                $out[$id] = $line->carriedOut;
            }
        }

        return $out;
    }

    public function line(string $partner): Line
    {
        return $this->lines[$partner] ?? throw new \OutOfBoundsException(sprintf('No partner "%s" in period %s.', $partner, $this->input->period->id));
    }

    /**
     * How a partner's amount was arrived at, step by step.
     *
     * @return array{partner: string, period: string, rules_version: string, currency: string, pool: int, weight: int, total_weight: int, exact_share: string, base_share: int, remainder_units: int, remainder_rule: string, carried_in: int, minimum_payout: int, payout: int, carried_out: int}
     */
    public function explain(string $partner): array
    {
        $line = $this->line($partner);

        return [
            'partner' => $line->partner,
            'period' => $this->input->period->id,
            'rules_version' => $this->rules->version,
            'currency' => $this->input->pool->currency,
            'pool' => $this->input->pool->minor,
            'weight' => $line->weight,
            'total_weight' => $this->totalWeight,
            'exact_share' => sprintf('%d * %d / %d', $this->input->pool->minor, $line->weight, $this->totalWeight),
            'base_share' => $line->baseShare,
            'remainder_units' => $line->remainderUnits,
            'remainder_rule' => $this->rules->remainder->value,
            'carried_in' => $line->carriedIn,
            'minimum_payout' => $this->rules->minimumPayout,
            'payout' => $line->payout,
            'carried_out' => $line->carriedOut,
        ];
    }

    /**
     * The whole run as canonical JSON. A recalculation with the same input and rules
     * gives the same bytes.
     */
    public function toJson(): string
    {
        return Canonical::json([
            'period' => $this->input->period->id,
            'input_hash' => $this->input->hash(),
            'rules' => $this->rules->toArray(),
            'rules_fingerprint' => $this->rules->fingerprint(),
            'currency' => $this->input->pool->currency,
            'pool' => $this->input->pool->minor,
            'total_weight' => $this->totalWeight,
            'lines' => array_values(array_map(static fn(Line $l): array => $l->toArray(), $this->lines)),
        ]);
    }
}
