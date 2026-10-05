<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Tests;

use IanFoxDev\PayoutSplit\Input;
use IanFoxDev\PayoutSplit\Money;
use IanFoxDev\PayoutSplit\Payouts;
use IanFoxDev\PayoutSplit\Period;
use IanFoxDev\PayoutSplit\PeriodClosed;
use IanFoxDev\PayoutSplit\Rules;
use IanFoxDev\PayoutSplit\Storage\InMemoryRunRepository;
use IanFoxDev\PayoutSplit\Storage\PeriodAlreadyStored;
use IanFoxDev\PayoutSplit\Storage\RunRepository;
use IanFoxDev\PayoutSplit\Storage\StoredRun;
use PHPUnit\Framework\TestCase;

final class PayoutsTest extends TestCase
{
    private function september(int $pool = 100_000): Input
    {
        return new Input(Period::month('2026-09'), Money::of($pool, 'USD'), ['studio-a' => 600, 'studio-b' => 370, 'studio-c' => 30]);
    }

    public function testRunningAPeriodAgainReturnsTheStoredRun(): void
    {
        $payouts = new Payouts(new InMemoryRunRepository());
        $rules = new Rules('2026-09', minimumPayout: 5_000);
        $first = $payouts->run('studios', $this->september(), $rules);
        $again = $payouts->run('studios', $this->september(), $rules);
        self::assertSame($first->toJson(), $again->toJson());
    }

    public function testClosedPeriodRefusesNewInputOrRules(): void
    {
        $payouts = new Payouts(new InMemoryRunRepository());
        $payouts->run('studios', $this->september(), new Rules('2026-09'));
        foreach ([[$this->september(100_001), new Rules('2026-09')], [$this->september(), new Rules('2026-10')]] as [$input, $rules]) {
            try {
                $payouts->run('studios', $input, $rules);
                self::fail('a closed period was recalculated');
            } catch (PeriodClosed $e) {
                self::assertStringContainsString('Period 2026-09 of scheme "studios" was already run', $e->getMessage());
            }
        }
        // Another scheme is another set of periods.
        self::assertSame(60_000, $payouts->run('authors', $this->september(), new Rules('v1'))->share('studio-a')->minor);
    }

    public function testVerify(): void
    {
        $repo = new InMemoryRunRepository();
        $payouts = new Payouts($repo);
        $payouts->run('studios', $this->september(), new Rules('2026-09', minimumPayout: 5_000));
        $v = $payouts->verify('studios', Period::month('2026-09'));
        self::assertTrue($v->matches);
        self::assertSame($v->stored, $v->recalculated);
    }

    public function testVerifyCatchesAChangedResult(): void
    {
        $stored = new InMemoryRunRepository();
        (new Payouts($stored))->run('studios', $this->september(), new Rules('2026-09'));
        $run = $stored->find('studios', '2026-09');
        self::assertNotNull($run);
        // Someone "fixed" studio-c's payout in the table.
        $tampered = new InMemoryRunRepository();
        $tampered->add(new StoredRun($run->scheme, $run->period, $run->inputHash, $run->rulesFingerprint, $run->input, $run->rules, str_replace('"payout":3000', '"payout":3500', $run->result)));
        self::assertFalse((new Payouts($tampered))->verify('studios', Period::month('2026-09'))->matches);
    }

    public function testRaceBetweenTwoProcesses(): void
    {
        // The other process stores the period between our find() and add().
        $repo = new class implements RunRepository {
            public ?StoredRun $theirs = null;
            private bool $first = true;

            public function find(string $scheme, string $period): ?StoredRun
            {
                if ($this->first) {
                    $this->first = false;

                    return null;
                }

                return $this->theirs;
            }

            public function add(StoredRun $run): void
            {
                $this->theirs = $run;
                throw new PeriodAlreadyStored('taken');
            }
        };
        $run = (new Payouts($repo))->run('studios', $this->september(), new Rules('2026-09'));
        self::assertSame(60_000, $run->share('studio-a')->minor);
    }
}
