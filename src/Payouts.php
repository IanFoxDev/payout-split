<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

use IanFoxDev\PayoutSplit\Storage\PeriodAlreadyStored;
use IanFoxDev\PayoutSplit\Storage\RunRepository;
use IanFoxDev\PayoutSplit\Storage\StoredRun;

/**
 * Runs each period of a payout scheme once and keeps the result, so running it again
 * returns the same numbers and a closed period can be checked later.
 */
final class Payouts
{
    public function __construct(
        private readonly RunRepository $runs,
        private readonly Splitter $splitter = new Splitter(),
    ) {}

    /**
     * Calculates and stores the period, or returns the stored run if the period was
     * already run with the same input and rules.
     *
     * @throws PeriodClosed when the period was run with a different input or rules
     */
    public function run(string $scheme, Input $input, Rules $rules): Run
    {
        $existing = $this->runs->find($scheme, $input->period->id);
        if ($existing !== null) {
            return $this->same($existing, $input, $rules);
        }
        $run = $this->splitter->run($input, $rules);
        try {
            $this->runs->add(new StoredRun(
                $scheme,
                $input->period->id,
                $input->hash(),
                $rules->fingerprint(),
                $input->canonical(),
                Canonical::json($rules->toArray()),
                $run->toJson(),
            ));
        } catch (PeriodAlreadyStored) {
            // Another process stored the period between find() and add().
            $stored = $this->runs->find($scheme, $input->period->id) ?? throw new \LogicException('A stored run disappeared.');

            return $this->same($stored, $input, $rules);
        }

        return $run;
    }

    /**
     * Recalculates a stored period from its stored input and rules and compares the
     * result with the stored one, byte for byte.
     */
    public function verify(string $scheme, Period $period): Verification
    {
        $stored = $this->runs->find($scheme, $period->id) ?? throw new \OutOfBoundsException(sprintf('Scheme "%s" has no run for %s.', $scheme, $period->id));
        $recalculated = $this->splitter->run(Input::fromCanonical($stored->input), Rules::fromArray(self::array($stored->rules)))->toJson();

        return new Verification($stored->result === $recalculated, $stored->result, $recalculated);
    }

    private function same(StoredRun $stored, Input $input, Rules $rules): Run
    {
        if ($stored->inputHash !== $input->hash() || $stored->rulesFingerprint !== $rules->fingerprint()) {
            throw new PeriodClosed(sprintf(
                'Period %s of scheme "%s" was already run with input %s and rules %s; this call has input %s and rules %s. Closed periods are not recalculated with new data.',
                $input->period->id,
                $stored->scheme,
                substr($stored->inputHash, 0, 12),
                substr($stored->rulesFingerprint, 0, 12),
                substr($input->hash(), 0, 12),
                substr($rules->fingerprint(), 0, 12),
            ));
        }

        return $this->splitter->run(Input::fromCanonical($stored->input), Rules::fromArray(self::array($stored->rules)));
    }

    /**
     * @return array<mixed>
     */
    private static function array(string $json): array
    {
        $data = json_decode($json, true, 4, JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : throw new \UnexpectedValueException('Stored rules are not a JSON object.');
    }
}
