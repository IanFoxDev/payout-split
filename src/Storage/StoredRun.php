<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Storage;

/**
 * A run as it is kept: the canonical input and rules it was calculated from, and the
 * canonical result.
 */
final readonly class StoredRun
{
    public function __construct(
        public string $scheme,
        public string $period,
        public string $inputHash,
        public string $rulesFingerprint,
        public string $input,
        public string $rules,
        public string $result,
    ) {}
}
