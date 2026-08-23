<?php

declare(strict_types=1);

namespace Quillstack\Auth\Exceptions;

/**
 * Somebody was recognised, and may not do this.
 *
 * A different answer from not being recognised at all: `401` says try again with credentials,
 * `403` says do not bother.
 */
class NotAuthorisedException extends AuthException
{
    /**
     * {@inheritDoc}
     */
    public function getStatusCode(): int
    {
        return 403;
    }
}
