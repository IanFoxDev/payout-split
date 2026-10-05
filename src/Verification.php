<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

final readonly class Verification
{
    public function __construct(
        public bool $matches,
        public string $stored,
        public string $recalculated,
    ) {}
}
