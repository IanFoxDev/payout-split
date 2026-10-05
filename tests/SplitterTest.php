<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Tests;

use IanFoxDev\PayoutSplit\Input;
use IanFoxDev\PayoutSplit\Money;
use IanFoxDev\PayoutSplit\Period;
use IanFoxDev\PayoutSplit\Remainder;
use IanFoxDev\PayoutSplit\Rules;
use IanFoxDev\PayoutSplit\Splitter;
use PHPUnit\Framework\TestCase;

final class SplitterTest extends TestCase
{
    public function testMinimumPayoutCarriesSmallAmountsOver(): void
    {
        $rules = new Rules('2026-09', minimumPayout: 5_000); // 50.00 USD
        $september = (new Splitter())->run(
            new Input(Period::month('2026-09'), Money::of(100_000, 'USD'), ['studio-a' => 600, 'studio-b' => 370, 'studio-c' => 30]),
            $rules,
        );
        // 1000.00 USD by 600:370:30 is 600.00, 370.00 and 30.00; 30.00 is below the minimum.
        self::assertTrue($september->share('studio-a')->equals(Money::of(60_000, 'USD')));
        self::assertSame(0, $september->share('studio-c')->minor);
        self::assertSame(3_000, $september->carriedOver('studio-c')->minor);
        self::assertSame(['studio-c' => 3_000], $september->carryOverForNextPeriod());

        // In October studio-c earns 25.00 more: 55.00 in total, paid out.
        $october = (new Splitter())->run(
            new Input(Period::month('2026-10'), Money::of(100_000, 'USD'), ['studio-a' => 700, 'studio-b' => 275, 'studio-c' => 25], $september->carryOverForNextPeriod()),
            $rules,
        );
        self::assertSame(5_500, $october->share('studio-c')->minor);
        self::assertSame([], $october->carryOverForNextPeriod());
    }

    public function testExplain(): void
    {
        $run = (new Splitter())->run(
            new Input(Period::month('2026-09'), Money::of(100, 'EUR'), ['a' => 1, 'b' => 1, 'c' => 1]),
            new Rules('v1'),
        );
        self::assertSame([
            'partner' => 'a',
            'period' => '2026-09',
            'rules_version' => 'v1',
            'currency' => 'EUR',
            'pool' => 100,
            'weight' => 1,
            'total_weight' => 3,
            'exact_share' => '100 * 1 / 3',
            'base_share' => 33,
            'remainder_units' => 1,
            'remainder_rule' => 'largest_remainder',
            'carried_in' => 0,
            'minimum_payout' => 0,
            'payout' => 34,
            'carried_out' => 0,
        ], $run->explain('a'));
    }

    public function testHouseAccountIsPaidWithoutMinimum(): void
    {
        $run = (new Splitter())->run(
            new Input(Period::month('2026-09'), Money::of(101, 'EUR'), ['a' => 1, 'b' => 1, 'c' => 1]),
            new Rules('v1', Remainder::HouseAccount, minimumPayout: 1_000, houseAccount: 'platform'),
        );
        self::assertSame(2, $run->share('platform')->minor);
        self::assertSame(33, $run->carriedOver('a')->minor);
    }

    public function testPartnerOnlyCarriedOverTakesPart(): void
    {
        $run = (new Splitter())->run(
            new Input(Period::month('2026-10'), Money::of(1_000, 'EUR'), ['a' => 1], ['gone' => 700]),
            new Rules('v1', minimumPayout: 500),
        );
        self::assertSame(700, $run->share('gone')->minor);
        self::assertSame(1_000, $run->share('a')->minor);
    }

    public function testEmptyPoolWithCarryOnly(): void
    {
        $run = (new Splitter())->run(
            new Input(Period::month('2026-10'), Money::of(0, 'EUR'), [], ['a' => 300]),
            new Rules('v1', minimumPayout: 500),
        );
        self::assertSame(300, $run->carriedOver('a')->minor);
    }

    public function testSameInputSameBytes(): void
    {
        $input = new Input(Period::month('2026-09'), Money::of(987_654_321, 'USD'), ['x' => 17, 'y' => 4, 'z' => 1_000_003], ['y' => 12]);
        $rules = new Rules('v3', minimumPayout: 100);
        self::assertSame((new Splitter())->run($input, $rules)->toJson(), (new Splitter())->run($input, $rules)->toJson());
    }

    public function testHouseAccountMustNotBeAPartner(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Splitter())->run(
            new Input(Period::month('2026-09'), Money::of(10, 'EUR'), ['a' => 1], ['platform' => 1]),
            new Rules('v1', Remainder::HouseAccount, houseAccount: 'platform'),
        );
    }

    public function testUnknownPartner(): void
    {
        $run = (new Splitter())->run(new Input(Period::month('2026-09'), Money::of(10, 'EUR'), ['a' => 1]), new Rules('v1'));
        $this->expectException(\OutOfBoundsException::class);
        $run->share('b');
    }

    public function testPropertiesWithCarryAndMinimum(): void
    {
        $random = new \Random\Randomizer(new \Random\Engine\Mt19937(7));
        $splitter = new Splitter();
        for ($case = 0; $case < 3_000; $case++) {
            $weights = $carry = [];
            for ($i = 0, $n = $random->getInt(1, 20); $i < $n; $i++) {
                $weights['p' . $i] = $random->getInt(0, 1_000);
                if ($random->getInt(0, 2) === 0) {
                    $carry['p' . $i] = $random->getInt(1, 50_000);
                }
            }
            if (array_sum($weights) === 0) {
                $weights['p0'] = 1;
            }
            $pool = $random->getInt(0, 10_000_000);
            $min = $random->getInt(0, 100_000);
            $run = $splitter->run(new Input(Period::month('2026-09'), Money::of($pool, 'USD'), $weights, $carry), new Rules('v1', minimumPayout: $min));

            $paid = $carried = 0;
            foreach ($run->lines as $line) {
                $paid += $line->payout;
                $carried += $line->carriedOut;
                self::assertTrue($line->payout === 0 || $line->payout >= $min, "case $case: paid below the minimum");
                self::assertTrue($line->payout === 0 || $line->carriedOut === 0, "case $case: paid and carried at once");
            }
            self::assertSame($pool + array_sum($carry), $paid + $carried, "case $case");
        }
    }
}
