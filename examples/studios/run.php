<?php

declare(strict_types=1);

// Three months of a streaming platform paying studios by minutes watched.
// php examples/studios/run.php

use IanFoxDev\PayoutSplit\Input;
use IanFoxDev\PayoutSplit\Money;
use IanFoxDev\PayoutSplit\Payouts;
use IanFoxDev\PayoutSplit\Period;
use IanFoxDev\PayoutSplit\PeriodClosed;
use IanFoxDev\PayoutSplit\Remainder;
use IanFoxDev\PayoutSplit\Rules;
use IanFoxDev\PayoutSplit\Run;
use IanFoxDev\PayoutSplit\Storage\InMemoryRunRepository;

require __DIR__ . '/../../vendor/autoload.php';

$payouts = new Payouts(new InMemoryRunRepository());

// Minutes watched per studio. Small studios earn less than the minimum payout.
$minutes = [
    '2026-07' => ['aurora' => 1_200_431, 'birch' => 845_002, 'cobalt' => 19_877, 'dune' => 2_480],
    '2026-08' => ['aurora' => 1_150_008, 'birch' => 911_430, 'cobalt' => 23_004, 'dune' => 1_500],
    '2026-09' => ['aurora' => 1_301_777, 'birch' => 790_115, 'cobalt' => 31_590, 'dune' => 900],
];
$pools = ['2026-07' => 2_500_000, '2026-08' => 2_380_000, '2026-09' => 2_615_511]; // in cents

// July and August: pay 50.00 USD or more, carry smaller amounts over.
// From September: pay 25.00 USD or more, and rounding goes to the platform's account.
$rules = [
    '2026-07' => new Rules('v1', Remainder::LargestRemainder, minimumPayout: 5_000),
    '2026-08' => new Rules('v1', Remainder::LargestRemainder, minimumPayout: 5_000),
    '2026-09' => new Rules('v2', Remainder::HouseAccount, minimumPayout: 2_500, houseAccount: 'platform'),
];

function usd(int $cents): string
{
    return sprintf('%s%d.%02d', $cents < 0 ? '-' : '', intdiv(abs($cents), 100), abs($cents) % 100);
}

function show(Run $run): void
{
    printf("%s, rules %s, pool %s USD\n", $run->input->period->id, $run->rules->version, usd($run->input->pool->minor));
    printf("  %-9s %10s %10s %10s %10s\n", 'partner', 'earned', 'carried in', 'paid', 'carried');
    foreach ($run->lines as $line) {
        printf("  %-9s %10s %10s %10s %10s\n", $line->partner, usd($line->allocated()), usd($line->carriedIn), usd($line->payout), usd($line->carriedOut));
    }
    echo "\n";
}

$carry = [];
foreach ($minutes as $month => $weights) {
    $run = $payouts->run('studios', new Input(Period::month($month), Money::of($pools[$month], 'USD'), $weights, $carry), $rules[$month]);
    show($run);
    $carry = $run->carryOverForNextPeriod();
}

echo "How dune's September payout was calculated:\n";
foreach ($run->explain('dune') as $key => $value) {
    printf("  %-16s %s\n", $key, (string) $value);
}
echo "\n";

// Someone finds 10,000 more minutes for cobalt in July and runs July again.
$corrected = $minutes['2026-07'];
$corrected['cobalt'] += 10_000;
try {
    $payouts->run('studios', new Input(Period::month('2026-07'), Money::of($pools['2026-07'], 'USD'), $corrected), $rules['2026-07']);
} catch (PeriodClosed $e) {
    echo "Running July again with new data: refused.\n  ", $e->getMessage(), "\n\n";
}

// Running July again with the same data returns what was paid.
$july = $payouts->run('studios', new Input(Period::month('2026-07'), Money::of($pools['2026-07'], 'USD'), $minutes['2026-07']), $rules['2026-07']);
printf("Running July again with the same data: aurora %s USD, as before.\n", usd($july->share('aurora')->minor));
printf("July recalculated from what was stored matches: %s\n", $payouts->verify('studios', Period::month('2026-07'))->matches ? 'yes' : 'no');
