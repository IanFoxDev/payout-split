# Example: paying studios by minutes watched

A streaming platform shares a monthly pool with studios in proportion to the minutes
their content was watched. `run.php` runs July, August and September:

- July and August pay 50.00 USD or more. `dune` earns 29.98 and then 17.11, below the
  minimum, so the amounts are carried over.
- September changes the rules: the minimum drops to 25.00 and the cents left over by
  rounding go to the platform's own account. `dune` is paid 58.17: 11.08 earned plus
  47.09 carried in.
- Running July again with corrected minutes is refused, because July is closed. Running
  it again with the same minutes returns what was paid, and recalculating July from
  what was stored gives the same result.

```bash
composer install
php examples/studios/run.php
```

`expected-output.txt` is the output; the test suite checks that it does not change.
