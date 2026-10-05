# 0001. Exact arithmetic, and the shares always add up to the pool

Date: 2026-10-05. Status: accepted.

## Context

Splitting a pool by weights looks like one line: `share = pool * weight / total`. In
practice it goes wrong in three places.

- **Rounding.** Each share is rounded to the cent on its own, and the rounded shares add
  up to a little more or less than the pool. The difference is a few cents per run and
  grows with every period, until finance finds it.
- **Overflow and floats.** A pool of 10 million dollars is 10^9 cents; a weight can be a
  count of views in the billions. Their product does not fit in a 64-bit integer, and a
  float silently loses the low digits.
- **Repeatability.** A partner asks why September paid less than August. Unless the same
  input and the same rules give the same numbers, there is no answer.

## Decision

- **Amounts are integers in minor units** with a currency code. Weights are non-negative
  integers: views, minutes, or sales in minor units. A fractional weight is scaled to an
  integer by the caller.
- **The arithmetic is exact.** `pool * weight` and the division by the total weight use
  arbitrary-precision integers (`brick/math`). No floats anywhere.
- **The remainder is distributed by a stated rule.** The default is the largest remainder
  method: every partner gets the integer part of their exact share, and the units left
  over go one by one to the partners with the largest fractional parts. A tie goes to the
  partner whose id sorts first. Other rules (to the largest share, to the first partner,
  to a house account) are a setting, never an accident.
- **The sum is checked on every run.** If the shares do not add up to the pool, the run
  throws instead of returning a result. This should never happen; the check is what
  makes that a fact rather than a hope.
- **Same input, same rules, same output.** A run records the version of the rules and a
  SHA-256 of a canonical form of its input. Recalculating a closed period with the stored
  input and rules gives the same result, byte for byte.

## Consequences

- A dependency on `brick/math`. It is pure PHP and uses GMP or BCMath when they are
  installed, so it works on every PHP install.
- Callers convert their own money type to minor units at the boundary. Adapters for
  brick/money and moneyphp can come later without changing the core.
- The invariant is tested with property-based tests on many random pools and weights,
  not only with hand-picked examples.
