# Usage

## Input

```php
new Input(
    Period::month('2026-09'),          // or Period::named('2026-Q3')
    Money::of(12_000_000, 'USD'),      // the pool in minor units: 120,000.00 USD
    $weights,                          // iterable<partner id, int>
    $carriedOver,                      // iterable<partner id, int>, minor units, optional
);
```

- Weights are non-negative integers. Use a count (views, minutes) or an amount in minor
  units (sales). A fractional weight has to be scaled to an integer first, for example
  hours with two decimals times 100.
- Partner ids are strings. Numeric ids work, but PHP turns numeric array keys into ints,
  so look them up as `$run->share('42')` and expect int keys when you iterate.
- `carriedOver` is what was below the minimum payout last period, usually the previous
  run's `carryOverForNextPeriod()`. A partner can be in `carriedOver` without a weight
  this period; they take part with weight 0 and are paid once the total reaches the
  minimum.

## Rules

```php
new Rules(
    version: '2026-09',
    remainder: Remainder::LargestRemainder,   // see the README for the other rules
    minimumPayout: 5_000,                     // minor units; 0 pays everything
    houseAccount: null,                       // required with Remainder::HouseAccount
);
```

The version is yours to choose. Two rule sets with the same version and different
values are still told apart: a run stores the rules themselves and their SHA-256.

## A run

`Splitter::run(Input, Rules): Run` calculates without storing. `Payouts::run(string
$scheme, Input, Rules): Run` stores the run, or returns the stored one; the scheme
separates independent payout programmes (studios, authors, affiliates).

For each partner a run has a `Line`:

| Field | Meaning |
|---|---|
| `weight` | the weight this period |
| `baseShare` | the exact share rounded down |
| `remainderUnits` | cents added by the remainder rule |
| `carriedIn` | carried over from earlier periods |
| `payout` | paid this period: earned plus carried in, if that reaches the minimum |
| `carriedOut` | carried to the next period otherwise |

Two invariants hold for every run, and a run that breaks one throws: the earned shares
add up to the pool, and payouts plus amounts carried out add up to the pool plus
amounts carried in.

`explain($partner)` returns the whole calculation for one partner, including the exact
share as `pool * weight / total_weight`, ready for a statement to that partner.

## Storage

Create the table from `schema/postgresql.sql` or `schema/mysql.sql` (rename it and pass
the name to `PdoRunRepository` if you like). A row holds the canonical input, the rules
and the canonical result, so `Payouts::verify($scheme, $period)` can recalculate a
period years later and compare.

`Payouts::run()` throws `PeriodClosed` when the period exists with different input or
rules. To correct a closed period, book the correction in the next one; recalculating
history is exactly what this refuses to do silently.
