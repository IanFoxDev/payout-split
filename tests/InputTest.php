<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Tests;

use IanFoxDev\PayoutSplit\Input;
use IanFoxDev\PayoutSplit\Money;
use IanFoxDev\PayoutSplit\Period;
use IanFoxDev\PayoutSplit\Remainder;
use IanFoxDev\PayoutSplit\Rules;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    public function testCanonicalFormIgnoresOrder(): void
    {
        $a = new Input(Period::month('2026-09'), Money::of(12_000_000, 'USD'), ['studio-2' => 30, 'studio-1' => 70], ['studio-2' => 15]);
        $b = new Input(Period::month('2026-09'), Money::of(12_000_000, 'USD'), ['studio-1' => 70, 'studio-2' => 30], ['studio-2' => 15]);
        self::assertSame($a->canonical(), $b->canonical());
        self::assertSame(
            '{"carried_over":{"studio-2":15},"currency":"USD","period":"2026-09","pool":12000000,"weights":{"studio-1":70,"studio-2":30}}',
            $a->canonical(),
        );
        self::assertSame(64, strlen($a->hash()));
    }

    public function testNumericIdsStayAnObject(): void
    {
        $input = new Input(Period::named('2026-Q3'), Money::of(100, 'EUR'), [10 => 1, 2 => 1]);
        self::assertSame('{"carried_over":{},"currency":"EUR","period":"2026-Q3","pool":100,"weights":{"10":1,"2":1}}', $input->canonical());
    }

    public function testEveryChangeChangesTheHash(): void
    {
        $base = new Input(Period::month('2026-09'), Money::of(1000, 'USD'), ['a' => 1, 'b' => 2]);
        $variants = [
            new Input(Period::month('2026-10'), Money::of(1000, 'USD'), ['a' => 1, 'b' => 2]),
            new Input(Period::month('2026-09'), Money::of(1001, 'USD'), ['a' => 1, 'b' => 2]),
            new Input(Period::month('2026-09'), Money::of(1000, 'EUR'), ['a' => 1, 'b' => 2]),
            new Input(Period::month('2026-09'), Money::of(1000, 'USD'), ['a' => 1, 'b' => 3]),
            new Input(Period::month('2026-09'), Money::of(1000, 'USD'), ['a' => 1, 'b' => 2], ['a' => 1]),
        ];
        foreach ($variants as $i => $v) {
            self::assertNotSame($base->hash(), $v->hash(), "variant $i");
        }
    }

    public function testRulesFingerprint(): void
    {
        $a = new Rules('2026-09', Remainder::LargestRemainder, 5000);
        self::assertSame($a->fingerprint(), (new Rules('2026-09', Remainder::LargestRemainder, 5000))->fingerprint());
        self::assertNotSame($a->fingerprint(), (new Rules('2026-09', Remainder::LargestRemainder, 2500))->fingerprint());
    }

    public function testRejects(): void
    {
        $cases = [
            static fn() => Period::month('2026-13'),
            static fn() => Period::named('has space'),
            static fn() => new Rules(''),
            static fn() => new Rules('v1', Remainder::LargestRemainder, -1),
            static fn() => new Rules('v1', Remainder::HouseAccount),
            static fn() => new Input(Period::month('2026-09'), Money::of(-1, 'USD'), ['a' => 1]),
            static fn() => new Input(Period::month('2026-09'), Money::of(1, 'USD'), ['a' => -1]),
            static fn() => new Input(Period::month('2026-09'), Money::of(1, 'USD'), ['a' => 1], ['a' => -5]),
            static fn() => Money::of(1, 'usd'),
        ];
        foreach ($cases as $i => $case) {
            try {
                $case();
                self::fail("case $i accepted");
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
