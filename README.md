# payout-split

[![php](https://github.com/IanFoxDev/payout-split/actions/workflows/php.yml/badge.svg)](https://github.com/IanFoxDev/payout-split/actions/workflows/php.yml)

Splits a pool of money between partners by weights: studios by minutes watched,
authors by sales, affiliates by referred revenue. The shares always add up to the pool,
amounts below a minimum payout are carried over to the next period, and a closed period
recalculated later gives the same result, byte for byte.

> Status: in development, nothing released yet.

The usual way to do this is a script: take the pool, multiply by each weight, divide,
round. Then the rounded shares add up to a few cents more or less than the pool, a
second run of the script pays twice, and when a partner asks why August was lower than
July there is nothing to show them.

## Install

```bash
composer require ianfoxdev/payout-split
```

PHP 8.3 or later. The only dependency is `brick/math`, for arithmetic that does not
overflow or round.

## Example

```php
use IanFoxDev\PayoutSplit\{Input, Money, Payouts, Period, Rules};
use IanFoxDev\PayoutSplit\Storage\PdoRunRepository;

$payouts = new Payouts(new PdoRunRepository($pdo));   // or InMemoryRunRepository

$run = $payouts->run(
    'studios',
    new Input(
        Period::month('2026-07'),
        Money::of(2_500_000, 'USD'),                    // 25,000.00 USD in cents
        ['aurora' => 1_200_431, 'birch' => 845_002, 'cobalt' => 19_877, 'dune' => 2_480],
    ),
    new Rules('v1', minimumPayout: 5_000),              // pay 50.00 USD or more
);

$run->share('aurora');                 // Money: 1451345 USD cents
$run->carriedOver('dune');             // Money: 2998, below the minimum
$run->carryOverForNextPeriod();        // ['dune' => 2998], into August's Input
$run->explain('dune');                 // weights, exact share, rounding, minimum, carry
```

[examples/studios](examples/studios) runs three months: `dune` earns 29.98 and 17.11,
is carried over twice and paid 58.17 in September, when the rules change.

## What it guarantees

- **The shares add up to the pool.** Every share is rounded down, and the cents left
  over go one by one to the partners with the largest fractional parts (the largest
  remainder method). Every share is within one cent of its exact value. The sum is
  checked on every run; a run that does not add up throws instead of returning.
- **No floats, no overflow.** `pool x weight` is computed with arbitrary-precision
  integers: a pool of 10^9 cents times weights in the billions is exact.
- **A period is calculated once.** Running it again with the same input and rules
  returns the stored result. Running it with different data throws `PeriodClosed`:

  ```
  Period 2026-07 of scheme "studios" was already run with input e8930305bb5a and rules
  8a3002e9f07a; this call has input 62c5aa46eb0d and rules 8a3002e9f07a.
  ```

- **A closed period can be checked.** `verify()` recalculates it from the stored input
  and rules and compares the result byte for byte.
- **Rules are versioned.** A run stores its rules, so changing them in September does
  not change July.

These properties are tested on ten thousand random pools and weights, not only on
examples. Why the arithmetic works this way:
[docs/adr/0001-exact-arithmetic-and-the-sum.md](docs/adr/0001-exact-arithmetic-and-the-sum.md).

## Rounding rules

| `Remainder` | The cents left over by rounding go to |
|---|---|
| `LargestRemainder` (default) | the partners with the largest fractional parts, one each; a tie goes to the id that sorts first |
| `LargestShare` | the partner with the largest weight |
| `First` | the partner with a non-zero weight whose id sorts first |
| `HouseAccount` | a separate account, such as the platform's own |

## Storage

Runs are kept by a `RunRepository`: `InMemoryRunRepository` for tests, and
`PdoRunRepository` for PostgreSQL or MySQL with the table from
[schema/postgresql.sql](schema/postgresql.sql) or [schema/mysql.sql](schema/mysql.sql).
Rows are only inserted. The primary key on scheme and period makes a second run of a
period fail even when two processes race.

More: [docs/usage.md](docs/usage.md).

## Not yet

Fixed rates and caps next to the pool, clawback of refunded payments, pools in several
currencies, a ledger integration, Laravel and Symfony wrappers, adapters for brick/money
and moneyphp. Open an issue if you need one of them first.

## License

[MIT](LICENSE)
