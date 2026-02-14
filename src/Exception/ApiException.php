<?php

declare(strict_types=1);

namespace Swish\Exception;

/**
 * Thrown on HTTP 400, 415, 429, 500, 504 — general API-level errors.
 */
class ApiException extends SwishException
{
}
