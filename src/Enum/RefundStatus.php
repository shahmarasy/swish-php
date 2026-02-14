<?php

declare(strict_types=1);

namespace Swish\Enum;

/**
 * Refund statuses as defined by the Swish API.
 */
enum RefundStatus: string
{
    case CREATED = 'CREATED';
    case DEBITED = 'DEBITED';
    case PAID = 'PAID';
    case ERROR = 'ERROR';
}
