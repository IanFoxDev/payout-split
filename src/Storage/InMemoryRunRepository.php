<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Storage;

final class InMemoryRunRepository implements RunRepository
{
    /** @var array<string, StoredRun> */
    private array $runs = [];

    public function find(string $scheme, string $period): ?StoredRun
    {
        return $this->runs[$scheme . "\0" . $period] ?? null;
    }

    public function add(StoredRun $run): void
    {
        $key = $run->scheme . "\0" . $run->period;
        if (isset($this->runs[$key])) {
            throw new PeriodAlreadyStored(sprintf('Scheme "%s" already has a run for %s.', $run->scheme, $run->period));
        }
        $this->runs[$key] = $run;
    }
}
