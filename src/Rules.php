<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

/**
 * How a pool is split. A run records the version and the fingerprint of the rules it
 * used, so a closed period can be recalculated with the same rules after they change.
 */
final readonly class Rules
{
    /**
     * @param int $minimumPayout a partner owed less than this, in minor units, is paid
     *                           nothing this period and the amount is carried over
     */
    public function __construct(
        public string $version,
        public Remainder $remainder = Remainder::LargestRemainder,
        public int $minimumPayout = 0,
        public ?string $houseAccount = null,
    ) {
        if ($version === '') {
            throw new \InvalidArgumentException('Rules need a version.');
        }
        if ($minimumPayout < 0) {
            throw new \InvalidArgumentException(sprintf('The minimum payout must not be negative, got %d.', $minimumPayout));
        }
        if ($remainder === Remainder::HouseAccount && ($houseAccount === null || $houseAccount === '')) {
            throw new \InvalidArgumentException('The house_account rule needs the id of the house account.');
        }
    }

    /**
     * @return array{version: string, remainder: string, minimum_payout: int, house_account: ?string}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'remainder' => $this->remainder->value,
            'minimum_payout' => $this->minimumPayout,
            'house_account' => $this->houseAccount,
        ];
    }

    /**
     * @param array<mixed> $data what toArray() returned
     */
    public static function fromArray(array $data): self
    {
        $remainder = Remainder::tryFrom(is_string($data['remainder'] ?? null) ? $data['remainder'] : '');
        $house = $data['house_account'] ?? null;
        if (!is_string($data['version'] ?? null) || $remainder === null || !is_int($data['minimum_payout'] ?? null) || !(is_string($house) || $house === null)) {
            throw new \InvalidArgumentException('Not a payout-split rules array.');
        }

        return new self($data['version'], $remainder, $data['minimum_payout'], $house);
    }

    /**
     * SHA-256 of the rules, so two rule sets with the same version but different
     * contents are told apart.
     */
    public function fingerprint(): string
    {
        return hash('sha256', Canonical::json($this->toArray()));
    }
}
