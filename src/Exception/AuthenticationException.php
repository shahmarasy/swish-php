<?php

declare(strict_types=1);

namespace Swish\Exception;

/**
 * Thrown on HTTP 401/403 — certificate or enrollment issues.
 */
class AuthenticationException extends SwishException
{
}
