# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/). Before 1.0, minor versions may break the
API; such changes are marked **BREAKING**.

## [Unreleased]

## [0.1.0] - 2026-10-05

The first release: a pool split by weights so the shares always add up to it, a minimum
payout with carry-over, and periods that are calculated once and can be verified later.
PostgreSQL and MySQL storage.

### Added

- `Allocation::split()`: splits an amount in minor units by integer weights with the
  largest remainder method, or another remainder rule (`LargestShare`, `First`,
  `HouseAccount`). The shares always add up to the amount; arithmetic is exact with
  `brick/math`.
- `Splitter::run()` for a period: minimum payout with carry-over to the next period,
  `share()`, `carriedOver()`, `carryOverForNextPeriod()` and `explain()`.
- Versioned `Rules` and a canonical SHA-256 of the `Input`.
- `Payouts`: a period is calculated once per scheme; a repeat run with the same input
  returns the stored result, different input throws `PeriodClosed`; `verify()`
  recalculates a stored period and compares it byte for byte.
- `PdoRunRepository` for PostgreSQL and MySQL with schemas in `schema/`, and
  `InMemoryRunRepository`.

[Unreleased]: https://github.com/IanFoxDev/payout-split/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/IanFoxDev/payout-split/releases/tag/v0.1.0
