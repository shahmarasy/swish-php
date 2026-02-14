<?php

declare(strict_types=1);

namespace Swish\Enum;

/**
 * Supported currencies. Swish currently only supports SEK.
 */
enum Currency: string
{
    case SEK = 'SEK';
}
