<?php

declare(strict_types=1);

namespace Swish\Utils;

use Ramsey\Uuid\Uuid;

/**
 * Generates instruction UUIDs for Swish API requests.
 *
 * Swish requires a 32-character uppercase hexadecimal string (UUID without hyphens).
 */
final class IdGenerator
{
    /**
     * Generate a new instruction UUID.
     *
     * @return string 32-character uppercase hex string
     */
    public static function generate(): string
    {
        return strtoupper(str_replace('-', '', Uuid::uuid4()->toString()));
    }
}
