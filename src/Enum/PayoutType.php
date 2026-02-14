<?php

declare(strict_types=1);

namespace Swish\Enum;

/**
 * Payout type. Currently only PAYOUT is supported by Swish.
 */
enum PayoutType: string
{
    case PAYOUT = 'PAYOUT';
}
