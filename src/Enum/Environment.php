<?php

declare(strict_types=1);

namespace Swish\Enum;

/**
 * Swish API environments with their base URLs.
 */
enum Environment: string
{
    case Production = 'production';
    case Test = 'test';
    case Sandbox = 'sandbox';

    /**
     * Get the base URL for this environment.
     */
    public function baseUrl(): string
    {
        return match ($this) {
            self::Production => 'https://cpc.getswish.net',
            self::Test => 'https://mss.cpc.getswish.net',
            self::Sandbox => 'https://staging.getswish.pub.tds.tieto.com',
        };
    }
}
