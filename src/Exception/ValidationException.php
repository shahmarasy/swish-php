<?php

declare(strict_types=1);

namespace Swish\Exception;

/**
 * Thrown on HTTP 422 — request was syntactically valid but semantically incorrect.
 */
class ValidationException extends SwishException
{
}
