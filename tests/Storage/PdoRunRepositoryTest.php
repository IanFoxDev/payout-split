<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Tests\Storage;

use IanFoxDev\PayoutSplit\Input;
use IanFoxDev\PayoutSplit\Money;
use IanFoxDev\PayoutSplit\Payouts;
use IanFoxDev\PayoutSplit\Period;
use IanFoxDev\PayoutSplit\PeriodClosed;
use IanFoxDev\PayoutSplit\Rules;
use IanFoxDev\PayoutSplit\Storage\PdoRunRepository;
use IanFoxDev\PayoutSplit\Storage\PeriodAlreadyStored;
use IanFoxDev\PayoutSplit\Storage\StoredRun;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Runs against PostgreSQL and MySQL when PAYOUT_PG_DSN and PAYOUT_MYSQL_DSN are set
 * (make databases-up starts both).
 */
final class PdoRunRepositoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function databases(): iterable
    {
        yield 'postgresql' => ['PAYOUT_PG_DSN', 'postgresql.sql'];
        yield 'mysql' => ['PAYOUT_MYSQL_DSN', 'mysql.sql'];
    }

    private function pdo(string $env, string $schema): \PDO
    {
        $dsn = getenv($env);
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped("$env not set");
        }
        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('DROP TABLE IF EXISTS payout_runs');
        $sql = file_get_contents(__DIR__ . '/../../schema/' . $schema);
        self::assertIsString($sql);
        $pdo->exec($sql);

        return $pdo;
    }

    #[DataProvider('databases')]
    public function testStoreFindAndRefuseASecondRun(string $env, string $schema): void
    {
        $repo = new PdoRunRepository($this->pdo($env, $schema));
        self::assertNull($repo->find('studios', '2026-09'));
        $run = new StoredRun('studios', '2026-09', str_repeat('a', 64), str_repeat('b', 64), '{"in":1}', '{"r":1}', '{"out":1}');
        $repo->add($run);
        self::assertEquals($run, $repo->find('studios', '2026-09'));
        $this->expectException(PeriodAlreadyStored::class);
        $repo->add($run);
    }

    #[DataProvider('databases')]
    public function testPayoutsEndToEnd(string $env, string $schema): void
    {
        $payouts = new Payouts(new PdoRunRepository($this->pdo($env, $schema)));
        $weights = [];
        for ($i = 1; $i <= 500; $i++) {
            $weights[(string) $i] = $i * 7919 % 10_007; // numeric ids, as PHP makes them ints
        }
        $input = new Input(Period::month('2026-09'), Money::of(9_000_000_000_000, 'USD'), $weights, ['7' => 1]);
        $rules = new Rules('2026-09', minimumPayout: 50_000);

        $first = $payouts->run('authors', $input, $rules);
        self::assertSame($first->toJson(), $payouts->run('authors', $input, $rules)->toJson());
        self::assertTrue($payouts->verify('authors', Period::month('2026-09'))->matches);

        $this->expectException(PeriodClosed::class);
        $payouts->run('authors', new Input(Period::month('2026-09'), Money::of(9_000_000_000_001, 'USD'), $weights), $rules);
    }
}
