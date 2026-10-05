<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

/**
 * Splits a period's pool by weights, applies the minimum payout and carries amounts
 * below it over to the next period.
 */
final class Splitter
{
    public function run(Input $input, Rules $rules): Run
    {
        if ($rules->houseAccount !== null && (isset($input->weights[$rules->houseAccount]) || isset($input->carriedOver[$rules->houseAccount]))) {
            throw new \InvalidArgumentException(sprintf('The house account "%s" is also a partner.', $rules->houseAccount));
        }

        // Partners carried over from earlier periods take part with weight 0.
        $weights = $input->weights;
        foreach ($input->carriedOver as $id => $_) {
            $weights[$id] ??= 0;
        }
        ksort($weights, SORT_STRING);
        $totalWeight = array_sum($weights);

        if ($input->pool->minor === 0 && $totalWeight === 0) {
            $allocation = null;
        } else {
            $allocation = Allocation::split($input->pool->minor, $weights, $rules->remainder, $rules->houseAccount);
        }

        $lines = [];
        foreach ($weights as $id => $weight) {
            $id = (string) $id;
            $base = $allocation === null ? 0 : $allocation->baseShares[$id];
            $allocated = $allocation === null ? 0 : $allocation->shares[$id];
            $carriedIn = $input->carriedOver[$id] ?? 0;
            $due = $allocated + $carriedIn;
            [$payout, $carriedOut] = $due < $rules->minimumPayout ? [0, $due] : [$due, 0];
            $lines[$id] = new Line($id, $weight, $base, $allocated - $base, $carriedIn, $payout, $carriedOut);
        }
        if ($allocation !== null && $rules->houseAccount !== null && $rules->remainder === Remainder::HouseAccount) {
            // The house account is the platform's own: no minimum payout.
            $house = $allocation->shares[$rules->houseAccount];
            $lines[$rules->houseAccount] = new Line($rules->houseAccount, 0, 0, $house, 0, $house, 0);
            ksort($lines, SORT_STRING);
        }

        $this->check($input, $lines);

        return new Run($input, $rules, $lines, $totalWeight);
    }

    /**
     * @param array<array-key, Line> $lines
     */
    private function check(Input $input, array $lines): void
    {
        $allocated = $paid = $carriedOut = 0;
        foreach ($lines as $line) {
            $allocated += $line->allocated();
            $paid += $line->payout;
            $carriedOut += $line->carriedOut;
        }
        $carriedIn = array_sum($input->carriedOver);
        if ($allocated !== $input->pool->minor || $paid + $carriedOut !== $input->pool->minor + $carriedIn) {
            throw new \LogicException(sprintf(
                'Period %s does not add up: allocated %d of a pool of %d, paid %d and carried %d of %d.',
                $input->period->id,
                $allocated,
                $input->pool->minor,
                $paid,
                $carriedOut,
                $input->pool->minor + $carriedIn,
            ));
        }
    }
}
