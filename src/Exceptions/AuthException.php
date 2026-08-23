<?php

declare(strict_types=1);

namespace Quillstack\Auth\Exceptions;

use RuntimeException;

/**
 * What everything here throws, carrying the status it means.
 *
 * The status travels with the exception so that whatever answers requests — this package has
 * no opinion on what that is — can answer correctly without knowing which exception it caught.
 */
abstract class AuthException extends RuntimeException
{
    abstract public function getStatusCode(): int;
}
