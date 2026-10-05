# Security

payout-split computes amounts in your process and stores them through the PDO connection
you give it. It makes no network requests.

If you find a vulnerability, for example input that makes a run pay out more than the
pool, or a way to make `verify()` report a match for a changed result, do not open a
public issue. Report it privately through
[GitHub](https://github.com/IanFoxDev/payout-split/security/advisories/new), or write to
ianfoxdeveloper@gmail.com.

## Supported versions

Fixes go into the latest release only. Until 1.0 that is the latest `0.x` tag.
