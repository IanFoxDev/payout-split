<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Storage;

/**
 * Keeps one run per scheme and period. Runs are never updated or deleted.
 */
interface RunRepository
{
    public function find(string $scheme, string $period): ?StoredRun;

    /**
     * @throws PeriodAlreadyStored when a run for the scheme and period exists, also when
     *                             another process stored it a moment ago
     */
    public function add(StoredRun $run): void;
}
