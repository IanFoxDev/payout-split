<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit;

/**
 * Where the units left over after rounding every share down go.
 */
enum Remainder: string
{
    /** One unit each to the partners with the largest fractional parts; a tie goes to the id that sorts first. */
    case LargestRemainder = 'largest_remainder';

    /** All of it to the partner with the largest weight. */
    case LargestShare = 'largest_share';

    /** All of it to the partner with a non-zero weight whose id sorts first. */
    case First = 'first';

    /** All of it to a separate account, such as the platform's own. */
    case HouseAccount = 'house_account';
}
