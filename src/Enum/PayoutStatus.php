<?php

declare(strict_types=1);

namespace Swish\Enum;

/**
 * Payout statuses as defined by the Swish API.
 */
enum PayoutStatus: string
{
    case CREATED = 'CREATED';
    case DEBITED = 'DEBITED';
    case PAID = 'PAID';
    case ERROR = 'ERROR';
}
