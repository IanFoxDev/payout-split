# Contributing

## Running locally

You need PHP 8.3 or later, Composer and Docker.

```bash
composer install
make databases-up    # PostgreSQL on 55434, MySQL (Percona Server 8.4) on 33307
make check           # PHP-CS-Fixer, PHPStan at level max, PHPUnit
make databases-down
```

Without the databases the storage tests are skipped, not failed. The Makefile sets
`PAYOUT_PG_DSN` and `PAYOUT_MYSQL_DSN` for the containers above.

## Where things are

| Path | What |
|---|---|
| `src/Allocation.php` | Splitting an amount by weights and the remainder rules |
| `src/Splitter.php`, `src/Run.php`, `src/Line.php` | A period: minimum payout, carry-over, explain |
| `src/Input.php`, `src/Rules.php`, `src/Canonical.php` | What a run is calculated from, and its canonical form |
| `src/Payouts.php`, `src/Storage` | Storing runs, repeat runs, verify |
| `tests/AllocationPropertiesTest.php` | The invariants on ten thousand random inputs |
| `examples/studios` | The example; its output is checked by the tests |

## Changing the arithmetic

Any change to how shares are computed has to keep the properties in
`tests/AllocationPropertiesTest.php` and `tests/SplitterTest.php`: the sum is the pool,
every share is within one unit of its exact value, zero weights get nothing, and the
order of the input does not matter. A change that alters results for existing inputs
changes stored runs' verification and is **BREAKING**.

## Pull requests

- One logical change per pull request; Conventional Commits.
- `make check` passes with the databases up. Add a line to `CHANGELOG.md`.
