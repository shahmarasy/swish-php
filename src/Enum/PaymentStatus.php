<?php

declare(strict_types=1);

namespace Swish\Enum;

/**
 * Payment request statuses as defined by the Swish API.
 */
enum PaymentStatus: string
{
    case CREATED = 'CREATED';
    case PAID = 'PAID';
    case DECLINED = 'DECLINED';
    case ERROR = 'ERROR';
    case CANCELLED = 'CANCELLED';
}
