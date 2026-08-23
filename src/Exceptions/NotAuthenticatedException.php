<?php

declare(strict_types=1);

namespace Quillstack\Auth\Exceptions;

/**
 * Nobody was recognised: no credentials, or credentials standing for nobody.
 */
class NotAuthenticatedException extends AuthException
{
    /**
     * {@inheritDoc}
     */
    public function getStatusCode(): int
    {
        return 401;
    }
}
